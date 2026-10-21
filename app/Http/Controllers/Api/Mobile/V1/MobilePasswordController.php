<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Actions\Security\RevokeUserMobileSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\MobileAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MobilePasswordController extends Controller
{
    /**
     * Update the user's password (the web security settings form).
     *
     * Like the web form, the password change signs the user out of the native application on every
     * other device. The session making the request stays signed in.
     */
    public function update(
        PasswordUpdateRequest $request,
        RevokeUserMobileSessions $revokeUserMobileSessions,
    ): JsonResponse {
        $user = $request->user();
        $mobileAccessToken = $request->attributes->get('mobile_access_token');

        if (! $mobileAccessToken instanceof MobileAccessToken) {
            $mobileAccessToken = MobileAccessToken::resolve($request->bearerToken());
        }

        abort_unless($user instanceof User, 401);
        abort_unless($mobileAccessToken instanceof MobileAccessToken, 401);
        abort_unless($mobileAccessToken->user_id === $user->id, 401);

        DB::transaction(function () use ($request, $user, $mobileAccessToken, $revokeUserMobileSessions): void {
            $user->update([
                'password' => $request->validated('password'),
            ]);

            $revokeUserMobileSessions($user, $mobileAccessToken);
        });

        return response()->json([
            'message' => __('Password updated.'),
        ]);
    }
}
