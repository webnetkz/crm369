<?php

namespace App\Http\Middleware;

use App\Models\SystemSecuritySetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticationIsRequired
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! SystemSecuritySetting::requiresTwoFactorAuthentication()) {
            return $next($request);
        }

        if (
            ! Features::canManageTwoFactorAuthentication()
            && $user->isSuperAdmin()
            && $request->routeIs('settings.system-security.*')
        ) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.disable')) {
            return $this->disabledTwoFactorResponse($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication() || $this->isSetupRoute($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.system_security.two_factor_setup_required'),
            ], 403);
        }

        return redirect()
            ->route('security.edit')
            ->with('two_factor_required', true);
    }

    private function isSetupRoute(Request $request): bool
    {
        return $request->routeIs([
            'security.edit',
            'user-password.update',
            'password.confirm*',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.qr-code',
            'two-factor.recovery-codes',
            'two-factor.regenerate-recovery-codes',
            'two-factor.secret-key',
            'verification.*',
            'language.update',
            'logout',
        ]);
    }

    private function disabledTwoFactorResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.system_security.two_factor_disable_blocked'),
            ], 403);
        }

        return redirect()
            ->route('security.edit')
            ->withErrors([
                'two_factor' => __('ui.system_security.two_factor_disable_blocked'),
            ]);
    }
}
