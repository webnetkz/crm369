<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiUserResource;
use App\Models\PortalSetting;
use App\Models\User;
use App\Support\ChatSidebarData;
use App\Support\MobilePortalData;
use App\Support\NavigationMenuData;
use App\Support\UserAbilityData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class MobileBootstrapController extends Controller
{
    /**
     * Everything the native application needs to build its navigation for the signed in user.
     */
    public function show(
        Request $request,
        UserAbilityData $userAbilityData,
        NavigationMenuData $navigationMenuData,
        MobilePortalData $mobilePortalData,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $portal = PortalSetting::current();
        $abilities = $userAbilityData->build($user);
        $firebase = $mobilePortalData->firebaseClientOptions();

        return response()->json([
            'data' => [
                'user' => (new ApiUserResource($user->loadMissing('group')))->resolve(),
                'abilities' => $abilities,
                'portal' => [
                    'name' => $portal->companyName(),
                    'url' => rtrim((string) config('app.url'), '/'),
                    'logo_url' => url($portal->logoUrl()),
                    'default_language' => $portal->defaultLanguage(),
                    'enabled_modules' => $portal->enabledModules(),
                ],
                'menu' => $navigationMenuData->build($user),
                'counters' => [
                    'notifications' => $this->unreadNotificationsCount($user),
                    'chats' => $abilities['canAccessChats'] ? $this->unreadChatsCount($user) : 0,
                ],
                'push' => [
                    'enabled' => $firebase !== null,
                    'provider' => 'fcm',
                    'firebase' => $firebase,
                ],
                'server_time' => now()->toISOString(),
            ],
        ]);
    }

    private function unreadNotificationsCount(User $user): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    private function unreadChatsCount(User $user): int
    {
        if (
            ! Schema::hasTable('chat_conversations')
            || ! Schema::hasTable('chat_conversation_participants')
            || ! Schema::hasTable('chat_messages')
        ) {
            return 0;
        }

        return (int) (app(ChatSidebarData::class)->shared($user)['unreadCount'] ?? 0);
    }
}
