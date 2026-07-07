<?php

namespace App\Http\Controllers;

use App\Models\PortalSetting;
use App\Support\ApiCatalog;
use App\Support\WebhookDocumentationCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentationController extends Controller
{
    public function __invoke(
        Request $request,
        ApiCatalog $apiCatalog,
        WebhookDocumentationCatalog $webhookDocumentationCatalog,
    ): Response {
        $settings = PortalSetting::current();
        $canViewApiDocumentation = $settings->isModuleEnabled('api');
        $canViewWebhookDocumentation = $settings->isModuleEnabled('webhooks')
            && ($request->user()?->canManageWebhooks() ?? false);

        abort_unless($canViewApiDocumentation || $canViewWebhookDocumentation, 403);

        return Inertia::render('documentation/Index', [
            'sections' => [
                'api' => $canViewApiDocumentation,
                'webhooks' => $canViewWebhookDocumentation,
            ],
            'apiBaseUrl' => $canViewApiDocumentation
                ? rtrim($request->getSchemeAndHttpHost(), '/').'/api/v1'
                : null,
            'apiDocumentation' => $canViewApiDocumentation
                ? $apiCatalog->sections()
                : null,
            'webhookDocumentation' => $canViewWebhookDocumentation
                ? $webhookDocumentationCatalog->payload()
                : null,
        ]);
    }
}
