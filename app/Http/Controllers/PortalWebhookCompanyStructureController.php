<?php

namespace App\Http\Controllers;

use App\Models\PortalWebhook;
use App\Models\User;
use App\Support\CompanyStructureData;
use Illuminate\Http\JsonResponse;

class PortalWebhookCompanyStructureController extends Controller
{
    public function index(PortalWebhook $portalWebhook, CompanyStructureData $companyStructureData): JsonResponse
    {
        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            ...$companyStructureData->webhookData(),
        ]);
    }

    public function show(
        PortalWebhook $portalWebhook,
        User $user,
        CompanyStructureData $companyStructureData,
    ): JsonResponse {
        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            ...$companyStructureData->apiShowData($user),
        ]);
    }
}
