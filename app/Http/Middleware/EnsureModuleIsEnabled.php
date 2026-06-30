<?php

namespace App\Http\Middleware;

use App\Models\PortalSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(PortalSetting::current()->isModuleEnabled($module), 404);

        return $next($request);
    }
}
