<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDashboardConfigurationRequest;
use App\Support\DashboardConfiguration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DashboardConfigurationController extends Controller
{
    public function update(
        UpdateDashboardConfigurationRequest $request,
        DashboardConfiguration $dashboardConfiguration,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->update([
            'dashboard_configuration' => $dashboardConfiguration->normalize(
                $request->configuration(),
            ),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.dashboard.configuration_saved'),
        ]);

        return to_route('dashboard');
    }
}
