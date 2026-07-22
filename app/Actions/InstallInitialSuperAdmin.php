<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final class InstallInitialSuperAdmin
{
    public function execute(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            if (User::query()->exists()) {
                throw new LogicException('CRM369 already has a user account.');
            }

            $user = new User;

            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'language' => config('app.locale', 'ru'),
                'has_selected_language' => true,
                'user_group_id' => null,
                'is_active' => true,
                'deactivated_at' => null,
            ])->save();

            return $user;
        });
    }
}
