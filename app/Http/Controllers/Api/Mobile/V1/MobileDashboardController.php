<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDashboardConfigurationRequest;
use App\Support\DashboardConfiguration;
use App\Support\DashboardPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    /**
     * The dashboard statistics and the personal dashboard configuration (the web dashboard page props).
     */
    public function show(Request $request, DashboardPageData $pageData): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return response()->json([
            'data' => $pageData->build($user),
        ]);
    }

    /**
     * Save the personal dashboard configuration and return the dashboard rebuilt with it.
     */
    public function updateConfiguration(
        UpdateDashboardConfigurationRequest $request,
        DashboardConfiguration $dashboardConfiguration,
        DashboardPageData $pageData,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->update([
            'dashboard_configuration' => $dashboardConfiguration->normalize(
                $request->configuration(),
            ),
        ]);

        return response()->json([
            'message' => __('ui.dashboard.configuration_saved'),
            'data' => $pageData->build($user),
        ]);
    }
}
