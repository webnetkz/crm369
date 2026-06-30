<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\FilterUsersIndexRequest;
use App\Http\Resources\ApiUserResource;
use App\Models\PortalWebhook;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookUserController extends Controller
{
    public function index(FilterUsersIndexRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $filters = $request->filters();

        $users = User::query()
            ->with('group:id,name')
            ->select([
                'id',
                'name',
                'last_name',
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
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => collect($users->items())
                ->map(fn (User $user): array => (new ApiUserResource($user))->resolve())
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
        ]);
    }

    public function show(Request $request, PortalWebhook $portalWebhook, User $user): JsonResponse
    {
        $user->load('group:id,name');

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => (new ApiUserResource($user))->resolve(),
        ]);
    }
}
