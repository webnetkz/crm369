<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortalFormRequest;
use App\Models\PortalForm;
use App\Support\PortalFormManager;
use App\Support\PortalFormPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalFormController extends Controller
{
    public function index(Request $request, PortalFormPageData $pageData): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return Inertia::render('forms/Index', $pageData->buildForViewer($user, $request->integer('form')));
    }

    public function store(
        StorePortalFormRequest $request,
        PortalFormManager $formManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $form = $formManager->create($request, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.created_success')]);

        return to_route('forms.index', ['form' => $form->id]);
    }

    public function update(
        StorePortalFormRequest $request,
        PortalForm $portalForm,
        PortalFormManager $formManager,
    ): RedirectResponse {
        $visibleForm = $this->visibleForm($request, $portalForm, $formManager);

        $formManager->update($request, $visibleForm);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.updated_success')]);

        return to_route('forms.index', ['form' => $visibleForm->id]);
    }

    public function destroy(Request $request, PortalForm $portalForm, PortalFormManager $formManager): RedirectResponse
    {
        $visibleForm = $this->visibleForm($request, $portalForm, $formManager);

        $visibleForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.deleted_success')]);

        return to_route('forms.index');
    }

    private function visibleForm(Request $request, PortalForm $portalForm, PortalFormManager $formManager): PortalForm
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $formManager->findVisibleForm($user, $portalForm->id);
    }
}
