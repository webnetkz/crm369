<?php

namespace App\Support;

use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Collection;

class FilePageData
{
    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return array<string, mixed>
     */
    public function build(
        User $user,
        Collection $directories,
        ?FileDirectory $activeDirectory,
        FileAccessManager $accessManager,
    ): array {
        $accessibleDirectories = $accessManager->accessibleDirectories($directories, $user);
        $activeChildren = $activeDirectory
            ? $accessibleDirectories
                ->where('parent_id', $activeDirectory->id)
                ->values()
            : collect();

        return [
            'tree' => $this->tree($user, $accessibleDirectories, $accessManager),
            'activeDirectory' => $activeDirectory
                ? $this->serializeActiveDirectory($user, $activeDirectory, $directories, $activeChildren, $accessManager)
                : null,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email', 'user_group_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $this->displayName($member),
                    'email' => $member->email,
                    'user_group_id' => $member->user_group_id,
                ])
                ->values()
                ->all(),
            'availableGroups' => UserGroup::query()
                ->orderBy('name')
                ->get()
                ->map(fn (UserGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ])
                ->values()
                ->all(),
            'can' => [
                'createRoot' => true,
            ],
        ];
    }

    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return array<int, array<string, mixed>>
     */
    private function tree(User $user, Collection $directories, FileAccessManager $accessManager, ?int $parentId = null): array
    {
        return $directories
            ->filter(function (FileDirectory $directory) use ($directories, $parentId, $user, $accessManager): bool {
                if ($parentId !== null) {
                    return $directory->parent_id === $parentId;
                }

                if ($directory->parent_id === null) {
                    return true;
                }

                $parent = $directories->firstWhere('id', $directory->parent_id);

                return ! $parent || ! $accessManager->canReadDirectory($user, $parent, $directories);
            })
            ->sortBy(['sort_order', 'name'])
            ->values()
            ->map(fn (FileDirectory $directory): array => $this->serializeTreeDirectory(
                $user,
                $directory,
                $directories,
                $accessManager,
            ))
            ->all();
    }

    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @param  Collection<int, FileDirectory>  $activeChildren
     * @return array<string, mixed>
     */
    private function serializeActiveDirectory(
        User $user,
        FileDirectory $directory,
        Collection $directories,
        Collection $activeChildren,
        FileAccessManager $accessManager,
    ): array {
        $breadcrumbs = $accessManager->accessibleBreadcrumbs($user, $directory, $directories);

        return [
            'id' => $directory->id,
            'name' => $directory->name,
            'parent_id' => $directory->parent_id,
            'owner' => $directory->owner ? [
                'id' => $directory->owner->id,
                'name' => $this->displayName($directory->owner),
                'email' => $directory->owner->email,
            ] : null,
            'permission_level' => $accessManager->directoryAccessLevel($user, $directory, $directories),
            'can_edit' => $accessManager->canEditDirectory($user, $directory, $directories),
            'breadcrumbs' => $breadcrumbs
                ->map(fn (FileDirectory $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->values()
                ->all(),
            'children' => $activeChildren
                ->sortBy(['sort_order', 'name'])
                ->values()
                ->map(fn (FileDirectory $child): array => $this->serializeTreeDirectory($user, $child, $directories, $accessManager))
                ->all(),
            'entries' => $directory->entries
                ->map(fn (FileEntry $entry): array => [
                    'id' => $entry->id,
                    'original_name' => $entry->original_name,
                    'mime_type' => $entry->mime_type,
                    'extension' => $entry->extension,
                    'size_bytes' => $entry->size_bytes,
                    'owner_name' => $entry->owner ? $this->displayName($entry->owner) : null,
                    'created_at' => $entry->created_at?->toISOString(),
                    'download_url' => route('files.entries.download', $entry),
                ])
                ->values()
                ->all(),
            'permissions' => $directory->permissions
                ->map(fn (FileDirectoryPermission $permission): array => [
                    'id' => $permission->id,
                    'access_level' => $permission->access_level,
                    'subject_type' => $permission->user_id !== null ? 'user' : 'group',
                    'subject_id' => $permission->user_id ?? $permission->user_group_id,
                    'subject_name' => $permission->user
                        ? $this->displayName($permission->user)
                        : ($permission->group?->displayName() ?? $permission->group?->name),
                    'granted_by_name' => $permission->grantedBy ? $this->displayName($permission->grantedBy) : null,
                    'created_at' => $permission->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'created_at' => $directory->created_at?->toISOString(),
            'updated_at' => $directory->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return array<string, mixed>
     */
    private function serializeTreeDirectory(
        User $user,
        FileDirectory $directory,
        Collection $directories,
        FileAccessManager $accessManager,
    ): array {
        $children = $directories
            ->where('parent_id', $directory->id)
            ->values();

        return [
            'id' => $directory->id,
            'name' => $directory->name,
            'parent_id' => $directory->parent_id,
            'can_edit' => $accessManager->canEditDirectory($user, $directory, $directories),
            'permission_level' => $accessManager->directoryAccessLevel($user, $directory, $directories),
            'children_count' => $directory->children_count ?? $children->count(),
            'files_count' => $directory->entries_count ?? 0,
            'permissions' => $directory->permissions
                ->map(fn (FileDirectoryPermission $permission): array => [
                    'id' => $permission->id,
                    'access_level' => $permission->access_level,
                    'subject_type' => $permission->user_id !== null ? 'user' : 'group',
                    'subject_id' => $permission->user_id ?? $permission->user_group_id,
                    'subject_name' => $permission->user
                        ? $this->displayName($permission->user)
                        : ($permission->group?->displayName() ?? $permission->group?->name),
                    'granted_by_name' => $permission->grantedBy ? $this->displayName($permission->grantedBy) : null,
                    'created_at' => $permission->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'children' => $this->tree($user, $directories, $accessManager, $directory->id),
        ];
    }

    private function displayName(User $user): string
    {
        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : $user->email;
    }
}
