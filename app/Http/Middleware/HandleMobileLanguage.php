<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use App\Models\PortalSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleMobileLanguage
{
    /**
     * Resolve the response language for native mobile API requests.
     *
     * Authenticated requests use the user's preferred language. Guest requests
     * (portal discovery, sign in) use the device language sent in the
     * Accept-Language header, falling back to the portal default language.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLanguage($request));

        return $next($request);
    }

    private function resolveLanguage(Request $request): string
    {
        $portalLanguage = PortalSetting::current()->defaultLanguage();
        $user = $request->attributes->get('mobile_access_token') instanceof MobileAccessToken
            ? $request->user()
            : null;

        if ($user instanceof User) {
            $preferredLanguage = $user->preferredLanguage();

            if (is_string($preferredLanguage) && in_array($preferredLanguage, PortalSetting::SUPPORTED_LANGUAGES, true)) {
                return $preferredLanguage;
            }
        }

        foreach ($request->getLanguages() as $deviceLanguage) {
            $primaryLanguage = mb_strtolower(strtok(str_replace('-', '_', $deviceLanguage), '_') ?: '');

            if (in_array($primaryLanguage, PortalSetting::SUPPORTED_LANGUAGES, true)) {
                return $primaryLanguage;
            }
        }

        return $portalLanguage;
    }
}
