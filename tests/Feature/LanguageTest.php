<?php

use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('users can update their language preference', function () {
    $user = User::factory()->create([
        'language' => 'ru',
        'has_selected_language' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('language.update'), [
            'language' => 'en',
        ]);

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertCookie('language', 'en');

    expect($user->refresh()->language)->toBe('en')
        ->and($user->has_selected_language)->toBeTrue();
});

test('unverified users can update their language preference from profile settings', function () {
    $user = User::factory()->unverified()->create([
        'language' => 'ru',
        'has_selected_language' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('language.update'), [
            'language' => 'en',
        ]);

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertCookie('language', 'en');

    expect($user->refresh()->language)->toBe('en')
        ->and($user->has_selected_language)->toBeTrue();
});

test('language preference is shared with inertia pages', function () {
    $user = User::factory()->create([
        'language' => 'en',
        'has_selected_language' => true,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en')
            ->where('locale.messages.common.settings', 'Settings')
            ->where('locale.messages.common.choose_file', 'Choose file')
            ->where('locale.messages.common.last_name', 'Last name')
            ->where('locale.messages.common.middle_name', 'Middle name')
            ->where('locale.messages.common.no_file_selected', 'No file selected')
            ->where('locale.messages.common.not_specified', 'Not specified')
            ->where('locale.messages.common.phone', 'Phone number')
            ->where('locale.messages.admin.profile_description', 'Full information about the selected user.')
            ->where('locale.messages.settings.rights', 'Rights')
            ->where('locale.messages.admin.permission_impersonate_users', 'Sign in as users')
            ->where('locale.messages.notifications.panel_title', 'Notifications')
            ->where('locale.messages.notifications.mark_all_as_read', 'Mark all as read')
            ->where('locale.messages.documentation.title', 'Documentation')
            ->where('locale.messages.profile.update_profile', 'Profile')
            ->where('locale.messages.profile.phone_placeholder', '+7 777 123 45 67')
            ->where('locale.messages.profile.update_profile_description', 'Update your name and email address')
            ->where('locale.messages.common.name', 'Name')
            ->where('locale.messages.common.email', 'Email')
            ->where('locale.messages.security.current_password', 'Current password')
            ->where('locale.messages.passkeys.title', 'Passkeys')
            ->where('locale.messages.two_factor.title', 'Two-factor authentication'),
        );

    $serializedMessages = json_encode(
        $response->inertiaProps('locale.messages'),
        JSON_THROW_ON_ERROR,
    );

    expect(strlen($serializedMessages))->toBeLessThan(200_000);
});

test('shared authorization data resolves the user group once per request', function () {
    $group = UserGroup::factory()->create();
    $user = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    DB::connection()->flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertSuccessful();

    $groupQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(
            $query['query'],
            'select * from "user_groups" where',
        ));

    DB::disableQueryLog();

    expect($groupQueries)->toHaveCount(1);
});

test('portal default language is used for guests without a language cookie', function () {
    PortalSetting::query()->create([
        'id' => 1,
        'default_language' => 'en',
    ]);

    $this
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en')
            ->where('locale.messages.auth.login_title', 'Log in to your account'),
        );
});

test('portal default language is used for authenticated users without an explicit language choice', function () {
    PortalSetting::query()->create([
        'id' => 1,
        'default_language' => 'en',
    ]);

    $user = User::factory()->create([
        'language' => 'ru',
        'has_selected_language' => false,
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en')
            ->where('auth.user.language', 'en')
            ->where('auth.user.has_selected_language', false),
        );
});

test('explicit user language overrides the portal default language', function () {
    PortalSetting::query()->create([
        'id' => 1,
        'default_language' => 'ru',
    ]);

    $user = User::factory()->create([
        'language' => 'en',
        'has_selected_language' => true,
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en')
            ->where('auth.user.language', 'en')
            ->where('auth.user.has_selected_language', true),
        );
});

test('russian appearance settings translations are shared with inertia pages', function () {
    PortalSetting::query()->updateOrCreate(
        ['id' => 1],
        ['default_language' => 'ru'],
    );

    $user = User::factory()->create([
        'language' => 'ru',
        'has_selected_language' => true,
    ]);

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'ru')
            ->where('locale.messages.settings.appearance_settings', 'Настройки внешнего вида')
            ->where('locale.messages.common.choose_file', 'Выберите файл')
            ->where('locale.messages.common.last_name', 'Фамилия')
            ->where('locale.messages.common.middle_name', 'Отчество')
            ->where('locale.messages.common.not_specified', 'Не указано')
            ->where('locale.messages.common.phone', 'Номер телефона')
            ->where('locale.messages.admin.profile_description', 'Полная информация о выбранном пользователе.')
            ->where('locale.messages.settings.rights', 'Права')
            ->where('locale.messages.admin.permission_impersonate_users', 'Авторизация от имени пользователей')
            ->where('locale.messages.notifications.panel_title', 'Уведомления')
            ->where('locale.messages.notifications.mark_all_as_read', 'Отметить все как прочитанные')
            ->where('locale.messages.documentation.title', 'Документация')
            ->where('locale.messages.settings.light', 'Светлая')
            ->where('locale.messages.settings.dark', 'Темная')
            ->where('locale.messages.settings.system', 'Системная'),
        );
});

test('settings pages use localized file picker component', function () {
    $pages = [
        resource_path('js/pages/settings/Profile.vue'),
        resource_path('js/pages/settings/Portal.vue'),
        resource_path('js/pages/settings/Appearance.vue'),
    ];

    foreach ($pages as $page) {
        expect(file_get_contents($page))
            ->toContain('LocalizedFilePicker')
            ->not
            ->toContain('type="file"');
    }

    $component = file_get_contents(resource_path('js/components/LocalizedFilePicker.vue'));

    expect($component)
        ->toContain('t.common.choose_file')
        ->toContain('no_file_selected')
        ->toContain('type="file"');
});

test('passkey email separator uses localized message', function () {
    $component = file_get_contents(resource_path('js/components/PasskeyVerify.vue'));
    $russianTranslations = require lang_path('ru/ui/passkeys.php');
    $englishTranslations = require lang_path('en/ui/passkeys.php');

    expect($component)
        ->toContain('t.passkeys.continue_with_email')
        ->not->toContain("'Or continue with email'")
        ->and($russianTranslations['continue_with_email'])
        ->toBe('Или продолжить с помощью электронной почты')
        ->and($englishTranslations['continue_with_email'])
        ->toBe('Or continue with email');
});

test('language tabs are rendered on profile page instead of appearance page', function () {
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));
    $appearancePage = file_get_contents(resource_path('js/pages/settings/Appearance.vue'));

    expect($profilePage)->toContain('LanguageTabs')
        ->and($appearancePage)->not->toContain('LanguageTabs');
});

test('guest language cookie is shared with inertia pages', function () {
    $this
        ->withCookie('language', 'en')
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale.current', 'en')
            ->where('locale.messages.auth.login_title', 'Log in to your account'),
        );
});

test('vue source does not contain hardcoded russian interface text', function () {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['ts', 'vue'], true)) {
            continue;
        }

        expect(file_get_contents($file->getPathname()))
            ->not
            ->toMatch('/[А-Яа-яЁё]/u');
    }
});
