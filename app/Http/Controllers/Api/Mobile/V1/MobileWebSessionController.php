<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileAccessToken;
use App\Models\User;
use App\Support\MobileWebSessionBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWebSessionController extends Controller
{
    /**
     * Issue a single-use link that opens a web page of the portal inside the
     * native application's browser with the current user signed in.
     */
    public function store(Request $request, MobileWebSessionBridge $mobileWebSessionBridge): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $mobileAccessToken = $request->attributes->get('mobile_access_token');

        abort_unless($user instanceof User, 401);
        abort_unless($mobileAccessToken instanceof MobileAccessToken, 401);

        $path = MobileWebSessionBridge::normalizePath($validated['path'] ?? null);
        $issued = $mobileWebSessionBridge->issue($user, $mobileAccessToken, $path);

        return response()->json([
            'data' => [
                'url' => route('mobile.web-session.consume', ['token' => $issued['token']]),
                'path' => $path,
                'expires_in' => $issued['expires_in'],
            ],
        ], 201);
    }
}
