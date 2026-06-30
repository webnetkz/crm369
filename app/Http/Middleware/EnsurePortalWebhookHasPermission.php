<?php

namespace App\Http\Middleware;

use App\Models\PortalWebhook;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalWebhookHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $portalWebhook = $request->route('portalWebhook');

        if (! $portalWebhook instanceof PortalWebhook) {
            abort(404);
        }

        if (! $portalWebhook->is_active) {
            return response()->json([
                'message' => __('ui.webhooks.error_inactive'),
            ], 403);
        }

        if ($portalWebhook->isExpired()) {
            return response()->json([
                'message' => __('ui.webhooks.error_expired'),
            ], 410);
        }

        $token = $this->resolveToken($request);

        if (! $portalWebhook->matchesToken($token)) {
            return response()->json([
                'message' => __('ui.webhooks.error_invalid_token'),
            ], 401);
        }

        if (is_string($permission) && $permission !== '' && ! $portalWebhook->hasPermission($permission)) {
            return response()->json([
                'message' => __('ui.webhooks.error_missing_permission'),
            ], 403);
        }

        $portalWebhook->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('portal_webhook', $portalWebhook);
        $request->attributes->set('portal_webhook_token', trim((string) $token));

        return $next($request);
    }

    private function resolveToken(Request $request): ?string
    {
        $bearerToken = trim((string) $request->bearerToken());

        if ($bearerToken !== '') {
            return $bearerToken;
        }

        $headerToken = trim((string) $request->header('X-Webhook-Token'));

        if ($headerToken !== '') {
            return $headerToken;
        }

        $queryToken = trim($request->string('token')->toString());

        return $queryToken !== '' ? $queryToken : null;
    }
}
