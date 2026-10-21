<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Support\MobilePortalData;
use Illuminate\Http\JsonResponse;

class MobilePortalController extends Controller
{
    /**
     * Public discovery endpoint used to bind the native application to a portal domain.
     */
    public function show(MobilePortalData $mobilePortalData): JsonResponse
    {
        return response()->json([
            'data' => $mobilePortalData->build(),
        ]);
    }
}
