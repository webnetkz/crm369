<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFileDirectoryPermissionRequest;
use App\Http\Requests\StoreFileDirectoryRequest;
use App\Http\Requests\StoreFileEntryRequest;
use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Support\FileAccessManager;
use App\Support\FilePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    public function index(Request $request, FilePageData $filePageData, FileAccessManager $fileAccessManager): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        $accessibleDirectories = $fileAccessManager->accessibleDirectories($directories, $user);
        $requestedDirectoryId = $request->integer('directory');

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

        return Inertia::render('files/Index', $filePageData->build(
            $user,
            $directories,
            $activeDirectory,
            $fileAccessManager,
        ));
    }

    public function storeDirectory(
        StoreFileDirectoryRequest $request,
        FileAccessManager $fileAccessManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $parentDirectory = $request->parentDirectory();

        if ($parentDirectory) {
            $directories = $this->directoryQuery()->get();
            abort_unless($fileAccessManager->canEditDirectory($user, $parentDirectory, $directories), 403);
        }

        $directory = FileDirectory::query()->create([
            'parent_id' => $parentDirectory?->id,
            'owner_user_id' => $user->id,
            'name' => $request->directoryName(),
            'sort_order' => ((int) FileDirectory::query()
                ->where('parent_id', $parentDirectory?->id)
                ->max('sort_order')) + 10,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.directory_created_success')]);

        return to_route('files.index', ['directory' => $directory->id]);
    }

    public function storeEntry(
        StoreFileEntryRequest $request,
        FileAccessManager $fileAccessManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        $directory = $directories->firstWhere('id', $request->directory()->id) ?? $request->directory();

        abort_unless($fileAccessManager->canEditDirectory($user, $directory, $directories), 403);

        $entryName = $request->entryName();
        $extension = pathinfo($entryName, PATHINFO_EXTENSION);
        $storedPath = 'files/'.$directory->id.'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $mimeType = null;
        $sizeBytes = 0;

        if ($request->hasUploadedFile()) {
            $uploadedFile = $request->uploadedFile();
            $storedPath = $uploadedFile->store('files/'.$directory->id, 'local');
            $mimeType = $uploadedFile->getClientMimeType();
            $sizeBytes = $uploadedFile->getSize() ?? 0;
        } else {
            Storage::disk('local')->put($storedPath, '');
        }

        FileEntry::query()->create([
            'file_directory_id' => $directory->id,
            'owner_user_id' => $user->id,
            'original_name' => $entryName,
            'disk' => 'local',
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => $sizeBytes,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->hasUploadedFile()
                ? __('ui.files.file_uploaded_success')
                : __('ui.files.file_created_success'),
        ]);

        return to_route('files.index', ['directory' => $directory->id]);
    }

    public function download(FileEntry $fileEntry, FileAccessManager $fileAccessManager)
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        abort_unless($fileAccessManager->canReadEntry($user, $fileEntry, $directories), 403);

        return Storage::disk($fileEntry->disk)->download($fileEntry->path, $fileEntry->original_name);
    }

    public function destroyEntry(FileEntry $fileEntry, FileAccessManager $fileAccessManager): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        abort_unless($fileAccessManager->canEditEntry($user, $fileEntry, $directories), 403);

        Storage::disk($fileEntry->disk)->delete($fileEntry->path);
        $directoryId = $fileEntry->file_directory_id;
        $fileEntry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.file_deleted_success')]);

        return to_route('files.index', ['directory' => $directoryId]);
    }

    public function destroyDirectory(FileDirectory $fileDirectory, FileAccessManager $fileAccessManager): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        $directoryIds = $fileAccessManager->descendantDirectoryIds($fileDirectory, $directories);
        $entries = FileEntry::query()
            ->whereIn('file_directory_id', $directoryIds)
            ->get();

        DB::transaction(function () use ($entries, $fileDirectory): void {
            foreach ($entries as $entry) {
                Storage::disk($entry->disk)->delete($entry->path);
            }

            $fileDirectory->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.directory_deleted_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->parent_id]);
    }

    public function storePermission(
        StoreFileDirectoryPermissionRequest $request,
        FileDirectory $fileDirectory,
        FileAccessManager $fileAccessManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        FileDirectoryPermission::query()->updateOrCreate(
            [
                'file_directory_id' => $fileDirectory->id,
                'user_id' => $request->targetUserId(),
                'user_group_id' => $request->targetGroupId(),
            ],
            [
                'access_level' => $request->accessLevel(),
                'granted_by_user_id' => $user->id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.permission_saved_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->id]);
    }

    public function destroyPermission(
        FileDirectory $fileDirectory,
        FileDirectoryPermission $fileDirectoryPermission,
        FileAccessManager $fileAccessManager,
    ): RedirectResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $this->directoryQuery()->get();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);
        abort_unless($fileDirectoryPermission->file_directory_id === $fileDirectory->id, 404);

        $fileDirectoryPermission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.permission_deleted_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->id]);
    }

    private function directoryQuery()
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
            ->orderBy('name');
    }
}
