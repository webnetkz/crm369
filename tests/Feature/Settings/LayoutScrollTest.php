<?php

test('settings layout uses a single scroll container for the main content', function () {
    $sidebarProvider = file_get_contents(resource_path('js/components/ui/sidebar/SidebarProvider.vue'));
    $sidebarInset = file_get_contents(resource_path('js/components/ui/sidebar/SidebarInset.vue'));
    $sidebarContent = file_get_contents(resource_path('js/components/ui/sidebar/SidebarContent.vue'));
    $sidebarLayout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $appSidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($sidebarProvider)->toContain('flex h-svh w-full overflow-hidden')
        ->and($sidebarInset)->toContain('flex min-h-0 w-full flex-1 flex-col')
        ->and($sidebarContent)->toContain('flex min-h-0 flex-1 flex-col gap-2 overflow-hidden')
        ->and($sidebarContent)->not->toContain('overflow-auto')
        ->and($sidebarLayout)->toContain('class="min-h-0 overflow-x-hidden overflow-y-auto"')
        ->and($appSidebar)->toContain('<SidebarContent class="overflow-x-hidden overflow-y-auto">');
});

test('sidebar navigation does not render the platform group label', function () {
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));

    expect($navMain)->not->toContain('SidebarGroupLabel')
        ->and($navMain)->not->toContain('common.platform');
});

test('sidebar uses a distinct icon for user groups', function () {
    $appSidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($appSidebar)->toContain('icon: Users')
        ->and($appSidebar)->toContain('icon: Network');
});

test('main content layout adds bottom padding for the primary view', function () {
    $appContent = file_get_contents(resource_path('js/components/AppContent.vue'));

    expect($appContent)->toContain('class="flex flex-1 flex-col pb-8"')
        ->and($appContent)->toContain("['px-[10px] py-[5px]', className]")
        ->and($appContent)->toContain('rounded-xl px-[10px] py-[5px]')
        ->and($appContent)->toContain('rounded-xl');
});
