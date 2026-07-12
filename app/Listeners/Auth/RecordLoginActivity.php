<?php

namespace App\Listeners\Auth;

use App\Models\User;
use App\Models\UserLoginActivity;
use App\Support\UserAgentDetails;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecordLoginActivity
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! Schema::hasTable('user_login_activities')) {
            return;
        }

        $request = request();

        if (! $request instanceof Request) {
            return;
        }

        $userAgent = $this->normalizeUserAgent($request->userAgent());
        $device = UserAgentDetails::from($userAgent);
        $ipAddress = $this->normalizeIpAddress($request->ip());

        $isNewDevice = $userAgent !== null
            ? ! UserLoginActivity::query()
                ->whereBelongsTo($event->user)
                ->where('device_signature', $device->signature)
                ->exists()
            : false;

        $isNewIp = $ipAddress !== null
            ? ! UserLoginActivity::query()
                ->whereBelongsTo($event->user)
                ->where('ip_address', $ipAddress)
                ->exists()
            : false;

        UserLoginActivity::query()->create([
            'user_id' => $event->user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'browser' => $device->browser,
            'platform' => $device->platform,
            'device_type' => $device->deviceType,
            'device_signature' => $device->signature,
            'is_new_device' => $isNewDevice,
            'is_new_ip' => $isNewIp,
            'logged_in_at' => now(),
        ]);
    }

    private function normalizeIpAddress(?string $ipAddress): ?string
    {
        if (! is_string($ipAddress)) {
            return null;
        }

        $ipAddress = trim($ipAddress);

        return $ipAddress !== '' ? $ipAddress : null;
    }

    private function normalizeUserAgent(?string $userAgent): ?string
    {
        if (! is_string($userAgent)) {
            return null;
        }

        $userAgent = Str::of($userAgent)
            ->squish()
            ->limit(1000, '')
            ->value();

        return $userAgent !== '' ? $userAgent : null;
    }
}
