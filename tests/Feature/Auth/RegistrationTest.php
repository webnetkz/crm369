<?php

use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register and remain pending administrator approval', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
        'language' => 'ru',
        'has_selected_language' => true,
    ]);
    $administrator = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);
    $secondAdministrator = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);
    $inactiveAdministrator = User::factory()->create([
        'user_group_id' => $administrators->id,
        'is_active' => false,
    ]);
    $restrictedGroup = UserGroup::factory()->create([
        'permissions' => [UserGroup::PERMISSION_VIEW_USERS],
    ]);
    $restrictedAdministrator = User::factory()->create([
        'user_group_id' => $restrictedGroup->id,
    ]);
    $regularUser = User::factory()->create();

    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertGuest();
    $response
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('status', __('ui.auth.registration_pending_approval'));

    expect($user->name)->toBe('Test User')
        ->and($user->is_active)->toBeFalse()
        ->and($user->deactivated_at)->toBeNull();

    Notification::assertSentTo(
        [$superAdmin, $administrator, $secondAdministrator],
        SystemNotification::class,
    );
    Notification::assertNotSentTo(
        [$inactiveAdministrator, $restrictedAdministrator, $regularUser, $user],
        SystemNotification::class,
    );
    Notification::assertCount(3);
    Notification::assertSentTo(
        $superAdmin,
        SystemNotification::class,
        function (SystemNotification $notification, array $channels) use ($superAdmin, $user): bool {
            $data = $notification->toArray($superAdmin);

            return $channels === ['database']
                && $data['title'] === 'Новая самостоятельная регистрация'
                && $data['message'] === 'Пользователь Test User (test@example.com) самостоятельно зарегистрировался и ожидает активации.'
                && $data['action_url'] === route('settings.users.index', [
                    'search' => $user->email,
                    'status' => 'inactive',
                ]);
        },
    );
});

test('registration screen includes password generator support', function () {
    $registerPage = file_get_contents(resource_path('js/pages/auth/Register.vue'));
    $passwordInput = file_get_contents(resource_path('js/components/PasswordInput.vue'));
    $generator = file_get_contents(resource_path('js/composables/usePasswordGenerator.ts'));

    expect($registerPage)->toContain('applyGeneratedPassword')
        ->and($registerPage)->toContain('useClipboard')
        ->and($registerPage)->toContain('canAutoSubmitRegistration')
        ->and($registerPage)->toContain('submit();')
        ->and($registerPage)->toContain('t.common.generate_password')
        ->and($registerPage)->toContain('v-model="form.password"')
        ->and($passwordInput)->toContain('modelValue')
        ->and($generator)->toContain('generatePassword');
});
