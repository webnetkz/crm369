<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Security\RunSystemSecurityAudit;
use App\Actions\Security\UpdateTwoFactorRequirement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSystemSecurityAuditRequest;
use App\Http\Requests\Settings\UpdateTwoFactorRequirementRequest;
use App\Models\User;
use App\Support\SystemSecurityPageData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SystemSecurityController extends Controller
{
    public function __construct(
        public SystemSecurityPageData $pageData,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/SystemSecurity', $this->pageData->build());
    }

    public function storeAudit(
        StoreSystemSecurityAuditRequest $request,
        RunSystemSecurityAudit $runSystemSecurityAudit,
    ): RedirectResponse {
        abort_unless($request->user() instanceof User, 403);

        /** @var array<string, bool> $manualAnswers */
        $manualAnswers = $request->validated('manual');

        $runSystemSecurityAudit->execute($request->user(), $manualAnswers);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.system_security.audit_completed'),
        ]);

        return back();
    }

    public function updateTwoFactorRequirement(
        UpdateTwoFactorRequirementRequest $request,
        UpdateTwoFactorRequirement $updateTwoFactorRequirement,
    ): RedirectResponse {
        abort_unless($request->user() instanceof User, 403);

        $enabled = $request->boolean('enabled');
        $updateTwoFactorRequirement->execute($request->user(), $enabled);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? __('ui.system_security.two_factor_required_enabled')
                : __('ui.system_security.two_factor_required_disabled'),
        ]);

        return back();
    }
}
