<?php

use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open the files workspace', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('files.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('files/Index')
            ->where('can.createRoot', true)
            ->has('tree')
            ->has('availableUsers')
            ->has('availableGroups'));
});

test('direct read access allows browsing and downloading but not modifying a shared directory', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $reader = User::factory()->create();

    $directory = FileDirectory::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Contracts',
    ]);

    FileDirectoryPermission::query()->create([
        'file_directory_id' => $directory->id,
        'user_id' => $reader->id,
        'granted_by_user_id' => $owner->id,
        'access_level' => FileDirectoryPermission::ACCESS_READ,
    ]);

    $storedPath = 'files/'.$directory->id.'/guide.txt';
    Storage::disk('local')->put($storedPath, 'Shared file');

    $entry = FileEntry::query()->create([
        'file_directory_id' => $directory->id,
        'owner_user_id' => $owner->id,
        'original_name' => 'guide.txt',
        'disk' => 'local',
        'path' => $storedPath,
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size_bytes' => Storage::disk('local')->size($storedPath),
    ]);

    $this->actingAs($reader)
        ->get(route('files.index', ['directory' => $directory->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('files/Index')
            ->where('activeDirectory.id', $directory->id)
            ->where('activeDirectory.can_edit', false)
            ->where('activeDirectory.permission_level', FileDirectoryPermission::ACCESS_READ)
            ->has('activeDirectory.entries', 1));

    $this->actingAs($reader)
        ->get(route('files.entries.download', $entry))
        ->assertDownload('guide.txt');

    $this->actingAs($reader)
        ->post(route('files.entries.store'), [
            'directory_id' => $directory->id,
            'file' => UploadedFile::fake()->create('forbidden.txt', 8, 'text/plain'),
        ])
        ->assertForbidden();

    $this->actingAs($reader)
        ->post(route('files.entries.store'), [
            'directory_id' => $directory->id,
            'name' => 'notes.txt',
        ])
        ->assertForbidden();

    $this->actingAs($reader)
        ->post(route('files.directories.store'), [
            'parent_id' => $directory->id,
            'name' => 'Nested',
        ])
        ->assertForbidden();

    $this->actingAs($reader)
        ->delete(route('files.entries.destroy', $entry))
        ->assertForbidden();

    $this->actingAs($reader)
        ->delete(route('files.directories.destroy', $directory))
        ->assertForbidden();
});

test('group edit access allows creating subdirectories and uploading files', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $group = UserGroup::query()->create([
        'name' => 'Finance',
        'description' => 'Finance team',
    ]);
    $member = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    $directory = FileDirectory::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Budget',
    ]);

    FileDirectoryPermission::query()->create([
        'file_directory_id' => $directory->id,
        'user_group_id' => $group->id,
        'granted_by_user_id' => $owner->id,
        'access_level' => FileDirectoryPermission::ACCESS_EDIT,
    ]);

    $this->actingAs($member)
        ->post(route('files.directories.store'), [
            'parent_id' => $directory->id,
            'name' => 'Q4',
        ])
        ->assertRedirect();

    $childDirectory = FileDirectory::query()
        ->where('parent_id', $directory->id)
        ->where('name', 'Q4')
        ->first();

    $this->assertModelExists($childDirectory);

    expect($childDirectory?->owner_user_id)->toBe($member->id);

    $this->actingAs($member)
        ->post(route('files.entries.store'), [
            'directory_id' => $directory->id,
            'file' => UploadedFile::fake()->create('budget-plan.txt', 12, 'text/plain'),
        ])
        ->assertRedirect();

    $entry = FileEntry::query()
        ->where('file_directory_id', $directory->id)
        ->where('original_name', 'budget-plan.txt')
        ->first();

    $this->assertModelExists($entry);

    expect($entry?->owner_user_id)->toBe($member->id)
        ->and($entry?->path)->not->toBeNull();

    expect(Storage::disk('local')->exists((string) $entry?->path))->toBeTrue();
});

test('users with edit access can create empty files in a directory', function () {
    Storage::fake('local');

    $owner = User::factory()->create();

    $directory = FileDirectory::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Playbooks',
    ]);

    $this->actingAs($owner)
        ->post(route('files.entries.store'), [
            'directory_id' => $directory->id,
            'name' => 'runbook.txt',
        ])
        ->assertRedirect(route('files.index', ['directory' => $directory->id]));

    $entry = FileEntry::query()
        ->where('file_directory_id', $directory->id)
        ->where('original_name', 'runbook.txt')
        ->first();

    $this->assertModelExists($entry);

    expect($entry?->owner_user_id)->toBe($owner->id)
        ->and($entry?->size_bytes)->toBe(0)
        ->and($entry?->extension)->toBe('txt')
        ->and($entry?->path)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $entry?->path);
});

test('visible directories expose permissions payload for the access modal', function () {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();

    $directory = FileDirectory::factory()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Projects',
    ]);

    FileDirectoryPermission::query()->create([
        'file_directory_id' => $directory->id,
        'user_id' => $recipient->id,
        'granted_by_user_id' => $owner->id,
        'access_level' => FileDirectoryPermission::ACCESS_READ,
    ]);

    $this->actingAs($owner)
        ->get(route('files.index', ['directory' => $directory->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeDirectory.permissions.0.subject_type', 'user')
            ->where('activeDirectory.permissions.0.access_level', FileDirectoryPermission::ACCESS_READ)
            ->where('tree.0.permissions.0.subject_type', 'user')
            ->where('tree.0.permissions.0.access_level', FileDirectoryPermission::ACCESS_READ));
});

test('files tab is wired into menu definitions and sidebar navigation', function () {
    $filesPage = file_get_contents(resource_path('js/pages/files/Index.vue'));

    expect($filesPage)->toContain('@contextmenu.prevent="openWorkspaceContextMenu($event)"')
        ->and($filesPage)->toContain('createFileDialogOpen')
        ->and($filesPage)->toContain('submitFile')
        ->and($filesPage)->toContain('desktopItems')
        ->and($filesPage)->toContain('@drop.prevent="handleDrop"')
        ->and($filesPage)->toContain('uploadForm.progress')
        ->and($filesPage)->toContain('permissionsDialogOpen')
        ->and($filesPage)->toContain('downloadFile(contextMenu.target.downloadUrl)');
});
