<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserGroupRequest;
use App\Models\UserGroup;
use App\Support\PaginationData;
use App\Support\PerPageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', \Illuminate\Validation\Rule::in(PerPageOptions::allowed())],
        ]);

        $perPage = PerPageOptions::resolve($request);
        $groups = UserGroup::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (UserGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'display_name' => $group->displayName(),
                'description' => $group->displayDescription(),
                'users_count' => $group->users_count,
                'created_at' => $group->created_at?->toISOString(),
            ]);

        return Inertia::render('settings/UserGroups', [
            'groups' => PaginationData::from($groups),
            'filters' => [
                'per_page' => $perPage,
            ],
            'perPageOptions' => PerPageOptions::allowed(),
        ]);
    }

    public function store(StoreUserGroupRequest $request): RedirectResponse
    {
        UserGroup::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.group_created_success')]);

        return back();
    }
}
