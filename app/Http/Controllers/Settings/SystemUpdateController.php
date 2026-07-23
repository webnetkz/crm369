<?php

namespace App\Http\Controllers\Settings;

use App\Actions\SystemUpdates\CheckSystemVersions;
use App\Actions\SystemUpdates\StartSystemUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StartSystemUpdateRequest;
use App\Models\User;
use App\Support\SystemUpdates\SystemUpdatePageData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemUpdateController extends Controller
{
    public function __construct(
        private SystemUpdatePageData $pageData,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/SystemUpdates', $this->pageData->build());
    }

    public function check(CheckSystemVersions $checkSystemVersions): RedirectResponse
    {
        $checkSystemVersions->execute();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.system_updates.check_completed'),
        ]);

        return back();
    }

    public function start(
        StartSystemUpdateRequest $request,
        StartSystemUpdate $startSystemUpdate,
    ): RedirectResponse {
        abort_unless($request->user() instanceof User, 403);

        $startSystemUpdate->execute(
            $request->user(),
            (string) $request->validated('component'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.system_updates.run_started'),
        ]);

        return back();
    }
}
