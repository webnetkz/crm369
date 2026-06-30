<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserGroupRequest;
use App\Http\Requests\Settings\UpdateUserGroupPermissionsRequest;
use App\Http\Resources\ApiUserGroupResource;
use App\Models\UserGroup;
use App\Support\PerPageOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', Rule::in(PerPageOptions::allowed())],
        ]);

        $perPage = PerPageOptions::resolve($request);
        $groups = UserGroup::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => ApiUserGroupResource::collection(collect($groups->items()))->resolve(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
            ],
            'per_page_options' => PerPageOptions::allowed(),
        ]);
    }

    public function store(StoreUserGroupRequest $request): JsonResponse
    {
        $group = UserGroup::create($request->validated());

        return response()->json([
            'message' => __('ui.admin.group_created_success'),
            'data' => (new ApiUserGroupResource($group->loadCount('users')))->resolve(),
        ], 201);
    }

    public function updatePermissions(UpdateUserGroupPermissionsRequest $request, UserGroup $userGroup): JsonResponse
    {
        $userGroup->update([
            'permissions' => $request->permissions(),
        ]);

        return response()->json([
            'message' => __('ui.admin.rights_updated_success'),
            'data' => (new ApiUserGroupResource($userGroup->fresh()->loadCount('users')))->resolve(),
        ]);
    }
}
