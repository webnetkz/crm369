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
use App\Support\FileWorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function index(Request $request, FilePageData $filePageData): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return Inertia::render('files/Index', $filePageData->buildForViewer($user, $request->integer('directory')));
    }

    public function storeDirectory(
        StoreFileDirectoryRequest $request,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $parentDirectory = $request->parentDirectory();

        if ($parentDirectory) {
            $directories = $fileWorkspaceManager->directories();
            abort_unless($fileAccessManager->canEditDirectory($user, $parentDirectory, $directories), 403);
        }

        $directory = $fileWorkspaceManager->createDirectory($user, $parentDirectory, $request->directoryName());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.directory_created_success')]);

        return to_route('files.index', ['directory' => $directory->id]);
    }

    public function storeEntry(
        StoreFileEntryRequest $request,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        $directory = $directories->firstWhere('id', $request->directory()->id) ?? $request->directory();

        abort_unless($fileAccessManager->canEditDirectory($user, $directory, $directories), 403);

        $fileWorkspaceManager->createEntry(
            $user,
            $directory,
            $request->entryName(),
            $request->hasUploadedFile() ? $request->uploadedFile() : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->hasUploadedFile()
                ? __('ui.files.file_uploaded_success')
                : __('ui.files.file_created_success'),
        ]);

        return to_route('files.index', ['directory' => $directory->id]);
    }

    public function download(
        FileEntry $fileEntry,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): StreamedResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canReadEntry($user, $fileEntry, $directories), 403);

        return Storage::disk($fileEntry->disk)->download($fileEntry->path, $fileEntry->original_name);
    }

    public function destroyEntry(
        FileEntry $fileEntry,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditEntry($user, $fileEntry, $directories), 403);

        $directoryId = $fileEntry->file_directory_id;
        $fileWorkspaceManager->deleteEntry($fileEntry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.file_deleted_success')]);

        return to_route('files.index', ['directory' => $directoryId]);
    }

    public function destroyDirectory(
        FileDirectory $fileDirectory,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        $fileWorkspaceManager->deleteDirectory($fileDirectory, $directories);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.directory_deleted_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->parent_id]);
    }

    public function storePermission(
        StoreFileDirectoryPermissionRequest $request,
        FileDirectory $fileDirectory,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        $fileWorkspaceManager->savePermission(
            $fileDirectory,
            $request->targetUserId(),
            $request->targetGroupId(),
            $request->accessLevel(),
            $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.permission_saved_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->id]);
    }

    public function destroyPermission(
        FileDirectory $fileDirectory,
        FileDirectoryPermission $fileDirectoryPermission,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): RedirectResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);
        abort_unless($fileDirectoryPermission->file_directory_id === $fileDirectory->id, 404);

        $fileDirectoryPermission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.files.permission_deleted_success')]);

        return to_route('files.index', ['directory' => $fileDirectory->id]);
    }
}
