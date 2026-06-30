<?php

namespace App\Support;

use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Support\Collection;

class FileAccessManager
{
    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return Collection<int, FileDirectory>
     */
    public function accessibleDirectories(Collection $directories, User $user): Collection
    {
        return $directories
            ->filter(fn (FileDirectory $directory): bool => $this->canReadDirectory($user, $directory, $directories))
            ->values();
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    public function canReadDirectory(User $user, FileDirectory $directory, ?Collection $directories = null): bool
    {
        return $this->directoryAccessLevel($user, $directory, $directories) !== null;
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    public function canEditDirectory(User $user, FileDirectory $directory, ?Collection $directories = null): bool
    {
        return $this->directoryAccessLevel($user, $directory, $directories) === FileDirectoryPermission::ACCESS_EDIT;
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    public function canReadEntry(User $user, FileEntry $entry, ?Collection $directories = null): bool
    {
        $directory = $this->resolveEntryDirectory($entry, $directories);

        return $directory !== null
            && $this->canReadDirectory($user, $directory, $directories);
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    public function canEditEntry(User $user, FileEntry $entry, ?Collection $directories = null): bool
    {
        $directory = $this->resolveEntryDirectory($entry, $directories);

        return $directory !== null
            && $this->canEditDirectory($user, $directory, $directories);
    }

    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return array<int, int>
     */
    public function descendantDirectoryIds(FileDirectory $directory, Collection $directories): array
    {
        $children = $directories
            ->where('parent_id', $directory->id)
            ->values();

        return [
            $directory->id,
            ...$children
                ->flatMap(fn (FileDirectory $child): array => $this->descendantDirectoryIds($child, $directories))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, FileDirectory>  $directories
     * @return Collection<int, FileDirectory>
     */
    public function accessibleBreadcrumbs(User $user, FileDirectory $directory, Collection $directories): Collection
    {
        $trail = collect();
        $cursor = $directory;

        while ($cursor) {
            if ($this->canReadDirectory($user, $cursor, $directories)) {
                $trail->prepend($cursor);
            }

            if ($cursor->parent_id === null) {
                break;
            }

            $cursor = $this->resolveDirectory($cursor->parent_id, $directories);
        }

        return $trail->values();
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    public function directoryAccessLevel(User $user, FileDirectory $directory, ?Collection $directories = null): ?string
    {
        if ($user->isSuperAdmin() || $directory->owner_user_id === $user->id) {
            return FileDirectoryPermission::ACCESS_EDIT;
        }

        $resolvedLevel = null;
        $cursor = $directory;

        while ($cursor) {
            foreach ($cursor->permissions as $permission) {
                if (! $this->permissionMatches($permission, $user)) {
                    continue;
                }

                if ($permission->grantsEdit()) {
                    return FileDirectoryPermission::ACCESS_EDIT;
                }

                $resolvedLevel ??= FileDirectoryPermission::ACCESS_READ;
            }

            if ($cursor->parent_id === null) {
                break;
            }

            $cursor = $this->resolveDirectory($cursor->parent_id, $directories);
        }

        return $resolvedLevel;
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    private function resolveDirectory(int $directoryId, ?Collection $directories = null): ?FileDirectory
    {
        if ($directories) {
            /** @var FileDirectory|null $directory */
            $directory = $directories->firstWhere('id', $directoryId);

            return $directory;
        }

        return FileDirectory::query()
            ->with(['permissions.user', 'permissions.group'])
            ->find($directoryId);
    }

    /**
     * @param  Collection<int, FileDirectory>|null  $directories
     */
    private function resolveEntryDirectory(FileEntry $entry, ?Collection $directories = null): ?FileDirectory
    {
        if ($entry->relationLoaded('directory')) {
            return $entry->directory;
        }

        return $this->resolveDirectory($entry->file_directory_id, $directories);
    }

    private function permissionMatches(FileDirectoryPermission $permission, User $user): bool
    {
        return ($permission->user_id !== null && $permission->user_id === $user->id)
            || ($permission->user_group_id !== null && $permission->user_group_id === $user->user_group_id);
    }
}
