<?php

namespace App\Actions\Security;

use App\Models\SecurityAudit;
use App\Models\User;
use App\Support\SecurityPageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Features;

class RunSecurityAudit
{
    public function __construct(
        private SecurityPageData $securityPageData,
    ) {}

    /**
     * @param  array{
     *     unique_password: bool,
     *     recovery_codes_stored: bool,
     *     sessions_reviewed: bool,
     *     device_protected: bool,
     *     phishing_ready: bool
     * }  $manualAnswers
     */
    public function execute(User $user, array $manualAnswers, string $currentSessionId): SecurityAudit
    {
        $lock = Cache::lock('security-audit:user:'.$user->id, 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'audit' => __('ui.security.audit_already_running'),
            ]);
        }

        try {
            return $this->run($user, $manualAnswers, $currentSessionId);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, bool>  $manualAnswers
     */
    private function run(User $user, array $manualAnswers, string $currentSessionId): SecurityAudit
    {
        $sessions = $this->securityPageData->sessionsFor($user, $currentSessionId);
        $recentLoginAlerts = $user->loginActivities()
            ->where('logged_in_at', '>=', now()->subDays(7))
            ->where(function ($query): void {
                $query->where('is_new_device', true)
                    ->orWhere('is_new_ip', true);
            })
            ->count();
        $apiTokens = $user->apiAccessTokens()
            ->select(['id', 'expires_at', 'last_used_at', 'created_at'])
            ->get();
        $riskyApiTokens = $apiTokens
            ->filter(fn ($token): bool => $token->expires_at === null
                || $token->expires_at->isPast()
                || ($token->last_used_at ?? $token->created_at)?->lt(now()->subDays(90)))
            ->count();
        $hasTwoFactor = Features::canManageTwoFactorAuthentication()
            && $user->hasEnabledTwoFactorAuthentication();
        $hasPasskey = Features::canManagePasskeys()
            && $user->passkeys()->exists();
        $hasRecoveryCodes = $hasTwoFactor
            && filled($user->two_factor_recovery_codes);

        $checks = collect([
            $this->check(
                key: 'email_verified',
                category: 'identity',
                status: $user->hasVerifiedEmail() ? 'passed' : 'failed',
                severity: 'critical',
                weight: 15,
            ),
            $this->check(
                key: 'two_factor_enabled',
                category: 'authentication',
                status: $hasTwoFactor ? 'passed' : 'failed',
                severity: 'critical',
                weight: 20,
            ),
            $this->check(
                key: 'passkey_registered',
                category: 'authentication',
                status: Features::canManagePasskeys()
                    ? ($hasPasskey ? 'passed' : 'warning')
                    : 'skipped',
                severity: 'recommended',
                weight: 8,
            ),
            $this->check(
                key: 'recovery_codes_available',
                category: 'recovery',
                status: $hasRecoveryCodes ? 'passed' : ($hasTwoFactor ? 'failed' : 'skipped'),
                severity: 'important',
                weight: 10,
            ),
            $this->check(
                key: 'active_sessions',
                category: 'access',
                status: match (true) {
                    count($sessions) <= 2 => 'passed',
                    count($sessions) <= 5 => 'warning',
                    default => 'failed',
                },
                severity: 'important',
                weight: 10,
                meta: ['count' => count($sessions)],
            ),
            $this->check(
                key: 'recent_login_alerts',
                category: 'access',
                status: match (true) {
                    $recentLoginAlerts === 0 => 'passed',
                    $recentLoginAlerts <= 2 => 'warning',
                    default => 'failed',
                },
                severity: 'important',
                weight: 10,
                meta: ['count' => $recentLoginAlerts, 'days' => 7],
            ),
            $this->check(
                key: 'api_tokens',
                category: 'access',
                status: match (true) {
                    $riskyApiTokens === 0 => 'passed',
                    $riskyApiTokens === 1 => 'warning',
                    default => 'failed',
                },
                severity: 'important',
                weight: 8,
                meta: ['count' => $apiTokens->count(), 'risky_count' => $riskyApiTokens],
            ),
            ...$this->manualChecks($manualAnswers),
        ]);

        $score = $this->score($checks);
        $failedCriticalChecks = $checks
            ->where('severity', 'critical')
            ->where('status', 'failed')
            ->count();

        return SecurityAudit::query()->create([
            'user_id' => $user->id,
            'score' => $score,
            'risk_level' => $this->riskLevel($score, $failedCriticalChecks),
            'passed_count' => $checks->where('status', 'passed')->count(),
            'warning_count' => $checks->where('status', 'warning')->count(),
            'failed_count' => $checks->where('status', 'failed')->count(),
            'skipped_count' => $checks->where('status', 'skipped')->count(),
            'total_count' => $checks->count(),
            'checks' => $checks->values()->all(),
            'manual_answers' => $manualAnswers,
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
            'unique_password' => ['authentication', 'critical', 8],
            'recovery_codes_stored' => ['recovery', 'important', 4],
            'sessions_reviewed' => ['access', 'important', 4],
            'device_protected' => ['device', 'important', 4],
            'phishing_ready' => ['awareness', 'recommended', 3],
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
     * @param  array<string, int|bool|string>  $meta
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
        $activeChecks = $checks->where('status', '!=', 'skipped');
        $availablePoints = (float) $activeChecks->sum('weight');

        if ($availablePoints === 0.0) {
            return 0;
        }

        return (int) round(((float) $activeChecks->sum('earned') / $availablePoints) * 100);
    }

    private function riskLevel(int $score, int $failedCriticalChecks): string
    {
        if ($score >= 85 && $failedCriticalChecks === 0) {
            return 'protected';
        }

        if ($score >= 65 && $failedCriticalChecks <= 1) {
            return 'attention';
        }

        return 'high_risk';
    }
}
