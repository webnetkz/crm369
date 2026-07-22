<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class NotifyAdministratorsOfNewRegistration
{
    public function __invoke(User $registeredUser): void
    {
        $superAdminEmail = Str::lower(trim((string) config('admin.super_admin_email')));

        User::query()
            ->with('group')
            ->where('is_active', true)
            ->whereKeyNot($registeredUser->getKey())
            ->where(function (Builder $query) use ($superAdminEmail): void {
                $query->whereHas('group');

                if ($superAdminEmail !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [$superAdminEmail]);
                }
            })
            ->get()
            ->filter(fn (User $administrator): bool => $administrator->canViewUsers()
                && $administrator->canManageUserActivation())
            ->each(function (User $administrator) use ($registeredUser): void {
                $locale = $administrator->resolvedLanguage();

                $administrator->notify(new SystemNotification(
                    title: Lang::get('ui.notifications.new_user_registration_title', [], $locale),
                    message: Lang::get('ui.notifications.new_user_registration_message', [
                        'name' => $registeredUser->name,
                        'email' => $registeredUser->email,
                    ], $locale),
                    actionUrl: route('settings.users.index', [
                        'search' => $registeredUser->email,
                        'status' => 'inactive',
                    ]),
                    actionLabel: Lang::get('ui.notifications.review_registered_user', [], $locale),
                ));
            });
    }
}
