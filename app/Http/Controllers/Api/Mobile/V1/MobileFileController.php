<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFileDirectoryPermissionRequest;
use App\Http\Requests\StoreFileDirectoryRequest;
use App\Http\Requests\StoreFileEntryRequest;
use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\User;
use App\Support\FileAccessManager;
use App\Support\FilePageData;
use App\Support\FileWorkspaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileFileController extends Controller
{
    /**
     * The files workspace (the web files page props) opened on the requested directory.
     */
    public function index(Request $request, FilePageData $filePageData): JsonResponse
    {
        return response()->json([
            'data' => $this->pageData($filePageData, $this->viewer($request), $request->integer('directory')),
        ]);
    }

    /**
     * Create a root directory, or a subdirectory of a directory the user can edit.
     * Responds with the workspace opened on the new directory.
     */
    public function storeDirectory(
        StoreFileDirectoryRequest $request,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
        FilePageData $filePageData,
    ): JsonResponse {
        $user = $this->viewer($request);
        $parentDirectory = $request->parentDirectory();

        if ($parentDirectory) {
            $directories = $fileWorkspaceManager->directories();
            abort_unless($fileAccessManager->canEditDirectory($user, $parentDirectory, $directories), 403);
        }

        $directory = $fileWorkspaceManager->createDirectory($user, $parentDirectory, $request->directoryName());

        return response()->json([
            'message' => __('ui.files.directory_created_success'),
            'data' => $this->pageData($filePageData, $user, $directory->id),
        ], 201);
    }

    /**
     * Delete a directory the user can edit with its subdirectories and files.
     */
    public function destroyDirectory(
        Request $request,
        FileDirectory $fileDirectory,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): JsonResponse {
        $user = $this->viewer($request);
        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        $fileWorkspaceManager->deleteDirectory($fileDirectory, $directories);

        return response()->json([
            'message' => __('ui.files.directory_deleted_success'),
        ]);
    }

    /**
     * Grant a user or a user group read or edit access to a directory the user can edit.
     * Responds with the workspace opened on the directory.
     */
    public function storePermission(
        StoreFileDirectoryPermissionRequest $request,
        FileDirectory $fileDirectory,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
        FilePageData $filePageData,
    ): JsonResponse {
        $user = $this->viewer($request);
        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);

        $permission = $fileWorkspaceManager->savePermission(
            $fileDirectory,
            $request->targetUserId(),
            $request->targetGroupId(),
            $request->accessLevel(),
            $user,
        );

        return response()->json([
            'message' => __('ui.files.permission_saved_success'),
            'data' => $this->pageData($filePageData, $user, $fileDirectory->id),
        ], $permission->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Revoke an access grant of a directory the user can edit.
     */
    public function destroyPermission(
        Request $request,
        FileDirectory $fileDirectory,
        FileDirectoryPermission $fileDirectoryPermission,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): JsonResponse {
        $user = $this->viewer($request);
        $directories = $fileWorkspaceManager->directories();
        abort_unless($fileAccessManager->canEditDirectory($user, $fileDirectory, $directories), 403);
        abort_unless($fileDirectoryPermission->file_directory_id === $fileDirectory->id, 404);

        $fileDirectoryPermission->delete();

        return response()->json([
            'message' => __('ui.files.permission_deleted_success'),
        ]);
    }

    /**
     * Upload a file (multipart "file") or create an empty file ("name") in a directory the user can edit.
     * Responds with the workspace opened on the directory.
     */
    public function storeEntry(
        StoreFileEntryRequest $request,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
        FilePageData $filePageData,
    ): JsonResponse {
        $user = $this->viewer($request);
        $directories = $fileWorkspaceManager->directories();
        $directory = $directories->firstWhere('id', $request->directory()->id) ?? $request->directory();

        abort_unless($fileAccessManager->canEditDirectory($user, $directory, $directories), 403);

        $fileWorkspaceManager->createEntry(
            $user,
            $directory,
            $request->entryName(),
            $request->hasUploadedFile() ? $request->uploadedFile() : null,
        );

        return response()->json([
            'message' => $request->hasUploadedFile()
                ? __('ui.files.file_uploaded_success')
                : __('ui.files.file_created_success'),
            'data' => $this->pageData($filePageData, $user, $directory->id),
        ], 201);
    }

    /**
     * Download a file of a directory the user can read, as an attachment named after the original file name.
     */
    public function download(
        Request $request,
        FileEntry $fileEntry,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): StreamedResponse {
        $user = $this->viewer($request);
        abort_unless($fileAccessManager->canReadEntry($user, $fileEntry, $fileWorkspaceManager->directories()), 403);

        return Storage::disk($fileEntry->disk)->download($fileEntry->path, $fileEntry->original_name);
    }

    /**
     * Delete a file of a directory the user can edit.
     */
    public function destroyEntry(
        Request $request,
        FileEntry $fileEntry,
        FileAccessManager $fileAccessManager,
        FileWorkspaceManager $fileWorkspaceManager,
    ): JsonResponse {
        $user = $this->viewer($request);
        abort_unless($fileAccessManager->canEditEntry($user, $fileEntry, $fileWorkspaceManager->directories()), 403);

        $fileWorkspaceManager->deleteEntry($fileEntry);

        return response()->json([
            'message' => __('ui.files.file_deleted_success'),
        ]);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * The web page props with an API download link next to the web download link of every listed file.
     *
     * @return array<string, mixed>
     */
    private function pageData(FilePageData $filePageData, User $user, int $directoryId): array
    {
        $pageData = $filePageData->buildForViewer($user, $directoryId);
        $entries = $pageData['activeDirectory']['entries'] ?? null;

        if (is_array($pageData['activeDirectory'] ?? null) && is_array($entries)) {
            $pageData['activeDirectory']['entries'] = array_map(
                fn (mixed $entry): mixed => is_array($entry) && is_int($entry['id'] ?? null)
                    ? [...$entry, 'mobile_download_url' => route('mobile.v1.files.entries.download', ['fileEntry' => $entry['id']])]
                    : $entry,
                $entries,
            );
        }

        return $pageData;
    }
}
