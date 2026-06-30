<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $token = $request->attributes->get('api_access_token');

        if (! $token instanceof ApiAccessToken) {
            $plainTextToken = trim((string) $request->bearerToken());

            if ($plainTextToken !== '') {
                $tokenPrefixes = ApiAccessToken::prefixCandidatesFor($plainTextToken);

                $resolvedToken = ApiAccessToken::query()
                    ->whereIn('token_prefix', $tokenPrefixes)
                    ->get()
                    ->first(fn (ApiAccessToken $apiAccessToken): bool => $apiAccessToken->matchesToken($plainTextToken));

                if (
                    $resolvedToken
                    && $resolvedToken->isAvailable()
                    && $request->user()?->id === $resolvedToken->user_id
                ) {
                    $token = $resolvedToken;
                    $request->attributes->set('api_access_token', $resolvedToken);
                }
            }
        }

        if (! $token instanceof ApiAccessToken) {
            abort(401);
        }

        if (! $token->hasPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
