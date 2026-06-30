<?php

use App\Models\User;
use App\Notifications\SystemNotification;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users receive notifications and unread count in shared props', function () {
    $user = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'Profile updated',
        message: 'Your profile was updated successfully.',
        actionUrl: route('profile.edit'),
        actionLabel: 'Open profile',
    ));

    $user->notify(new SystemNotification(
        title: 'Security notice',
        message: 'Review your security settings.',
        actionUrl: route('security.edit'),
        actionLabel: 'Open security',
    ));

    $response = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 2)
            ->has('notifications.items', 2)
        );

    $items = collect($response->inertiaProps('notifications.items'));

    expect($items->pluck('title')->all())->toContain('Profile updated', 'Security notice')
        ->and($items->every(fn (array $item): bool => array_key_exists('isRead', $item)))->toBeTrue();
});

test('authenticated users can open the notifications page', function () {
    $user = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'Profile updated',
        message: 'Your profile was updated successfully.',
        actionUrl: route('profile.edit'),
        actionLabel: 'Open profile',
    ));

    $user->notify(new SystemNotification(
        title: 'Security notice',
        message: 'Review your security settings.',
        actionUrl: route('security.edit'),
        actionLabel: 'Open security',
    ));

    $this->actingAs($user)
        ->get(route('notifications.index', [
            'status' => 'unread',
            'per_page' => 50,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->where('filters.status', 'unread')
            ->where('filters.per_page', 50)
            ->where('notifications.unreadCount', 2)
            ->has('notificationFeed.data', 2)
            ->where('notificationFeed.meta.per_page', 50)
        );
});

test('users can mark one of their notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'Profile updated',
        message: 'Your profile was updated successfully.',
    ));

    $notification = $user->notifications()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('notifications.read.update', $notification->id))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull();
});

test('users can mark all notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'First',
        message: 'First message.',
    ));

    $user->notify(new SystemNotification(
        title: 'Second',
        message: 'Second message.',
    ));

    $this->actingAs($user)
        ->patch(route('notifications.read-all.update'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('users cannot mark another user notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUser->notify(new SystemNotification(
        title: 'Private notice',
        message: 'Only for another user.',
    ));

    $notification = $otherUser->notifications()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('notifications.read.update', $notification->id))
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

test('notification ui renders bell trigger in both header variants and sidebar page access', function () {
    $header = file_get_contents(resource_path('js/components/AppHeader.vue'));
    $sidebarHeader = file_get_contents(resource_path('js/components/AppSidebarHeader.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $sheet = file_get_contents(resource_path('js/components/NotificationCenterSheet.vue'));

    expect($header)->toContain('NotificationCenterSheet')
        ->and($sidebarHeader)->toContain('NotificationCenterSheet')
        ->and($sidebar)->toContain('notificationsIndex()')
        ->and($sheet)->toContain('<Bell')
        ->and($sheet)->toContain('SheetContent side="right"')
        ->and($sheet)->toContain('notifications.unreadCount')
        ->and($sheet)->toContain('markAllAsRead')
        ->and($sheet)->toContain('@mouseenter="markAsRead(notification)"')
        ->and($sheet)->toContain('optimisticallyReadNotificationIds')
        ->and($sheet)->toContain('open_page');
});
