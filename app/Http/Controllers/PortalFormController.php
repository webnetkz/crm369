<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortalFormRequest;
use App\Models\PortalForm;
use App\Support\PortalFormPageData;
use App\Support\PortalFormSubmissionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalFormController extends Controller
{
    public function index(Request $request, PortalFormPageData $pageData): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $forms = $this->formQuery($user)->get();
        $requestedFormId = $request->integer('form');
        /** @var PortalForm|null $activeForm */
        $activeForm = $requestedFormId > 0 ? $forms->firstWhere('id', $requestedFormId) : null;

        if ($activeForm) {
            $activeForm->setRelation(
                'submissions',
                $activeForm->submissions()
                    ->with(['targetUser:id,name,last_name,email'])
                    ->latest('id')
                    ->limit(25)
                    ->get(),
            );
        }

        return Inertia::render('forms/Index', $pageData->build($user, $forms, $activeForm));
    }

    public function store(
        StorePortalFormRequest $request,
        PortalFormSubmissionManager $submissionManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $form = DB::transaction(function () use ($request, $submissionManager, $user): PortalForm {
            $form = PortalForm::query()->create([
                'owner_user_id' => $user->id,
                'target_user_id' => (int) $request->validated('target_user_id'),
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
                'submission_mode' => (string) $request->validated('submission_mode'),
                'public_token' => $this->newPublicToken(),
                'is_active' => $request->boolean('is_active'),
                'style_settings' => $request->styleSettings(),
                'completion_settings' => $request->completionSettings(),
            ]);

            $submissionManager->syncFields($form, $request->fieldRows());

            return $form;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.created_success')]);

        return to_route('forms.index', ['form' => $form->id]);
    }

    public function update(
        StorePortalFormRequest $request,
        PortalForm $portalForm,
        PortalFormSubmissionManager $submissionManager,
    ): RedirectResponse {
        $visibleForm = $this->visibleForm($request, $portalForm);

        DB::transaction(function () use ($request, $submissionManager, $visibleForm): void {
            $visibleForm->update([
                'target_user_id' => (int) $request->validated('target_user_id'),
                'name' => (string) $request->validated('name'),
                'description' => $request->validated('description'),
                'submission_mode' => (string) $request->validated('submission_mode'),
                'is_active' => $request->boolean('is_active'),
                'style_settings' => $request->styleSettings(),
                'completion_settings' => $request->completionSettings(),
            ]);

            $submissionManager->syncFields($visibleForm, $request->fieldRows());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.updated_success')]);

        return to_route('forms.index', ['form' => $visibleForm->id]);
    }

    public function destroy(Request $request, PortalForm $portalForm): RedirectResponse
    {
        $visibleForm = $this->visibleForm($request, $portalForm);

        $visibleForm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.deleted_success')]);

        return to_route('forms.index');
    }

    private function formQuery($user)
    {
        return PortalForm::query()
            ->with([
                'owner:id,name,last_name,email',
                'targetUser:id,name,last_name,email',
                'fields',
            ])
            ->withCount(['fields', 'submissions'])
            ->withMax('submissions', 'created_at')
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->where('owner_user_id', $user->id),
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function visibleForm(Request $request, PortalForm $portalForm): PortalForm
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $this->formQuery($user)->findOrFail($portalForm->id);
    }

    private function newPublicToken(): string
    {
        do {
            $token = Str::random(32);
        } while (PortalForm::query()->where('public_token', $token)->exists());

        return $token;
    }
}
