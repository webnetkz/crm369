<?php

namespace App\Support;

use App\Models\PortalForm;
use App\Models\PortalFormField;
use App\Models\PortalFormSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PortalFormPageData
{
    /**
     * @param  Collection<int, PortalForm>  $forms
     * @return array<string, mixed>
     */
    public function build(User $viewer, Collection $forms, ?PortalForm $activeForm = null): array
    {
        return [
            'forms' => $forms
                ->map(fn (PortalForm $form): array => $this->formListItem($form))
                ->values()
                ->all(),
            'activeForm' => $activeForm ? $this->activeForm($activeForm) : null,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $this->displayName($user),
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'fieldTypes' => collect(PortalFormField::availableTypes())
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => __('ui.forms.field_type_'.$type),
                ])
                ->values()
                ->all(),
            'can' => [
                'create' => true,
                'manageActive' => $activeForm
                    ? ($viewer->id === $activeForm->owner_user_id || $viewer->isSuperAdmin())
                    : false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formListItem(PortalForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'submission_mode' => $form->submission_mode,
            'is_active' => $form->is_active,
            'public_url' => route('forms.public.show', ['portalForm' => $form->public_token]),
            'target_user_name' => $form->targetUser ? $this->displayName($form->targetUser) : null,
            'fields_count' => $form->fields_count ?? $form->fields->count(),
            'submissions_count' => $form->submissions_count ?? $form->submissions->count(),
            'last_submission_at' => is_string($form->submissions_max_created_at)
                ? Carbon::parse($form->submissions_max_created_at)->toISOString()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeForm(PortalForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'description' => $form->description,
            'submission_mode' => $form->submission_mode,
            'is_active' => $form->is_active,
            'public_url' => route('forms.public.show', ['portalForm' => $form->public_token]),
            'target_user' => $form->targetUser ? [
                'id' => $form->targetUser->id,
                'name' => $this->displayName($form->targetUser),
                'email' => $form->targetUser->email,
            ] : null,
            'owner' => $form->owner ? [
                'id' => $form->owner->id,
                'name' => $this->displayName($form->owner),
                'email' => $form->owner->email,
            ] : null,
            'fields' => $form->fields
                ->map(fn (PortalFormField $field): array => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'placeholder' => $field->placeholder,
                    'is_required' => $field->is_required,
                    'sort_order' => $field->sort_order,
                ])
                ->values()
                ->all(),
            'submissions' => $form->submissions
                ->map(fn (PortalFormSubmission $submission): array => [
                    'id' => $submission->id,
                    'created_at' => $submission->created_at?->toISOString(),
                    'project_task_id' => $submission->project_task_id,
                    'chat_conversation_id' => $submission->chat_conversation_id,
                    'chat_message_id' => $submission->chat_message_id,
                    'target_user_name' => $submission->targetUser ? $this->displayName($submission->targetUser) : null,
                    'payload' => $submission->payload ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    private function displayName(User $user): string
    {
        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : $user->email;
    }
}
