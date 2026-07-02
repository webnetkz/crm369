<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMessengerIntegrationRequest;
use App\Http\Resources\ApiMessengerIntegrationResource;
use App\Models\MessengerIntegration;
use App\Models\MessengerIntegrationGroupAccess;
use App\Models\UserGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MessengerIntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        MessengerIntegration::ensureDefaultIntegrationsExist();

        $groups = UserGroup::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $integrations = MessengerIntegration::query()
            ->with('groupAccesses')
            ->orderByRaw("case driver when 'whatsapp_business' then 1 when 'telegram' then 2 when 'telephony' then 3 else 999 end")
            ->get();

        return response()->json([
            'data' => $integrations
                ->map(function (MessengerIntegration $integration) use ($groups): array {
                    return [
                        ...(new ApiMessengerIntegrationResource($integration))->resolve(),
                        'description' => ($definition = MessengerIntegration::driverDefinitions()[$integration->driver] ?? null)
                            ? __($definition['description_key'])
                            : null,
                        'group_accesses' => $groups
                            ->map(fn (UserGroup $group): array => [
                                'user_group_id' => $group->id,
                                'access_level' => $integration->groupAccesses
                                    ->firstWhere('user_group_id', $group->id)?->access_level
                                    ?? MessengerIntegration::ACCESS_NONE,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
            'groups' => $groups
                ->map(fn (UserGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                    'description' => $group->displayDescription(),
                    'users_count' => $group->users_count,
                ])
                ->values()
                ->all(),
            'access_levels' => collect(MessengerIntegrationGroupAccess::accessDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values()
                ->all(),
            'super_admin_access_level' => MessengerIntegration::ACCESS_FULL,
        ]);
    }

    public function update(UpdateMessengerIntegrationRequest $request, MessengerIntegration $messengerIntegration): JsonResponse
    {
        DB::transaction(function () use ($request, $messengerIntegration): void {
            $messengerIntegration->update([
                'name' => $request->validated('name'),
                'is_active' => (bool) $request->validated('is_active'),
                'settings' => $request->settings($messengerIntegration),
                'updated_by_user_id' => $request->user()?->id,
            ]);

            $groupAccesses = $request->groupAccesses();
            $groupIds = collect($groupAccesses)->pluck('user_group_id')->all();

            if ($groupIds === []) {
                $messengerIntegration->groupAccesses()->delete();

                return;
            }

            $messengerIntegration->groupAccesses()
                ->whereNotIn('user_group_id', $groupIds)
                ->delete();

            foreach ($groupAccesses as $groupAccess) {
                if ($groupAccess['access_level'] === MessengerIntegration::ACCESS_NONE) {
                    $messengerIntegration->groupAccesses()
                        ->where('user_group_id', $groupAccess['user_group_id'])
                        ->delete();

                    continue;
                }

                $messengerIntegration->groupAccesses()->updateOrCreate(
                    ['user_group_id' => $groupAccess['user_group_id']],
                    ['access_level' => $groupAccess['access_level']],
                );
            }
        });

        return response()->json([
            'message' => __('ui.integrations.updated_success'),
            'data' => (new ApiMessengerIntegrationResource($messengerIntegration->fresh('groupAccesses')))->resolve(),
        ]);
    }
}
