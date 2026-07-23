<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\RegisterDeviceRequest;
use App\Models\MobileAccessToken;
use App\Models\MobileDevice;
use Illuminate\Http\JsonResponse;

class MobileDeviceController extends Controller
{
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $accessToken = $request->attributes->get('mobile_access_token');

        if (! $accessToken instanceof MobileAccessToken) {
            $accessToken = MobileAccessToken::resolve($request->bearerToken());
        }

        abort_unless($accessToken instanceof MobileAccessToken, 401);
        abort_unless($accessToken->user_id === $request->user()->id, 401);
        abort_unless(hash_equals($accessToken->device_id, $request->deviceId()), 403);

        $tokenHash = hash('sha256', $request->fcmToken());

        MobileDevice::query()
            ->where('fcm_token_hash', $tokenHash)
            ->where(function ($query) use ($request): void {
                $query
                    ->where('user_id', '!=', $request->user()->id)
                    ->orWhere('device_id', '!=', $request->deviceId());
            })
            ->delete();

        $device = MobileDevice::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_id' => $request->deviceId(),
            ],
            [
                'platform' => 'android',
                'name' => $request->validated('device_name'),
                'app_version' => $request->validated('app_version'),
                'fcm_token' => $request->fcmToken(),
                'fcm_token_hash' => $tokenHash,
                'last_seen_at' => now(),
                'disabled_at' => null,
            ],
        );

        return response()->json([
            'message' => __('Push notifications are enabled for this device.'),
            'data' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'platform' => $device->platform,
                'registered_at' => $device->updated_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(RegisterDeviceRequest $request): JsonResponse
    {
        $accessToken = $request->attributes->get('mobile_access_token');

        if (! $accessToken instanceof MobileAccessToken) {
            $accessToken = MobileAccessToken::resolve($request->bearerToken());
        }

        abort_unless($accessToken instanceof MobileAccessToken, 401);
        abort_unless(hash_equals($accessToken->device_id, $request->deviceId()), 403);

        $request->user()->mobileDevices()
            ->where('device_id', $request->deviceId())
            ->update(['disabled_at' => now()]);

        return response()->json(['message' => __('Push notifications are disabled for this device.')]);
    }
}
