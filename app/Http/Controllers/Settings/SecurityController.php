<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\RevokeUserMobileSessions;
use App\Actions\Security\RunSecurityAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\StoreSecurityAuditRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\SystemSecuritySetting;
use App\Models\User;
use App\Support\SecurityAuditPageData;
use App\Support\SecurityPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    public function __construct(
        public SecurityPageData $securityPageData,
        public SecurityAuditPageData $securityAuditPageData,
    ) {}

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        abort_unless($request->user() instanceof User, 403);

        $user = $request->user();
        $twoFactorRequired = SystemSecuritySetting::requiresTwoFactorAuthentication();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'sessions' => $this->securityPageData->sessionsFor($user, $request->session()->getId()),
            'loginActivities' => $this->securityPageData->loginActivitiesFor($user),
            'securityAudit' => $this->securityAuditPageData->forUser($user),
            'twoFactorRequired' => $twoFactorRequired,
            'mustCompleteTwoFactor' => $twoFactorRequired
                && ! $user->hasEnabledTwoFactorAuthentication(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    public function storeAudit(
        StoreSecurityAuditRequest $request,
        RunSecurityAudit $runSecurityAudit,
    ): RedirectResponse {
        abort_unless($request->user() instanceof User, 403);

        /** @var array<string, bool> $manualAnswers */
        $manualAnswers = $request->validated('manual');

        $runSecurityAudit->execute(
            $request->user(),
            $manualAnswers,
            $request->session()->getId(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.security.audit_completed'),
        ]);

        return back();
    }

    /**
     * Update the user's password.
     */
    public function update(
        PasswordUpdateRequest $request,
        RevokeUserMobileSessions $revokeUserMobileSessions,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $revokeUserMobileSessions): void {
            $request->user()->update([
                'password' => $request->password,
            ]);

            $revokeUserMobileSessions($request->user());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
