<?php

use App\Http\Middleware\EnsureApiTokenHasPermission;
use App\Http\Middleware\EnsureModuleIsEnabled;
use App\Http\Middleware\EnsurePortalWebhookHasPermission;
use App\Http\Middleware\EnsureTwoFactorAuthenticationIsRequired;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleLanguage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->trimStrings(except: ['body']);
        $middleware->preventRequestForgery(except: [
            'portal-webhooks/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleLanguage::class,
            EnsureUserIsActive::class,
            EnsureTwoFactorAuthenticationIsRequired::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::using(10),
        ]);

        $middleware->alias([
            'api.token' => EnsureApiTokenHasPermission::class,
            'module.enabled' => EnsureModuleIsEnabled::class,
            'portal.webhook' => EnsurePortalWebhookHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Not Found',
            ], 404);
        });
    })->create();
