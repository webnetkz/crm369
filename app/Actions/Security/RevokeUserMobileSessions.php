<?php

namespace App\Actions\Security;

use App\Models\MobileAccessToken;
use App\Models\User;

class RevokeUserMobileSessions
{
    /**
     * Sign the user out of the native application and disable push notifications on their devices.
     *
     * The session of the given access token (the device making the request) can be kept signed in.
     */
    public function __invoke(User $user, ?MobileAccessToken $exceptAccessToken = null): void
    {
        $mobileAccessTokens = $user->mobileAccessTokens();
        $mobileDevices = $user->mobileDevices();

        if ($exceptAccessToken instanceof MobileAccessToken) {
            $mobileAccessTokens->whereKeyNot($exceptAccessToken->getKey());
            $mobileDevices->where('device_id', '!=', $exceptAccessToken->device_id);
        }

        $mobileAccessTokens->delete();
        $mobileDevices->update(['disabled_at' => now()]);
    }
}
