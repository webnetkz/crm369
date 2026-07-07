<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateUserGroupPermissionsRequest;
use App\Models\UserGroup;
use App\Support\PaginationData;
use App\Support\PerPageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserGroupPermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', Rule::in(PerPageOptions::allowed())],
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
                'permissions' => $group->resolvedPermissions(),
            ]);

        return Inertia::render('settings/Rights', [
            'groups' => PaginationData::from($groups),
            'availablePermissions' => collect(UserGroup::permissionDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                    'module_key' => $definition['module_key'],
                ])
                ->values(),
            'permissionGroups' => collect(UserGroup::permissionModuleDefinitions())
                ->map(function (array $moduleDefinition, string $moduleKey): array {
                    $permissions = collect(UserGroup::permissionDefinitions())
                        ->filter(fn (array $definition): bool => $definition['module_key'] === $moduleKey)
                        ->map(fn (array $definition, string $key): array => [
                            'key' => $key,
                            'label' => __($definition['label_key']),
                            'description' => __($definition['description_key']),
                        ])
                        ->values()
                        ->all();

                    return [
                        'key' => $moduleKey,
                        'label' => __($moduleDefinition['title_key']),
                        'description' => __($moduleDefinition['description_key']),
                        'permissions' => $permissions,
                    ];
                })
                ->filter(fn (array $group): bool => $group['permissions'] !== [])
                ->values(),
            'configurableModules' => UserGroup::configurableAccessModules(),
            'filters' => [
                'per_page' => $perPage,
            ],
            'perPageOptions' => PerPageOptions::allowed(),
        ]);
    }

    public function update(UpdateUserGroupPermissionsRequest $request, UserGroup $userGroup): RedirectResponse
    {
        $userGroup->update([
            'permissions' => $request->permissionPayload(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.admin.rights_updated_success')]);

        return back();
    }
}
