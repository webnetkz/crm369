<?php

namespace App\Support;

use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\PortalForm;
use App\Models\PortalFormField;
use App\Models\PortalFormSubmission;
use App\Models\ProjectTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalFormSubmissionManager
{
    public function __construct(
        private readonly DirectConversationManager $directConversationManager,
        private readonly TaskConversationManager $taskConversationManager,
    ) {}

    /**
     * @param  array<int, array{id: int|null, label: string, type: string, placeholder: string|null, is_required: bool, sort_order: int}>  $fieldRows
     */
    public function syncFields(PortalForm $form, array $fieldRows): void
    {
        $existingFields = $form->fields()->get()->keyBy('id');
        $retainedFieldIds = [];
        $takenKeys = $existingFields
            ->mapWithKeys(fn (PortalFormField $field): array => [$field->id => $field->key])
            ->all();

        foreach ($fieldRows as $index => $fieldRow) {
            $fieldId = $fieldRow['id'];
            $field = $fieldId !== null
                ? $existingFields->get($fieldId)
                : null;

            $key = $field?->key ?? $this->generateFieldKey($fieldRow['label'], array_values($takenKeys));
            $takenKeys[$fieldId ?? ($index + 1) * -1] = $key;

            $field ??= new PortalFormField([
                'portal_form_id' => $form->id,
                'key' => $key,
            ]);

            $field->fill([
                'label' => $fieldRow['label'],
                'type' => $fieldRow['type'],
                'placeholder' => $fieldRow['placeholder'],
                'is_required' => $fieldRow['is_required'],
                'sort_order' => ($index + 1) * 10,
            ]);
            $field->save();

            $retainedFieldIds[] = $field->id;
        }

        PortalFormField::query()
            ->where('portal_form_id', $form->id)
            ->whereNotIn('id', $retainedFieldIds)
            ->delete();
    }

    /**
     * @param  array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>  $payload
     */
    public function submit(PortalForm $form, array $payload): PortalFormSubmission
    {
        $form->loadMissing(['owner', 'targetUser', 'fields']);

        return DB::transaction(function () use ($form, $payload): PortalFormSubmission {
            $submission = PortalFormSubmission::query()->create([
                'portal_form_id' => $form->id,
                'target_user_id' => $form->target_user_id,
                'payload' => $payload,
            ]);

            if ($form->submission_mode === PortalForm::SUBMISSION_MODE_TASK) {
                $task = $this->createTask($form, $payload);
                $this->taskConversationManager->ensureForTask($task, $form->owner);

                $submission->update([
                    'project_task_id' => $task->id,
                ]);

                return $submission->fresh(['task', 'targetUser']) ?? $submission;
            }

            $message = $this->createChatMessage($form, $payload);

            $submission->update([
                'chat_conversation_id' => $message->chat_conversation_id,
                'chat_message_id' => $message->id,
            ]);

            return $submission->fresh(['conversation', 'chatMessage', 'targetUser']) ?? $submission;
        });
    }

    /**
     * @param  array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>  $payload
     */
    private function createTask(PortalForm $form, array $payload): ProjectTask
    {
        return ProjectTask::query()->create([
            'project_id' => null,
            'parent_task_id' => null,
            'creator_user_id' => $form->owner_user_id,
            'assignee_user_id' => $form->target_user_id,
            'title' => $this->submissionTitle($form, $payload),
            'description' => $this->submissionBody($form, $payload),
            'status' => ProjectTask::STATUS_TODO,
            'importance' => ProjectTask::IMPORTANCE_NORMAL,
            'complexity' => 3,
            'due_at' => null,
            'due_reminder_sent_at' => null,
            'completed_at' => null,
            'sort_order' => 0,
            'updated_by_user_id' => $form->owner_user_id,
        ]);
    }

    /**
     * @param  array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>  $payload
     */
    private function createChatMessage(PortalForm $form, array $payload): ChatMessage
    {
        $conversation = $this->directConversationManager->ensure($form->owner, $form->targetUser);

        $message = ChatMessage::query()->create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $form->owner_user_id,
            'body' => $this->submissionBody($form, $payload),
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();

        ChatConversationParticipant::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('user_id', $form->owner_user_id)
            ->update(['last_read_at' => $message->created_at]);

        return $message;
    }

    /**
     * @param  array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>  $payload
     */
    private function submissionTitle(PortalForm $form, array $payload): string
    {
        $firstValue = collect($payload)
            ->pluck('value')
            ->filter(fn (?string $value): bool => is_string($value) && trim($value) !== '')
            ->first();

        return Str::limit(
            trim($form->name.($firstValue ? ' - '.$firstValue : '')),
            255,
        );
    }

    /**
     * @param  array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>  $payload
     */
    private function submissionBody(PortalForm $form, array $payload): string
    {
        $lines = [
            __('ui.forms.submission_intro', ['form' => $form->name]),
        ];

        if (is_string($form->description) && trim($form->description) !== '') {
            $lines[] = '';
            $lines[] = $form->description;
        }

        foreach ($payload as $row) {
            $lines[] = '';
            $lines[] = $row['label'].':';
            $lines[] = $row['value'] ?? __('ui.forms.empty_value');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $takenKeys
     */
    private function generateFieldKey(string $label, array $takenKeys): string
    {
        $baseKey = Str::snake(Str::slug($label, ' '));
        $baseKey = $baseKey !== '' ? $baseKey : 'field';
        $candidate = $baseKey;
        $suffix = 2;

        while (in_array($candidate, $takenKeys, true)) {
            $candidate = $baseKey.'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
