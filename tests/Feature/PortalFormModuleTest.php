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
            ->has('formWidthOptions', 4)
            ->has('formCompletionActionOptions', 3)
            ->where('formStyleDefaults.container_width', 'lg')
            ->where('formCompletionDefaults.action', 'message')
            ->where('can.create', true));

    $this->actingAs($owner)
        ->post(route('forms.store'), [
            'name' => 'Client intake',
            'description' => "Collect a short summary\nand contact details.",
            'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
            'target_user_id' => $target->id,
            'is_active' => true,
            'style_settings' => [
                'container_width' => 'xl',
                'background_color' => '#FEF3C7',
                'border_color' => '#F59E0B',
                'text_color' => '#1F2937',
                'input_background_color' => '#FFFFFF',
                'input_border_color' => '#FCD34D',
                'button_background_color' => '#B45309',
                'button_text_color' => '#FFFFFF',
                'border_radius' => 30,
                'padding' => 40,
            ],
            'completion_settings' => [
                'action' => 'message',
                'success_message' => 'Спасибо! Мы получили вашу заявку и скоро ответим.',
                'redirect_url' => null,
            ],
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
        ->and($form->public_token)->not->toBe('')
        ->and($form->style_settings)->toMatchArray([
            'container_width' => 'xl',
            'background_color' => '#FEF3C7',
            'border_color' => '#F59E0B',
            'text_color' => '#1F2937',
            'input_background_color' => '#FFFFFF',
            'input_border_color' => '#FCD34D',
            'button_background_color' => '#B45309',
            'button_text_color' => '#FFFFFF',
            'border_radius' => 30,
            'padding' => 40,
        ])
        ->and($form->completion_settings)->toMatchArray([
            'action' => 'message',
            'success_message' => 'Спасибо! Мы получили вашу заявку и скоро ответим.',
            'redirect_url' => null,
        ]);

    expect($form->fields()->count())->toBe(2)
        ->and($form->fields()->pluck('label')->all())->toEqual(['Client name', 'Request details']);

    $this->actingAs($owner)
        ->get(route('forms.index', ['form' => $form->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('forms/Index')
            ->has('forms', 1)
            ->where('forms.0.name', 'Client intake')
            ->where('forms.0.public_url', route('forms.public.show', ['portalForm' => $form->public_token]))
            ->where('activeForm.id', $form->id)
            ->where('activeForm.name', 'Client intake')
            ->where('activeForm.style_settings.container_width', 'xl')
            ->where('activeForm.style_settings.border_radius', 30)
            ->where('activeForm.completion_settings.action', 'message')
            ->where('activeForm.completion_settings.success_message', 'Спасибо! Мы получили вашу заявку и скоро ответим.')
            ->has('activeForm.fields', 2)
            ->where('can.manageActive', true));
});

test('authenticated user can update and delete a form from forms workspace', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $nextTarget = User::factory()->create();

    $form = PortalForm::factory()->create([
        'owner_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'name' => 'Lead capture',
        'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
        'is_active' => true,
    ]);

    $form->fields()->createMany([
        [
            'key' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'placeholder' => 'Jane Doe',
            'is_required' => true,
            'sort_order' => 10,
        ],
        [
            'key' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'placeholder' => 'jane@example.com',
            'is_required' => true,
            'sort_order' => 20,
        ],
    ]);

    $fieldIds = $form->fields()->pluck('id')->values();

    $this->actingAs($owner)
        ->patch(route('forms.update', $form), [
            'name' => 'Updated lead capture',
            'description' => 'Updated form description',
            'submission_mode' => PortalForm::SUBMISSION_MODE_CHAT,
            'target_user_id' => $nextTarget->id,
            'is_active' => false,
            'style_settings' => [
                'container_width' => 'sm',
                'background_color' => '#ECFEFF',
                'border_color' => '#06B6D4',
                'text_color' => '#164E63',
                'input_background_color' => '#FFFFFF',
                'input_border_color' => '#67E8F9',
                'button_background_color' => '#0F766E',
                'button_text_color' => '#F0FDFA',
                'border_radius' => 18,
                'padding' => 24,
            ],
            'completion_settings' => [
                'action' => 'redirect',
                'success_message' => null,
                'redirect_url' => '/thank-you',
            ],
            'fields' => [
                [
                    'id' => $fieldIds[0],
                    'label' => 'Full name',
                    'type' => 'text',
                    'placeholder' => 'Jane Doe',
                    'is_required' => true,
                ],
                [
                    'label' => 'Project budget',
                    'type' => 'number',
                    'placeholder' => '1000',
                    'is_required' => false,
                ],
            ],
        ])
        ->assertRedirect(route('forms.index', ['form' => $form->id]));

    $form->refresh();

    expect($form->name)->toBe('Updated lead capture')
        ->and($form->description)->toBe('Updated form description')
        ->and($form->submission_mode)->toBe(PortalForm::SUBMISSION_MODE_CHAT)
        ->and($form->target_user_id)->toBe($nextTarget->id)
        ->and($form->is_active)->toBeFalse()
        ->and($form->style_settings)->toMatchArray([
            'container_width' => 'sm',
            'background_color' => '#ECFEFF',
            'border_color' => '#06B6D4',
            'text_color' => '#164E63',
            'input_background_color' => '#FFFFFF',
            'input_border_color' => '#67E8F9',
            'button_background_color' => '#0F766E',
            'button_text_color' => '#F0FDFA',
            'border_radius' => 18,
            'padding' => 24,
        ])
        ->and($form->completion_settings)->toMatchArray([
            'action' => 'redirect',
            'success_message' => null,
            'redirect_url' => '/thank-you',
        ])
        ->and($form->fields()->count())->toBe(2)
        ->and($form->fields()->pluck('label')->all())->toEqual(['Full name', 'Project budget']);

    $this->actingAs($owner)
        ->get(route('forms.index', ['form' => $form->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeForm.name', 'Updated lead capture')
            ->where('activeForm.is_active', false)
            ->where('activeForm.target_user.id', $nextTarget->id)
            ->where('activeForm.style_settings.container_width', 'sm')
            ->where('activeForm.style_settings.padding', 24)
            ->where('activeForm.completion_settings.action', 'redirect')
            ->where('activeForm.completion_settings.redirect_url', '/thank-you')
            ->has('activeForm.fields', 2));

    $this->actingAs($owner)
        ->delete(route('forms.destroy', $form))
        ->assertRedirect(route('forms.index'));

    expect(PortalForm::query()->find($form->id))->toBeNull();
});

test('redirect completion action requires a redirect url', function () {
    $owner = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('forms.store'), [
            'name' => 'Redirect form',
            'description' => 'Test form',
            'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
            'target_user_id' => $target->id,
            'is_active' => true,
            'completion_settings' => [
                'action' => 'redirect',
                'success_message' => null,
                'redirect_url' => null,
            ],
            'fields' => [
                [
                    'label' => 'Question',
                    'type' => 'text',
                    'placeholder' => 'Answer',
                    'is_required' => true,
                ],
            ],
        ])
        ->assertSessionHasErrors('completion_settings.redirect_url');
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
        'style_settings' => [
            'container_width' => 'md',
            'background_color' => '#FFF7ED',
            'border_color' => '#FDBA74',
            'text_color' => '#7C2D12',
            'input_background_color' => '#FFFFFF',
            'input_border_color' => '#FED7AA',
            'button_background_color' => '#C2410C',
            'button_text_color' => '#FFF7ED',
            'border_radius' => 28,
            'padding' => 36,
        ],
        'completion_settings' => [
            'action' => 'close',
            'success_message' => null,
            'redirect_url' => null,
        ],
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
            ->where('form.style_settings.container_width', 'md')
            ->where('form.style_settings.background_color', '#FFF7ED')
            ->where('form.completion_settings.action', 'close')
            ->has('form.fields', 2));

    $this->post(route('forms.public.submit', ['portalForm' => $form->public_token]), [
        'values' => [
            'company_name' => 'Acme Studio',
            'brief' => "Need a landing page\nwith two sections",
        ],
    ])->assertRedirect(route('forms.public.show', ['portalForm' => $form->public_token]));

    $task = ProjectTask::query()->latest('id')->first();
    $submission = PortalFormSubmission::query()->latest('id')->first();
    $notification = $target->refresh()->notifications()->latest('created_at')->first();

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

    expect($notification)->not->toBeNull()
        ->and($notification?->data['title'])->toBe(
            __('ui.notifications.task_assigned_title', [], $target->resolvedLanguage()),
        )
        ->and($notification?->data['message'])->toBe(
            __('ui.notifications.task_assigned_from_form_message', [
                'title' => $task?->title,
                'form' => $form->name,
            ], $target->resolvedLanguage()),
        )
        ->and($notification?->data['action_url'])->toBe(route('projects.workspace.tasks.show', $task));
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
        ->and($formsPage)->toContain('openForm(portalForm.id)')
        ->and($formsPage)->toContain('form.defaults(buildFormDefaults(activeForm));')
        ->and($formsPage)->toContain('handleEditorSheetOpenChange')
        ->and($formsPage)->toContain('<SheetContent')
        ->and($formsPage)->toContain('styleColorSections')
        ->and($formsPage)->toContain('deleteForm(portalForm.id)')
        ->and($formsPage)->toContain('showWorkspaceTask')
        ->and($publicPage)->toContain('submitPortalForm.url');
});
