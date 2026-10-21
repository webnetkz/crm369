<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortalFormRequest;
use App\Models\PortalForm;
use App\Models\User;
use App\Support\PortalFormManager;
use App\Support\PortalFormPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePortalFormController extends Controller
{
    /**
     * The forms workspace (the web forms page props). The "form" query parameter opens a form
     * with its fields and latest submissions as the active form, like the web page does.
     */
    public function index(Request $request, PortalFormPageData $pageData): JsonResponse
    {
        return response()->json([
            'data' => $pageData->buildForViewer($this->viewer($request), $request->integer('form')),
        ]);
    }

    /**
     * A form the user may manage with its fields, public link and latest submissions.
     */
    public function show(
        Request $request,
        PortalForm $portalForm,
        PortalFormManager $formManager,
        PortalFormPageData $pageData,
    ): JsonResponse {
        $user = $this->viewer($request);

        return response()->json([
            'data' => $this->form($user, $portalForm->id, $formManager, $pageData),
        ]);
    }

    public function store(
        StorePortalFormRequest $request,
        PortalFormManager $formManager,
        PortalFormPageData $pageData,
    ): JsonResponse {
        $user = $this->viewer($request);
        $form = $formManager->create($request, $user);

        return response()->json([
            'message' => __('ui.forms.created_success'),
            'data' => $this->form($user, $form->id, $formManager, $pageData),
        ], 201);
    }

    public function update(
        StorePortalFormRequest $request,
        PortalForm $portalForm,
        PortalFormManager $formManager,
        PortalFormPageData $pageData,
    ): JsonResponse {
        $user = $this->viewer($request);
        $visibleForm = $formManager->findVisibleForm($user, $portalForm->id);

        $formManager->update($request, $visibleForm);

        return response()->json([
            'message' => __('ui.forms.updated_success'),
            'data' => $this->form($user, $visibleForm->id, $formManager, $pageData),
        ]);
    }

    public function destroy(Request $request, PortalForm $portalForm, PortalFormManager $formManager): JsonResponse
    {
        $visibleForm = $formManager->findVisibleForm($this->viewer($request), $portalForm->id);

        $visibleForm->delete();

        return response()->json([
            'message' => __('ui.forms.deleted_success'),
        ]);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * The form freshly loaded through the user's visibility rules (404 when hidden) and serialized with its latest submissions.
     *
     * @return array<string, mixed>
     */
    private function form(User $user, int $portalFormId, PortalFormManager $formManager, PortalFormPageData $pageData): array
    {
        $form = $formManager->loadRecentSubmissions($formManager->findVisibleForm($user, $portalFormId));

        return $pageData->formDetail($user, $form);
    }
}
