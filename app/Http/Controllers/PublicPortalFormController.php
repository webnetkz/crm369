<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitPortalFormRequest;
use App\Models\PortalForm;
use App\Support\PortalFormSubmissionManager;
use Inertia\Inertia;
use Inertia\Response;

class PublicPortalFormController extends Controller
{
    public function show(PortalForm $portalForm): Response
    {
        abort_unless($portalForm->is_active, 404);
        $portalForm->loadMissing('fields');

        return Inertia::render('public/forms/Show', [
            'form' => [
                'id' => $portalForm->id,
                'public_token' => $portalForm->public_token,
                'name' => $portalForm->name,
                'description' => $portalForm->description,
                'fields' => $portalForm->fields
                    ->map(fn ($field): array => [
                        'id' => $field->id,
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type,
                        'placeholder' => $field->placeholder,
                        'is_required' => $field->is_required,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function submit(
        SubmitPortalFormRequest $request,
        PortalForm $portalForm,
        PortalFormSubmissionManager $submissionManager,
    ) {
        abort_unless($portalForm->is_active, 404);

        $submissionManager->submit($portalForm, $request->submissionPayload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.forms.submitted_success')]);

        return to_route('forms.public.show', ['portalForm' => $portalForm->public_token]);
    }
}
