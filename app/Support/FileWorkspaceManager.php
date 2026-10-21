<?php

namespace App\Support;

use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Directory, file and access operations of the files workspace shared by the web and native mobile clients.
 */
class FileWorkspaceManager
{
    public function __construct(private readonly FileAccessManager $accessManager) {}

    /**
     * Every directory with the owner, permission and counter data the access rules and the workspace page need.
     *
     * @return Collection<int, FileDirectory>
     */
    public function directories(): Collection
    {
        return FileDirectory::query()
            ->with([
                'owner:id,name,last_name,email',
                'permissions.user:id,name,last_name,email,user_group_id',
                'permissions.group:id,name,description',
                'permissions.grantedBy:id,name,last_name,email',
            ])
            ->withCount(['children', 'entries'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * The directory the workspace opens with its entries loaded: the requested directory when the user can read it,
     * otherwise the first readable root directory, otherwise the first readable directory.
     *
     * @param  Collection<int, FileDirectory>  $directories
     */
    public function activeDirectory(User $user, Collection $directories, int $requestedDirectoryId): ?FileDirectory
    {
        $accessibleDirectories = $this->accessManager->accessibleDirectories($directories, $user);

        /** @var FileDirectory|null $activeDirectory */
        $activeDirectory = $requestedDirectoryId > 0
            ? $accessibleDirectories->firstWhere('id', $requestedDirectoryId)
            : null;

        if (! $activeDirectory) {
            $activeDirectory = $accessibleDirectories->firstWhere('parent_id', null)
                ?? $accessibleDirectories->first();
        }

        if ($activeDirectory) {
            $activeDirectory->setRelation(
                'entries',
                FileEntry::query()
                    ->with('owner:id,name,last_name,email')
                    ->where('file_directory_id', $activeDirectory->id)
                    ->orderBy('original_name')
                    ->get(),
            );
        }

        return $activeDirectory;
    }

    /**
     * Create a directory owned by the user at the end of its parent (or of the root level).
     */
    public function createDirectory(User $owner, ?FileDirectory $parentDirectory, string $name): FileDirectory
    {
        return FileDirectory::query()->create([
            'parent_id' => $parentDirectory?->id,
            'owner_user_id' => $owner->id,
            'name' => $name,
            'sort_order' => ((int) FileDirectory::query()
                ->where('parent_id', $parentDirectory?->id)
                ->max('sort_order')) + 10,
        ]);
    }

    /**
     * Store an uploaded file in the directory, or create an empty file named after the entry when nothing was uploaded.
     */
    public function createEntry(User $owner, FileDirectory $directory, string $entryName, ?UploadedFile $uploadedFile): FileEntry
    {
        $extension = pathinfo($entryName, PATHINFO_EXTENSION);
        $storedPath = 'files/'.$directory->id.'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $mimeType = null;
        $sizeBytes = 0;

        if ($uploadedFile !== null) {
            $storedPath = $uploadedFile->store('files/'.$directory->id, 'local');
            $mimeType = $uploadedFile->getClientMimeType();
            $sizeBytes = $uploadedFile->getSize() ?: 0;
        } else {
            Storage::disk('local')->put($storedPath, '');
        }

        return FileEntry::query()->create([
            'file_directory_id' => $directory->id,
            'owner_user_id' => $owner->id,
            'original_name' => $entryName,
            'disk' => 'local',
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => $sizeBytes,
        ]);
    }

    /**
     * Delete a file entry together with its stored file.
     */
    public function deleteEntry(FileEntry $entry): void
    {
        Storage::disk($entry->disk)->delete($entry->path);
        $entry->delete();
    }

    /**
     * Delete a directory with its subdirectories and the stored files of every entry inside them.
     *
     * @param  Collection<int, FileDirectory>  $directories
     */
    public function deleteDirectory(FileDirectory $directory, Collection $directories): void
    {
        $directoryIds = $this->accessManager->descendantDirectoryIds($directory, $directories);
        $entries = FileEntry::query()
            ->whereIn('file_directory_id', $directoryIds)
            ->get();

        DB::transaction(function () use ($entries, $directory): void {
            foreach ($entries as $entry) {
                Storage::disk($entry->disk)->delete($entry->path);
            }

            $directory->delete();
        });
    }

    /**
     * Grant (or change) the access level of a user or a user group to a directory.
     */
    public function savePermission(
        FileDirectory $directory,
        ?int $userId,
        ?int $userGroupId,
        string $accessLevel,
        User $grantedBy,
    ): FileDirectoryPermission {
        return FileDirectoryPermission::query()->updateOrCreate(
            [
                'file_directory_id' => $directory->id,
                'user_id' => $userId,
                'user_group_id' => $userGroupId,
            ],
            [
                'access_level' => $accessLevel,
                'granted_by_user_id' => $grantedBy->id,
            ],
        );
    }
}
