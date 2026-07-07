<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('appearance page is displayed with current background settings', function () {
    $path = 'backgrounds/preview/background.jpg';

    $user = User::factory()->create([
        'background_color' => '#112233',
        'background_image_path' => $path,
        'background_blur' => 64,
    ]);

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Appearance')
            ->where('settings.background_color', '#112233')
            ->where('settings.background_image_url', Storage::disk('public')->url($path))
            ->where('settings.background_blur', 64),
        );
});

test('appearance page seeds the root html background with the user custom color', function () {
    $user = User::factory()->create([
        'background_color' => '#112233',
        'background_blur' => 0,
    ]);

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('#112233', false);
});

test('appearance defaults to light when no preference cookie is present', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee("const appearance = 'light';", false)
        ->assertDontSee("const appearance = 'system';", false);
});

test('authenticated layout receives background settings through shared auth props', function () {
    $path = 'backgrounds/preview/dashboard-background.jpg';

    $user = User::factory()->create([
        'background_color' => '#112233',
        'background_image_path' => $path,
        'background_blur' => 64,
    ]);

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.background_color', '#112233')
            ->where('auth.user.background_image', Storage::disk('public')->url($path))
            ->where('auth.user.background_blur', 64)
        );
});

test('users can update their background appearance settings', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('appearance.update'), [
            'background_color' => '#123456',
            'background_blur' => 72,
            'background_image' => UploadedFile::fake()->image('background.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('appearance.edit'));

    $user->refresh();

    Storage::disk('public')->assertExists($user->background_image_path);

    expect($user->background_color)->toBe('#123456')
        ->and($user->background_blur)->toBe(72)
        ->and($user->background_image_path)->toStartWith('backgrounds/'.$user->id.'/')
        ->and($user->background_image)->not->toBeNull();
});

test('users can remove their background image', function () {
    Storage::fake('public');

    $path = 'backgrounds/5/background.jpg';
    Storage::disk('public')->put($path, 'background');

    $user = User::factory()->create([
        'background_color' => '#654321',
        'background_image_path' => $path,
        'background_blur' => 48,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('appearance.update'), [
            'background_color' => '#654321',
            'background_blur' => 0,
            'remove_background_image' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('appearance.edit'));

    $user->refresh();

    Storage::disk('public')->assertMissing($path);

    expect($user->background_image_path)->toBeNull()
        ->and($user->background_image)->toBeNull()
        ->and($user->background_blur)->toBe(0);
});

test('portal chrome keeps opaque headers while a custom portal background is active', function () {
    $appHeader = file_get_contents(resource_path('js/components/AppHeader.vue'));
    $appContent = file_get_contents(resource_path('js/components/AppContent.vue'));
    $sidebarProvider = file_get_contents(resource_path('js/components/ui/sidebar/SidebarProvider.vue'));
    $sidebarInset = file_get_contents(resource_path('js/components/ui/sidebar/SidebarInset.vue'));
    $sidebar = file_get_contents(resource_path('js/components/ui/sidebar/Sidebar.vue'));
    $appSidebarHeader = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $appShell = file_get_contents(resource_path('js/components/AppShell.vue'));

    expect($appContent)->toContain(':style="contentStyle"')
        ->and($appContent)->toContain("backgroundColor: 'var(--app-shell-content-background, var(--background))'")
        ->and($appContent)->toContain("backdropFilter: 'var(--app-shell-backdrop-filter, none)'")
        ->and($sidebarProvider)->toContain("backgroundColor: 'var(--app-shell-wrapper-background, var(--sidebar-background))'")
        ->and($sidebarProvider)->toContain('group-data-[has-background=true]/app-shell:bg-transparent')
        ->and($sidebarInset)->toContain('group-data-[has-background=true]/app-shell:bg-transparent')
        ->and($sidebarInset)->toContain('group-data-[has-background=true]/app-shell:supports-[backdrop-filter]:bg-background/18')
        ->and($sidebar)->toContain("backgroundColor: 'var(--app-shell-sidebar-background, var(--sidebar-background))'")
        ->and($sidebar)->toContain("backdropFilter: 'var(--app-shell-backdrop-filter, none)'")
        ->and($appHeader)->toContain('var(--app-shell-surface-background, var(--background))')
        ->and($appHeader)->toContain("backdropFilter: 'var(--app-shell-backdrop-filter, none)'")
        ->and($appHeader)->toContain('group-data-[has-background=true]/app-shell:bg-transparent')
        ->and($appSidebarHeader)->toContain('var(--app-shell-surface-background, var(--background))')
        ->and($appSidebarHeader)->toContain("backdropFilter: 'var(--app-shell-backdrop-filter, none)'")
        ->and($appShell)->toContain('bg-white/10 dark:bg-black/18')
        ->and($appShell)->toContain("'--app-shell-wrapper-background': hasCustomBackground.value")
        ->and($appShell)->toContain("'--app-shell-content-background': hasCustomBackground.value")
        ->and($appShell)->toContain("'--app-shell-surface-background': 'var(--background)'")
        ->and($appShell)->toContain("'--app-shell-sidebar-background': hasCustomBackground.value")
        ->and($appShell)->toContain("'--app-shell-backdrop-filter': hasCustomBackground.value")
        ->and($appShell)->toContain('backgroundImage: `url("${backgroundSettings.value.image}")`,')
        ->and($appShell)->toContain('filter: `blur(${Math.round(backgroundSettings.value.blur * 0.45)}px)`,');
});

test('appearance editor syncs live background previews with the app shell', function () {
    $appearancePage = file_get_contents(resource_path('js/pages/settings/Appearance.vue'));
    $appShell = file_get_contents(resource_path('js/components/AppShell.vue'));
    $backgroundPreview = file_get_contents(resource_path('js/composables/useBackgroundPreview.ts'));
    $appearanceComposable = file_get_contents(resource_path('js/composables/useAppearance.ts'));

    expect($appearancePage)->toContain('useBackgroundPreview')
        ->and($appearancePage)->toContain('setPersisted({')
        ->and($appearancePage)->toContain('{ deep: true, immediate: true }')
        ->and($appearancePage)->toContain('setPreview({')
        ->and($appearancePage)->toContain('clearPreview();')
        ->and($appShell)->toContain('useBackgroundPreview')
        ->and($appShell)->toContain('setPersisted(value);')
        ->and($appShell)->toContain('preview.value ?? persisted.value ?? authBackgroundSettings.value')
        ->and($appShell)->toContain("backgroundColor: backgroundSettings.value.color ?? 'var(--background)'")
        ->and($appShell)->toContain('document.documentElement.style.backgroundColor = backgroundColor;')
        ->and($appShell)->toContain('document.body.style.backgroundColor = backgroundColor;')
        ->and($backgroundPreview)->toContain('export function useBackgroundPreview')
        ->and($backgroundPreview)->toContain('const persisted = computed<BackgroundPreviewSettings | null>(() => {')
        ->and($backgroundPreview)->toContain('const setPersisted = (value: BackgroundPreviewSettings | null): void => {')
        ->and($backgroundPreview)->toContain('isPreviewActive')
        ->and($appearanceComposable)->toContain("const defaultAppearance: Appearance = 'light';")
        ->and($appearanceComposable)->toContain('updateTheme(savedAppearance || defaultAppearance);')
        ->and($appearanceComposable)->toContain('const appearance = ref<Appearance>(defaultAppearance);');
});

test('app sidebar header and settings tabs stay pinned while their scroll container moves', function () {
    $appSidebarHeader = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $settingsLayout = file_get_contents(resource_path('js/layouts/settings/Layout.vue'));

    expect($appSidebarHeader)->toContain('sticky top-0 z-30')
        ->and($appSidebarHeader)->toContain('[--app-sidebar-header-height:4rem]')
        ->and($appSidebarHeader)->toContain('group-has-data-[collapsible=icon]/sidebar-wrapper:[--app-sidebar-header-height:3rem]')
        ->and($settingsLayout)->toContain('sticky top-[var(--app-sidebar-header-height,4rem)] z-20')
        ->and($settingsLayout)->toContain('var(--app-shell-surface-background, var(--background))')
        ->and($settingsLayout)->toContain("backdropFilter: 'var(--app-shell-backdrop-filter, none)'");
});
