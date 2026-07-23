<?php

namespace App\Actions\Security;

use App\Models\User;

class RevokeUserMobileSessions
{
    public function __invoke(User $user): void
    {
        $user->mobileAccessTokens()->delete();
        $user->mobileDevices()->update(['disabled_at' => now()]);
    }
}
