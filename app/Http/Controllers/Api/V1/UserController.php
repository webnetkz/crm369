<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FilterUsersIndexRequest;
use App\Http\Requests\Settings\ResetManagedUserPasswordRequest;
use App\Http\Requests\Settings\StoreManagedUserRequest;
use App\Http\Requests\Settings\UpdateManagedUserProfileRequest;
use App\Http\Requests\Settings\UpdateUserActivationRequest;
use App\Http\Requests\Settings\UpdateUserGroupMembershipRequest;
use App\Http\Resources\ApiUserResource;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\SystemNotification;
use App\Support\PerPageOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

class UserController extends Controller
{
    public function index(FilterUsersIndexRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $canManageUsers = $viewer?->can('manage-users') ?? false;
        $filters = $request->filters();

        $users = User::query()
            ->with('group:id,name')
            ->select([
                'id',
                'name',
                'last_name',
                'middle_name',
                'email',
                'phone',
                'email_verified_at',
                'avatar_path',
                'avatar_scale',
                'avatar_position_x',
                'avatar_position_y',
                'language',
                'has_selected_language',
                'background_color',
                'background_image_path',
                'background_blur',
                'user_group_id',
                'is_active',
                'deactivated_at',
                'created_at',
                'updated_at',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['group'] === 'none', fn ($query) => $query->whereNull('user_group_id'))
            ->when(
                $filters['group'] !== '' && $filters['group'] !== 'none',
                fn ($query) => $query->where('user_group_id', (int) $filters['group']),
            )
            ->when($filters['registered_from'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['registered_from']))
            ->when($filters['registered_to'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['registered_to']))
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return response()->json([
            'data' => collect($users->items())
                ->map(fn (User $user): array => [
                    ...(new ApiUserResource($user))->resolve(),
                    'can_be_impersonated' => $user->canBeImpersonatedBy($viewer),
                ])
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => $filters,
            'groups' => $canManageUsers
                ? UserGroup::query()
                    ->select(['id', 'name'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (UserGroup $group): array => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'display_name' => $group->displayName(),
                    ])
                    ->values()
                    ->all()
                : [],
            'per_page_options' => PerPageOptions::allowed(),
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load('group:id,name');

        return response()->json([
            'data' => [
                ...(new ApiUserResource($user))->resolve(),
                'can_be_impersonated' => $user->canBeImpersonatedBy($request->user()),
            ],
        ]);
    }

    public function store(StoreManagedUserRequest $request): JsonResponse
    {
        $user = new User([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'language' => PortalSetting::current()->defaultLanguage(),
            'has_selected_language' => false,
        ]);

        $user->email_verified_at = $request->emailVerified() ? now() : null;
        $user->save();
        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.account_created_title',
            'ui.notifications.account_created_message',
            route('profile.edit'),
            'ui.notifications.open_profile',
        ));

        return response()->json([
            'message' => __('ui.admin.user_created_success'),
            'data' => (new ApiUserResource($user->fresh('group')))->resolve(),
        ], 201);
    }

    public function updateProfile(UpdateManagedUserProfileRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $user->fill(Arr::only($validated, ['name', 'last_name', 'middle_name', 'email', 'phone', 'position', 'manager_id']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => __('Profile updated.'),
            'data' => (new ApiUserResource($user->fresh('group')))->resolve(),
        ]);
    }

    public function updateActivation(UpdateUserActivationRequest $request, User $user): JsonResponse
    {
        if ($user->is($request->user()) || $user->isSuperAdmin()) {
            return response()->json([
                'message' => __('ui.admin.user_activation_denied'),
            ], 422);
        }

        $isActive = $request->isActive();

        $user->update([
            'is_active' => $isActive,
            'deactivated_at' => $isActive ? null : now(),
        ]);

        return response()->json([
            'message' => $isActive
                ? __('ui.admin.user_activated_success')
                : __('ui.admin.user_deactivated_success'),
            'data' => (new ApiUserResource($user->fresh('group')))->resolve(),
        ]);
    }

    public function resetPassword(ResetManagedUserPasswordRequest $request, User $user): JsonResponse
    {
        if ($user->is($request->user()) || ($user->isSuperAdmin() && ! $request->user()?->isSuperAdmin())) {
            return response()->json([
                'message' => __('ui.admin.password_reset_denied'),
            ], 422);
        }

        $user->update([
            'password' => $request->validated('password'),
        ]);
        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.password_reset_title',
            'ui.notifications.password_reset_message',
            route('security.edit'),
            'ui.notifications.open_security',
        ));

        return response()->json([
            'message' => __('ui.admin.password_reset_success'),
            'data' => (new ApiUserResource($user->fresh('group')))->resolve(),
        ]);
    }

    public function updateGroup(UpdateUserGroupMembershipRequest $request, User $user): JsonResponse
    {
        $groupId = $request->userGroupId();

        $user->update([
            'user_group_id' => $groupId,
        ]);

        $user->notify($this->systemNotification(
            $user,
            'ui.notifications.group_updated_title',
            'ui.notifications.group_updated_message',
            route('profile.edit'),
            'ui.notifications.open_profile',
            ['group' => $this->groupNameForLocale($groupId, $user->resolvedLanguage())],
        ));

        return response()->json([
            'message' => __('ui.admin.group_updated_success'),
            'data' => (new ApiUserResource($user->fresh('group')))->resolve(),
        ]);
    }

    private function systemNotification(
        User $user,
        string $titleKey,
        string $messageKey,
        ?string $actionUrl = null,
        ?string $actionLabelKey = null,
        array $replace = [],
    ): SystemNotification {
        return new SystemNotification(
            title: Lang::get($titleKey, $replace, $user->resolvedLanguage()),
            message: Lang::get($messageKey, $replace, $user->resolvedLanguage()),
            actionUrl: $actionUrl,
            actionLabel: $actionLabelKey ? Lang::get($actionLabelKey, [], $user->resolvedLanguage()) : null,
        );
    }

    private function groupNameForLocale(?int $groupId, string $locale): string
    {
        if ($groupId === null) {
            return Lang::get('ui.admin.simple_user', [], $locale);
        }

        $group = UserGroup::query()->find($groupId);

        if (! $group) {
            return Lang::get('ui.admin.simple_user', [], $locale);
        }

        return $group->name === UserGroup::ADMINISTRATORS_NAME
            ? Lang::get('ui.admin.administrators_group', [], $locale)
            : $group->name;
    }
}
