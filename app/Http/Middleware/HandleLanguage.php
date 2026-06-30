<?php

namespace App\Http\Middleware;

use App\Models\PortalSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class HandleLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->user()?->preferredLanguage()
            ?? $request->cookie('language')
            ?? PortalSetting::current()->defaultLanguage();

        if (! in_array($language, PortalSetting::SUPPORTED_LANGUAGES, true)) {
            $language = PortalSetting::current()->defaultLanguage();
        }

        App::setLocale($language);

        return $next($request);
    }
}
