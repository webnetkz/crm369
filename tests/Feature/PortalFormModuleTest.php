<?php

use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\PortalForm;
use App\Models\PortalFormSubmission;
use App\Models\ProjectTask;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated user can open forms workspace and create a form with fields', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('forms.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('forms/Index')
            ->has('forms')
            ->has('availableUsers')
            ->has('fieldTypes')
            ->where('can.create', true));

    $this->actingAs($owner)
        ->post(route('forms.store'), [
            'name' => 'Client intake',
            'description' => "Collect a short summary\nand contact details.",
            'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
            'target_user_id' => $target->id,
            'is_active' => true,
            'fields' => [
                [
                    'label' => 'Client name',
                    'type' => 'text',
                    'placeholder' => 'Jane Doe',
                    'is_required' => true,
                ],
                [
                    'label' => 'Request details',
                    'type' => 'textarea',
                    'placeholder' => 'Describe the request',
                    'is_required' => true,
                ],
            ],
        ])
        ->assertRedirect();

    $form = PortalForm::query()
        ->where('name', 'Client intake')
        ->firstOrFail();

    expect($form->owner_user_id)->toBe($owner->id)
        ->and($form->target_user_id)->toBe($target->id)
        ->and($form->submission_mode)->toBe(PortalForm::SUBMISSION_MODE_TASK)
        ->and($form->public_token)->not->toBe('');

    expect($form->fields()->count())->toBe(2)
        ->and($form->fields()->pluck('label')->all())->toEqual(['Client name', 'Request details']);
});

test('guest can open public form and submission can create a standalone task for target user', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $form = PortalForm::factory()->create([
        'owner_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'name' => 'Website brief',
        'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
        'is_active' => true,
    ]);

    $form->fields()->createMany([
        [
            'key' => 'company_name',
            'label' => 'Company name',
            'type' => 'text',
            'placeholder' => 'Acme',
            'is_required' => true,
            'sort_order' => 10,
        ],
        [
            'key' => 'brief',
            'label' => 'Brief',
            'type' => 'textarea',
            'placeholder' => 'Tell us about the project',
            'is_required' => true,
            'sort_order' => 20,
        ],
    ]);

    $this->get(route('forms.public.show', ['portalForm' => $form->public_token]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/forms/Show')
            ->where('form.name', 'Website brief')
            ->has('form.fields', 2));

    $this->post(route('forms.public.submit', ['portalForm' => $form->public_token]), [
        'values' => [
            'company_name' => 'Acme Studio',
            'brief' => "Need a landing page\nwith two sections",
        ],
    ])->assertRedirect(route('forms.public.show', ['portalForm' => $form->public_token]));

    $task = ProjectTask::query()->latest('id')->first();
    $submission = PortalFormSubmission::query()->latest('id')->first();

    expect($task)->not->toBeNull()
        ->and($task?->project_id)->toBeNull()
        ->and($task?->creator_user_id)->toBe($owner->id)
        ->and($task?->assignee_user_id)->toBe($target->id)
        ->and($task?->description)->toContain('Acme Studio')
        ->and($task?->description)->toContain('Need a landing page');

    expect($submission)->not->toBeNull()
        ->and($submission?->portal_form_id)->toBe($form->id)
        ->and($submission?->project_task_id)->toBe($task?->id)
        ->and(collect($submission?->payload)->pluck('value')->all())->toContain('Acme Studio');
});

test('guest submission can be delivered as a direct chat message to selected user', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $form = PortalForm::factory()->create([
        'owner_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'name' => 'Support handoff',
        'submission_mode' => PortalForm::SUBMISSION_MODE_CHAT,
        'is_active' => true,
    ]);

    $form->fields()->createMany([
        [
            'key' => 'subject',
            'label' => 'Subject',
            'type' => 'text',
            'placeholder' => 'Login issue',
            'is_required' => true,
            'sort_order' => 10,
        ],
        [
            'key' => 'details',
            'label' => 'Details',
            'type' => 'textarea',
            'placeholder' => 'Describe the issue',
            'is_required' => true,
            'sort_order' => 20,
        ],
    ]);

    $this->post(route('forms.public.submit', ['portalForm' => $form->public_token]), [
        'values' => [
            'subject' => 'Login issue',
            'details' => "User cannot sign in\nsince yesterday",
        ],
    ])->assertRedirect(route('forms.public.show', ['portalForm' => $form->public_token]));

    $message = ChatMessage::query()->latest('id')->first();
    $submission = PortalFormSubmission::query()->latest('id')->first();

    expect($message)->not->toBeNull()
        ->and($message?->user_id)->toBe($owner->id)
        ->and($message?->body)->toContain('Support handoff')
        ->and($message?->body)->toContain('Login issue')
        ->and($message?->body)->toContain('User cannot sign in');

    expect(ChatConversationParticipant::query()
        ->where('chat_conversation_id', $message?->chat_conversation_id)
        ->pluck('user_id')
        ->all())->toEqualCanonicalizing([$owner->id, $target->id]);

    expect($submission)->not->toBeNull()
        ->and($submission?->chat_message_id)->toBe($message?->id)
        ->and($submission?->chat_conversation_id)->toBe($message?->chat_conversation_id);
});

test('forms module is wired into sidebar menu and public page delivery actions', function () {
    $menuItemModel = file_get_contents(app_path('Models/MenuItem.php'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $formsPage = file_get_contents(resource_path('js/pages/forms/Index.vue'));
    $publicPage = file_get_contents(resource_path('js/pages/public/forms/Show.vue'));

    expect($menuItemModel)->toContain("'title_key' => 'ui.forms.title'")
        ->and($menuItemModel)->toContain("'url' => '/forms'")
        ->and($menuItemModel)->toContain("'forms',")
        ->and($sidebar)->toContain("isMenuItemVisible('forms')")
        ->and($sidebar)->toContain('formsIndex()')
        ->and($formsPage)->toContain('copyPublicLink')
        ->and($formsPage)->toContain('showWorkspaceTask')
        ->and($publicPage)->toContain('submitPortalForm.url');
});
