<?php

namespace App\Providers;

use App\Listeners\InvalidateNotificationRuntimeCache;
use App\Models\ApiAccessToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::define('view-users', fn (User $user): bool => $user->canViewUsers());
        Gate::define('manage-user-activation', fn (User $user): bool => $user->canManageUserActivation());
        Gate::define('manage-user-accounts', fn (User $user): bool => $user->canManageUserAccounts());
        Gate::define('manage-api-tokens', fn (User $user): bool => $user->canManageApiTokens());
        Gate::define('impersonate-users', fn (User $user): bool => $user->canImpersonateUsers());
        Gate::define('manage-users', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('access-company-structure', fn (User $user): bool => $user->canAccessCompanyStructure());
        Gate::define('access-news', fn (User $user): bool => $user->canAccessNews());
        Gate::define('access-projects', fn (User $user): bool => $user->canAccessProjects());
        Gate::define('access-chats', fn (User $user): bool => $user->canAccessChats());
        Gate::define('access-knowledge-bases', fn (User $user): bool => $user->canAccessKnowledgeBases());
        Gate::define('access-forms', fn (User $user): bool => $user->canAccessForms());
        Gate::define('access-edo', fn (User $user): bool => $user->canAccessEdo());
        Gate::define('access-files', fn (User $user): bool => $user->canAccessFiles());
        Gate::define('access-production', fn (User $user): bool => $user->canAccessProduction());
        Gate::define('access-warehouses', fn (User $user): bool => $user->canAccessWarehouses());
        Gate::define('access-equipment', fn (User $user): bool => $user->canAccessEquipment());
        Gate::define('access-tsd', fn (User $user): bool => $user->canAccessTsd());
        Gate::define('access-directories', fn (User $user): bool => $user->canAccessDirectories());
        Gate::define('manage-directories', fn (User $user): bool => $user->canManageDirectories());
        Gate::define('manage-messenger-integrations', fn (User $user): bool => $user->canManageMessengerIntegrations());
        Gate::define('manage-business-processes', fn (User $user): bool => $user->canManageBusinessProcesses());
        Gate::define('manage-webhooks', fn (User $user): bool => $user->canManageWebhooks());
        Gate::define('access-contacts', fn (User $user): bool => $user->canAccessContacts());
        Gate::define('manage-knowledge-bases', fn (User $user): bool => $user->canManageKnowledgeBases());
        Gate::define('manage-news', fn (User $user): bool => $user->canManageNews());
        Gate::define('manage-funnels', fn (User $user): bool => $user->canManageFunnels());
        Event::listen(NotificationSent::class, InvalidateNotificationRuntimeCache::class);

        Auth::viaRequest('api-token', function (Request $request): ?User {
            $plainTextToken = trim((string) $request->bearerToken());

            if ($plainTextToken === '') {
                return null;
            }

            $tokenPrefixes = ApiAccessToken::prefixCandidatesFor($plainTextToken);

            $apiAccessToken = ApiAccessToken::query()
                ->with('user.group')
                ->whereIn('token_prefix', $tokenPrefixes)
                ->get()
                ->first(fn (ApiAccessToken $apiAccessToken): bool => $apiAccessToken->matchesToken($plainTextToken));

            if (! $apiAccessToken || ! $apiAccessToken->isAvailable()) {
                return null;
            }

            $user = $apiAccessToken->user;

            if (
                ! $user
                || ! $user->is_active
                || $user->email_verified_at === null
                || ! $user->canManageApiTokens()
            ) {
                return null;
            }

            $apiAccessToken->touchUsage($request);
            $request->attributes->set('api_access_token', $apiAccessToken);

            return $user;
        });

        RateLimiter::for('api', function (Request $request): array {
            $apiAccessToken = $request->attributes->get('api_access_token');
            $subject = $apiAccessToken instanceof ApiAccessToken
                ? 'token:'.$apiAccessToken->id
                : ($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip());

            return [
                Limit::perMinute(240)->by('minute:'.$subject),
                Limit::perDay(5000)->by('day:'.$subject),
            ];
        });

        RateLimiter::for('api-tokens', function (Request $request): array {
            $subject = $request->user()
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return [
                Limit::perMinute(5)->by('minute:'.$subject),
                Limit::perHour(30)->by('hour:'.$subject),
            ];
        });

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
