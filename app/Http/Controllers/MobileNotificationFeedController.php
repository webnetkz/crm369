<?php

namespace App\Http\Controllers;

use App\Support\MobileNotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNotificationFeedController extends Controller
{
    public function __invoke(Request $request, MobileNotificationFeed $mobileNotificationFeed): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json(
            $mobileNotificationFeed->build($user),
        );
    }
}
