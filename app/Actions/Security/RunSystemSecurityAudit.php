<?php

namespace App\Actions\Security;

use App\Models\ApiAccessToken;
use App\Models\MessengerIntegration;
use App\Models\SystemSecurityAudit;
use App\Models\SystemSecuritySetting;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunSystemSecurityAudit
{
    public function __construct(
        private Migrator $migrator,
    ) {}

    /**
     * @param  array{
     *     backups_verified: bool,
     *     infrastructure_patched: bool,
     *     privileged_access_reviewed: bool,
     *     security_headers_configured: bool,
     *     incident_plan_ready: bool,
     *     secrets_rotated: bool
     * }  $manualAnswers
     */
    public function execute(User $actor, array $manualAnswers): SystemSecurityAudit
    {
        $lock = Cache::lock('system-security-audit:running', 90);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'audit' => __('ui.system_security.audit_already_running'),
            ]);
        }

        try {
            return $this->run($actor, $manualAnswers);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, bool>  $manualAnswers
     */
    private function run(User $actor, array $manualAnswers): SystemSecurityAudit
    {
        $startedAt = hrtime(true);
        $checks = collect([
            $this->safeCheck('production_environment', 'runtime', 'important', 3, function (): array {
                $isProduction = app()->isProduction();

                return [$isProduction ? 'passed' : 'warning', ['production' => $isProduction]];
            }),
            $this->safeCheck('debug_mode', 'runtime', 'critical', 8, function (): array {
                $debugEnabled = (bool) config('app.debug');

                return [$debugEnabled ? 'failed' : 'passed', ['debug_enabled' => $debugEnabled]];
            }),
            $this->safeCheck('app_key', 'runtime', 'critical', 8, function (): array {
                $configured = filled(config('app.key'));

                return [$configured ? 'passed' : 'failed', ['configured' => $configured]];
            }),
            $this->safeCheck('https_url', 'transport', 'critical', 7, function (): array {
                $usesHttps = str_starts_with((string) config('app.url'), 'https://');

                return [$usesHttps ? 'passed' : 'failed', ['https' => $usesHttps]];
            }),
            $this->safeCheck('session_secure', 'sessions', 'critical', 6, function (): array {
                $secure = config('session.secure') === true;

                return [$secure ? 'passed' : 'failed', ['secure_cookie' => $secure]];
            }),
            $this->safeCheck('session_hardening', 'sessions', 'critical', 6, function (): array {
                $httpOnly = config('session.http_only') === true;
                $sameSite = in_array(config('session.same_site'), ['lax', 'strict'], true);
                $json = config('session.serialization') === 'json';
                $isHardened = $httpOnly && $sameSite && $json;

                return [$isHardened ? 'passed' : 'failed', [
                    'http_only' => $httpOnly,
                    'safe_same_site' => $sameSite,
                    'json_serialization' => $json,
                ]];
            }),
            $this->safeCheck('session_encryption', 'sessions', 'recommended', 2, function (): array {
                $encrypted = config('session.encrypt') === true;

                return [$encrypted ? 'passed' : 'warning', ['encrypted' => $encrypted]];
            }),
            $this->safeCheck('email_verification', 'authentication', 'critical', 6, function (): array {
                $enforced = is_subclass_of(User::class, MustVerifyEmail::class);

                return [$enforced ? 'passed' : 'failed', ['contract_enabled' => $enforced]];
            }),
            $this->safeCheck('auth_rate_limits', 'authentication', 'critical', 6, function (): array {
                $limiterNames = collect(config('fortify.limiters', []))
                    ->filter(fn (mixed $name): bool => is_string($name) && $name !== '');
                $configured = $limiterNames->count() === 3
                    && $limiterNames->every(fn (string $name): bool => RateLimiter::limiter($name) instanceof Closure);

                return [$configured ? 'passed' : 'failed', ['configured_count' => $limiterNames->count()]];
            }),
            $this->safeCheck('global_two_factor_policy', 'authentication', 'important', 5, function (): array {
                $enabled = SystemSecuritySetting::requiresTwoFactorAuthentication();
                $featureAvailable = Features::canManageTwoFactorAuthentication();

                return [match (true) {
                    $enabled && $featureAvailable => 'passed',
                    $enabled => 'failed',
                    default => 'warning',
                }, ['enabled' => $enabled, 'feature_available' => $featureAvailable]];
            }),
            $this->safeCheck('two_factor_coverage', 'authentication', 'critical', 8, function (): array {
                $total = User::query()->where('is_active', true)->count();
                $protected = User::query()
                    ->where('is_active', true)
                    ->whereNotNull('two_factor_secret')
                    ->whereNotNull('two_factor_confirmed_at')
                    ->count();
                $missing = max(0, $total - $protected);
                $missingPercent = $total > 0 ? (int) round(($missing / $total) * 100) : 0;

                return [match (true) {
                    $missing === 0 => 'passed',
                    $missingPercent <= 20 => 'warning',
                    default => 'failed',
                }, ['total' => $total, 'protected' => $protected, 'missing' => $missing]];
            }),
            $this->safeCheck('super_admin_security', 'authentication', 'critical', 8, function (): array {
                $email = trim((string) config('admin.super_admin_email'));
                $users = $email === ''
                    ? collect()
                    : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->get();
                $superAdmin = $users->first();
                $secure = $users->count() === 1
                    && $superAdmin instanceof User
                    && $superAdmin->is_active
                    && $superAdmin->email_verified_at !== null
                    && $superAdmin->hasEnabledTwoFactorAuthentication();

                return [$secure ? 'passed' : 'failed', [
                    'configured' => $email !== '',
                    'matching_accounts' => $users->count(),
                    'active' => $superAdmin?->is_active ?? false,
                    'email_verified' => $superAdmin?->email_verified_at !== null,
                    'two_factor_enabled' => $superAdmin?->hasEnabledTwoFactorAuthentication() ?? false,
                ]];
            }),
            $this->safeCheck('api_tokens', 'access', 'important', 4, function (): array {
                if (! Schema::hasTable('api_access_tokens')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $total = ApiAccessToken::query()->count();
                $withoutExpiry = ApiAccessToken::query()->whereNull('expires_at')->count();
                $expired = ApiAccessToken::query()->where('expires_at', '<', now())->count();
                $ownersWithoutTwoFactor = ApiAccessToken::query()
                    ->whereHas('user', fn ($query) => $query
                        ->whereNull('two_factor_secret')
                        ->orWhereNull('two_factor_confirmed_at'))
                    ->count();
                $risky = $withoutExpiry + $expired + $ownersWithoutTwoFactor;

                return [match (true) {
                    $risky === 0 => 'passed',
                    $risky <= 2 => 'warning',
                    default => 'failed',
                }, compact('total', 'withoutExpiry', 'expired', 'ownersWithoutTwoFactor')];
            }),
            $this->safeCheck('webhook_tokens', 'access', 'important', 4, function (): array {
                if (! Schema::hasTable('portal_webhooks')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $active = DB::table('portal_webhooks')->where('is_active', true);
                $total = (clone $active)->count();
                $withoutExpiry = (clone $active)->whereNull('expires_at')->count();
                $stale = (clone $active)
                    ->where(function ($query): void {
                        $query->where('last_used_at', '<', now()->subDays(90))
                            ->orWhere(function ($query): void {
                                $query->whereNull('last_used_at')
                                    ->where('created_at', '<', now()->subDays(90));
                            });
                    })
                    ->count();
                $risky = $withoutExpiry + $stale;

                return [match (true) {
                    $risky === 0 => 'passed',
                    $risky <= 2 => 'warning',
                    default => 'failed',
                }, compact('total', 'withoutExpiry', 'stale')];
            }),
            $this->safeCheck('stale_sessions', 'sessions', 'important', 3, function (): array {
                if (! Schema::hasTable('sessions')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $stale = DB::table('sessions')
                    ->where('last_activity', '<', now()->subDays(30)->timestamp)
                    ->count();
                $inactiveUsers = DB::table('sessions')
                    ->join('users', 'users.id', '=', 'sessions.user_id')
                    ->where('users.is_active', false)
                    ->count();

                return [match (true) {
                    $stale === 0 && $inactiveUsers === 0 => 'passed',
                    $stale + $inactiveUsers <= 5 => 'warning',
                    default => 'failed',
                }, ['stale' => $stale, 'inactive_users' => $inactiveUsers]];
            }),
            $this->safeCheck('failed_jobs', 'operations', 'important', 3, function (): array {
                if (! Schema::hasTable('failed_jobs')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $count = DB::table('failed_jobs')->count();

                return [match (true) {
                    $count === 0 => 'passed',
                    $count <= 5 => 'warning',
                    default => 'failed',
                }, ['count' => $count]];
            }),
            $this->safeCheck('pending_migrations', 'operations', 'critical', 5, function (): array {
                if (! $this->migrator->repositoryExists()) {
                    return ['skipped', ['unavailable' => true]];
                }

                $files = $this->migrator->getMigrationFiles(database_path('migrations'));
                $ran = array_flip($this->migrator->getRepository()->getRan());
                $pending = collect(array_keys($files))
                    ->reject(fn (string $migration): bool => isset($ran[$migration]))
                    ->count();

                return [$pending === 0 ? 'passed' : 'failed', ['pending' => $pending]];
            }),
            $this->safeCheck('storage_permissions', 'operations', 'critical', 4, function (): array {
                $storageWritable = is_writable(storage_path());
                $cacheWritable = is_writable(base_path('bootstrap/cache'));
                $writable = $storageWritable && $cacheWritable;

                return [$writable ? 'passed' : 'failed', [
                    'storage_writable' => $storageWritable,
                    'cache_writable' => $cacheWritable,
                ]];
            }),
            $this->safeCheck('integration_tls', 'integrations', 'critical', 4, function (): array {
                if (! Schema::hasTable('one_c_integrations')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $unsafe = DB::table('one_c_integrations')
                    ->where('is_enabled', true)
                    ->where('verify_tls', false)
                    ->count();

                return [$unsafe === 0 ? 'passed' : 'failed', ['unsafe_count' => $unsafe]];
            }),
            $this->safeCheck('integration_secrets', 'integrations', 'critical', 5, function (): array {
                if (! Schema::hasTable('messenger_integrations')) {
                    return ['skipped', ['unavailable' => true]];
                }

                $secretKeys = ['api_token', 'bot_token', 'webhook_secret'];
                $exposed = MessengerIntegration::query()
                    ->get(['id', 'settings'])
                    ->filter(function (MessengerIntegration $integration) use ($secretKeys): bool {
                        $settings = is_array($integration->settings) ? $integration->settings : [];

                        return collect($secretKeys)->contains(
                            fn (string $key): bool => filled($settings[$key] ?? null),
                        );
                    })
                    ->count();

                return [$exposed === 0 ? 'passed' : 'failed', ['exposed_integrations' => $exposed]];
            }),
            $this->dependencyCheck('composer_dependencies', ['composer', 'audit', '--locked', '--format=json', '--no-interaction']),
            $this->dependencyCheck('npm_dependencies', ['npm', 'audit', '--omit=dev', '--json']),
            ...$this->manualChecks($manualAnswers),
        ]);

        $score = $this->score($checks);
        $failedCriticalChecks = $checks
            ->where('severity', 'critical')
            ->where('status', 'failed')
            ->count();

        return SystemSecurityAudit::query()->create([
            'performed_by_user_id' => $actor->id,
            'score' => $score,
            'risk_level' => $this->riskLevel($score, $failedCriticalChecks),
            'passed_count' => $checks->where('status', 'passed')->count(),
            'warning_count' => $checks->where('status', 'warning')->count(),
            'failed_count' => $checks->where('status', 'failed')->count(),
            'skipped_count' => $checks->where('status', 'skipped')->count(),
            'total_count' => $checks->count(),
            'checks' => $checks->values()->all(),
            'manual_answers' => $manualAnswers,
            'duration_ms' => max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
            'checked_at' => now(),
        ]);
    }

    /**
     * @param  array<string, bool>  $manualAnswers
     * @return array<int, array<string, mixed>>
     */
    private function manualChecks(array $manualAnswers): array
    {
        return collect([
            'backups_verified' => ['operations', 'critical', 6],
            'infrastructure_patched' => ['infrastructure', 'critical', 6],
            'privileged_access_reviewed' => ['access', 'critical', 5],
            'security_headers_configured' => ['transport', 'important', 4],
            'incident_plan_ready' => ['operations', 'important', 3],
            'secrets_rotated' => ['integrations', 'important', 3],
        ])->map(function (array $definition, string $key) use ($manualAnswers): array {
            [$category, $severity, $weight] = $definition;

            return $this->check(
                key: $key,
                category: $category,
                status: ($manualAnswers[$key] ?? false) ? 'passed' : 'failed',
                severity: $severity,
                weight: $weight,
                meta: ['manual' => true],
            );
        })->values()->all();
    }

    /**
     * @param  array<int, string>  $command
     * @return array<string, mixed>
     */
    private function dependencyCheck(string $key, array $command): array
    {
        return $this->safeCheck($key, 'dependencies', 'critical', 5, function () use ($key, $command): array {
            $result = Process::path(base_path())
                ->timeout(12)
                ->run($command);
            $decoded = json_decode(substr($result->output(), 0, 1_000_000), true);

            if (! is_array($decoded)) {
                return ['skipped', ['unavailable' => true]];
            }

            if ($key === 'composer_dependencies') {
                $advisories = collect($decoded['advisories'] ?? [])
                    ->sum(fn (mixed $items): int => is_array($items) ? count($items) : 0);
                $abandoned = is_array($decoded['abandoned'] ?? null)
                    ? count($decoded['abandoned'])
                    : 0;

                return [match (true) {
                    $advisories > 0 => 'failed',
                    $abandoned > 0 => 'warning',
                    default => 'passed',
                }, compact('advisories', 'abandoned')];
            }

            $vulnerabilities = $decoded['metadata']['vulnerabilities'] ?? [];
            $total = (int) ($vulnerabilities['total'] ?? 0);
            $high = (int) ($vulnerabilities['high'] ?? 0);
            $critical = (int) ($vulnerabilities['critical'] ?? 0);

            return [match (true) {
                $critical > 0 || $high > 0 => 'failed',
                $total > 0 => 'warning',
                default => 'passed',
            }, compact('total', 'high', 'critical')];
        });
    }

    /**
     * @param  Closure(): array{0: string, 1: array<string, int|bool>}  $resolver
     * @return array<string, mixed>
     */
    private function safeCheck(
        string $key,
        string $category,
        string $severity,
        int $weight,
        Closure $resolver,
    ): array {
        try {
            [$status, $meta] = $resolver();

            return $this->check($key, $category, $status, $severity, $weight, $meta);
        } catch (Throwable) {
            return $this->check(
                $key,
                $category,
                'skipped',
                $severity,
                $weight,
                ['unavailable' => true],
            );
        }
    }

    /**
     * @param  array<string, int|bool>  $meta
     * @return array<string, mixed>
     */
    private function check(
        string $key,
        string $category,
        string $status,
        string $severity,
        int $weight,
        array $meta = [],
    ): array {
        return [
            'key' => $key,
            'category' => $category,
            'status' => $status,
            'severity' => $severity,
            'weight' => $weight,
            'earned' => match ($status) {
                'passed' => $weight,
                'warning' => $weight / 2,
                default => 0,
            },
            'meta' => $meta,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $checks
     */
    private function score(Collection $checks): int
    {
        $scoredChecks = $checks->where('status', '!=', 'skipped');
        $availableWeight = (float) $scoredChecks->sum('weight');

        if ($availableWeight <= 0) {
            return 0;
        }

        return (int) round(((float) $scoredChecks->sum('earned') / $availableWeight) * 100);
    }

    private function riskLevel(int $score, int $failedCriticalChecks): string
    {
        return match (true) {
            $score >= 85 && $failedCriticalChecks === 0 => 'protected',
            $score >= 65 && $failedCriticalChecks <= 1 => 'attention',
            default => 'high_risk',
        };
    }
}
