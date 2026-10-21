<?php

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use App\Models\EdoDocument;
use App\Models\FileDirectory;
use App\Models\FileDirectoryPermission;
use App\Models\FileEntry;
use App\Models\MobileAccessToken;
use App\Models\PortalForm;
use App\Models\PortalFormField;
use App\Models\PortalFormSubmission;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\PortalFormManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Issue a mobile bearer token for the user. The resolved mobile user is cached by the guard
 * between requests of one test, so the guards are reset whenever another user signs in.
 */
function mobileFilesFormsConferencesAs(User $user): string
{
    app('auth')->forgetGuards();

    return MobileAccessToken::issueToUser($user, 'android-files-forms-conferences-'.$user->id)['plain_text_token'];
}

function mobileFilesFormsConferencesSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'mobile-files-forms-admin@example.com']);

    return User::factory()->create([
        'email' => 'mobile-files-forms-admin@example.com',
        'language' => 'en',
    ]);
}

/**
 * A member of a group that explicitly configures the given modules with only the given permissions.
 *
 * @param  array<int, string>  $permissions
 * @param  array<int, string>  $configuredModules
 */
function mobileFilesFormsConferencesGroupUser(array $permissions = [], array $configuredModules = []): User
{
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules($permissions, $configuredModules),
    ]);

    return User::factory()->create([
        'user_group_id' => $group->id,
        'language' => 'en',
    ]);
}

/**
 * A complete form payload as the web and mobile clients send it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mobileFilesFormsConferencesFormPayload(User $target, array $overrides = []): array
{
    return [
        'name' => 'Client intake',
        'description' => "Collect a short summary\r\nand contact details.",
        'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
        'target_user_id' => $target->id,
        'is_active' => true,
        'style_settings' => [
            'container_width' => 'xl',
            'background_color' => '#fef3c7',
            'border_color' => '#F59E0B',
            'text_color' => '#1F2937',
            'input_background_color' => '#FFFFFF',
            'input_border_color' => '#FCD34D',
            'button_background_color' => '#B45309',
            'button_text_color' => '#FFFFFF',
            'border_radius' => 30,
            'padding' => 40,
        ],
        'completion_settings' => [
            'action' => PortalForm::COMPLETION_ACTION_MESSAGE,
            'success_message' => 'Thank you! We will contact you soon.',
            'redirect_url' => null,
        ],
        'fields' => [
            [
                'label' => 'Client name',
                'type' => PortalFormField::TYPE_TEXT,
                'placeholder' => 'Jane Doe',
                'is_required' => true,
            ],
            [
                'label' => 'Request details',
                'type' => PortalFormField::TYPE_TEXTAREA,
                'placeholder' => '',
                'is_required' => false,
            ],
        ],
        ...$overrides,
    ];
}

test('mobile files forms conferences and edo endpoints require a mobile session', function () {
    $this->getJson(route('mobile.v1.files.index'))
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
    $this->postJson(route('mobile.v1.files.directories.store'))->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.files.directories.destroy', 1))->assertUnauthorized();
    $this->postJson(route('mobile.v1.files.directories.permissions.store', 1))->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.files.directories.permissions.destroy', [1, 1]))->assertUnauthorized();
    $this->postJson(route('mobile.v1.files.entries.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.files.entries.download', 1))->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.files.entries.destroy', 1))->assertUnauthorized();

    $this->getJson(route('mobile.v1.forms.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.forms.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.forms.show', 1))->assertUnauthorized();
    $this->patchJson(route('mobile.v1.forms.update', 1))->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.forms.destroy', 1))->assertUnauthorized();

    $this->getJson(route('mobile.v1.conferences.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.conferences.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.conferences.show', 1))->assertUnauthorized();
    $this->postJson(route('mobile.v1.conferences.invitations.store', 1))->assertUnauthorized();
    $this->patchJson(route('mobile.v1.conferences.end', 1))->assertUnauthorized();

    $this->getJson(route('mobile.v1.edo.index'))->assertUnauthorized();
    $this->postJson(route('mobile.v1.edo.store'))->assertUnauthorized();
    $this->getJson(route('mobile.v1.edo.show', 1))->assertUnauthorized();
    $this->patchJson(route('mobile.v1.edo.update', 1))->assertUnauthorized();
    $this->deleteJson(route('mobile.v1.edo.destroy', 1))->assertUnauthorized();
    $this->postJson(route('mobile.v1.edo.public-link.store', 1))->assertUnauthorized();
    $this->getJson(route('mobile.v1.edo.file.download', 1))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson(route('mobile.v1.files.index'))
        ->assertUnauthorized();
});

test('mobile files forms conferences and edo endpoints are not found when their module is disabled', function () {
    Storage::fake('local');

    $superAdmin = mobileFilesFormsConferencesSuperAdmin();
    $directory = FileDirectory::factory()->create(['owner_user_id' => $superAdmin->id]);
    $permission = FileDirectoryPermission::factory()->create(['file_directory_id' => $directory->id]);
    $entry = FileEntry::factory()->create(['file_directory_id' => $directory->id]);
    $form = PortalForm::factory()->create(['owner_user_id' => $superAdmin->id, 'target_user_id' => $superAdmin->id]);
    $conference = Conference::factory()->create(['created_by_user_id' => $superAdmin->id]);
    $document = EdoDocument::factory()->create([
        'created_by_user_id' => $superAdmin->id,
        'updated_by_user_id' => $superAdmin->id,
    ]);

    PortalSetting::current()->update(['disabled_modules' => ['files', 'forms', 'conferences', 'edo']]);

    $this->withToken(mobileFilesFormsConferencesAs($superAdmin));

    $this->getJson(route('mobile.v1.files.index'))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);
    $this->postJson(route('mobile.v1.files.directories.store'), ['name' => 'Hidden'])->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.directories.destroy', $directory))->assertNotFound();
    $this->postJson(route('mobile.v1.files.directories.permissions.store', $directory), [
        'access_level' => FileDirectoryPermission::ACCESS_READ,
        'user_id' => $superAdmin->id,
    ])->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.directories.permissions.destroy', [$directory, $permission]))->assertNotFound();
    $this->postJson(route('mobile.v1.files.entries.store'), ['directory_id' => $directory->id, 'name' => 'hidden.txt'])->assertNotFound();
    $this->getJson(route('mobile.v1.files.entries.download', $entry))->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.entries.destroy', $entry))->assertNotFound();

    $this->getJson(route('mobile.v1.forms.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($superAdmin))->assertNotFound();
    $this->getJson(route('mobile.v1.forms.show', $form))->assertNotFound();
    $this->patchJson(route('mobile.v1.forms.update', $form), mobileFilesFormsConferencesFormPayload($superAdmin))->assertNotFound();
    $this->deleteJson(route('mobile.v1.forms.destroy', $form))->assertNotFound();

    $this->getJson(route('mobile.v1.conferences.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.conferences.store'), ['title' => 'Hidden'])->assertNotFound();
    $this->getJson(route('mobile.v1.conferences.show', $conference))->assertNotFound();
    $this->postJson(route('mobile.v1.conferences.invitations.store', $conference), ['invited_user_ids' => [$superAdmin->id]])->assertNotFound();
    $this->patchJson(route('mobile.v1.conferences.end', $conference))->assertNotFound();

    $this->getJson(route('mobile.v1.edo.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.edo.store'), [
        'title' => 'Hidden',
        'counterparty_name' => 'Hidden LLP',
        'counterparty_identifier' => '123456789012',
        'content' => 'Hidden body.',
    ])->assertNotFound();
    $this->getJson(route('mobile.v1.edo.show', $document))->assertNotFound();
    $this->patchJson(route('mobile.v1.edo.update', $document), ['title' => 'Hidden'])->assertNotFound();
    $this->deleteJson(route('mobile.v1.edo.destroy', $document))->assertNotFound();
    $this->postJson(route('mobile.v1.edo.public-link.store', $document))->assertNotFound();
    $this->getJson(route('mobile.v1.edo.file.download', $document))->assertNotFound();

    expect(FileDirectory::query()->where('name', 'Hidden')->exists())->toBeFalse()
        ->and($directory->fresh())->not->toBeNull()
        ->and($permission->fresh())->not->toBeNull()
        ->and($entry->fresh())->not->toBeNull()
        ->and(PortalForm::query()->count())->toBe(1)
        ->and($form->fresh())->not->toBeNull()
        ->and(Conference::query()->count())->toBe(1)
        ->and($conference->fresh()->ended_at)->toBeNull()
        ->and(EdoDocument::query()->count())->toBe(1)
        ->and($document->fresh()->public_token)->toBeNull();
});

test('mobile module endpoints are forbidden when the user group does not grant the module', function () {
    $restricted = mobileFilesFormsConferencesGroupUser([], ['files', 'forms', 'conferences', 'edo']);
    $document = EdoDocument::factory()->create([
        'created_by_user_id' => $restricted->id,
        'updated_by_user_id' => $restricted->id,
    ]);

    $this->withToken(mobileFilesFormsConferencesAs($restricted));

    $this->getJson(route('mobile.v1.files.index'))
        ->assertForbidden();
    $this->postJson(route('mobile.v1.files.directories.store'), ['name' => 'Denied'])->assertForbidden();
    $this->getJson(route('mobile.v1.forms.index'))->assertForbidden();
    $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($restricted))->assertForbidden();
    $this->getJson(route('mobile.v1.conferences.index'))->assertForbidden();
    $this->postJson(route('mobile.v1.conferences.store'), ['title' => 'Denied'])->assertForbidden();
    $this->getJson(route('mobile.v1.edo.index'))->assertForbidden();
    $this->getJson(route('mobile.v1.edo.show', $document))->assertForbidden();
    $this->getJson(route('mobile.v1.edo.file.download', $document))->assertForbidden();

    $allowed = mobileFilesFormsConferencesGroupUser([
        UserGroup::PERMISSION_ACCESS_FILES,
        UserGroup::PERMISSION_ACCESS_FORMS,
        UserGroup::PERMISSION_ACCESS_CONFERENCES,
        UserGroup::PERMISSION_ACCESS_EDO,
    ], ['files', 'forms', 'conferences', 'edo']);

    $this->withToken(mobileFilesFormsConferencesAs($allowed));

    $this->getJson(route('mobile.v1.files.index'))->assertSuccessful();
    $this->getJson(route('mobile.v1.forms.index'))->assertSuccessful();
    $this->getJson(route('mobile.v1.conferences.index'))->assertSuccessful();
    $this->getJson(route('mobile.v1.edo.index'))->assertSuccessful();

    expect(FileDirectory::query()->exists())->toBeFalse()
        ->and(PortalForm::query()->exists())->toBeFalse()
        ->and(Conference::query()->exists())->toBeFalse();
});

test('the mobile files index mirrors the web page props and opens the requested readable directory', function () {
    Storage::fake('local');

    $owner = User::factory()->create(['name' => 'Olivia', 'last_name' => 'Owner', 'language' => 'en']);
    $reader = User::factory()->create(['name' => 'Ruslan', 'last_name' => 'Reader', 'language' => 'en']);

    $contracts = FileDirectory::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Contracts', 'sort_order' => 10]);
    $archive = FileDirectory::factory()->create([
        'owner_user_id' => $owner->id,
        'parent_id' => $contracts->id,
        'name' => 'Archive',
        'sort_order' => 10,
    ]);
    $private = FileDirectory::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Private', 'sort_order' => 20]);

    FileDirectoryPermission::query()->create([
        'file_directory_id' => $contracts->id,
        'user_id' => $reader->id,
        'granted_by_user_id' => $owner->id,
        'access_level' => FileDirectoryPermission::ACCESS_READ,
    ]);

    Storage::disk('local')->put('files/'.$contracts->id.'/guide.txt', 'Shared guide');

    $entry = FileEntry::factory()->create([
        'file_directory_id' => $contracts->id,
        'owner_user_id' => $owner->id,
        'original_name' => 'guide.txt',
        'path' => 'files/'.$contracts->id.'/guide.txt',
        'mime_type' => 'text/plain',
        'extension' => 'txt',
        'size_bytes' => 12,
    ]);

    $token = mobileFilesFormsConferencesAs($reader);

    $this->withToken($token)
        ->getJson(route('mobile.v1.files.index'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.tree')
        ->assertJsonPath('data.tree.0.id', $contracts->id)
        ->assertJsonPath('data.tree.0.permission_level', FileDirectoryPermission::ACCESS_READ)
        ->assertJsonPath('data.tree.0.can_edit', false)
        ->assertJsonPath('data.tree.0.children_count', 1)
        ->assertJsonPath('data.tree.0.files_count', 1)
        ->assertJsonPath('data.tree.0.children.0.id', $archive->id)
        ->assertJsonPath('data.activeDirectory.id', $contracts->id)
        ->assertJsonPath('data.activeDirectory.parent_id', null)
        ->assertJsonPath('data.activeDirectory.owner', [
            'id' => $owner->id,
            'name' => 'Olivia Owner',
            'email' => $owner->email,
        ])
        ->assertJsonPath('data.activeDirectory.permission_level', FileDirectoryPermission::ACCESS_READ)
        ->assertJsonPath('data.activeDirectory.can_edit', false)
        ->assertJsonPath('data.activeDirectory.breadcrumbs', [['id' => $contracts->id, 'name' => 'Contracts']])
        ->assertJsonPath('data.activeDirectory.children.0.id', $archive->id)
        ->assertJsonPath('data.activeDirectory.entries.0.id', $entry->id)
        ->assertJsonPath('data.activeDirectory.entries.0.original_name', 'guide.txt')
        ->assertJsonPath('data.activeDirectory.entries.0.owner_name', 'Olivia Owner')
        ->assertJsonPath('data.activeDirectory.entries.0.download_url', route('files.entries.download', $entry))
        ->assertJsonPath('data.activeDirectory.entries.0.mobile_download_url', route('mobile.v1.files.entries.download', $entry))
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_type', 'user')
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_id', $reader->id)
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_name', 'Ruslan Reader')
        ->assertJsonPath('data.activeDirectory.permissions.0.granted_by_name', 'Olivia Owner')
        ->assertJsonPath('data.can.createRoot', true)
        ->assertJsonStructure([
            'data' => [
                'tree' => [[
                    'id', 'name', 'parent_id', 'can_edit', 'permission_level', 'children_count', 'files_count',
                    'permissions' => [['id', 'access_level', 'subject_type', 'subject_id', 'subject_name', 'granted_by_name', 'created_at']],
                    'children',
                ]],
                'activeDirectory' => [
                    'id', 'name', 'parent_id', 'owner' => ['id', 'name', 'email'], 'permission_level', 'can_edit',
                    'breadcrumbs' => [['id', 'name']],
                    'children',
                    'entries' => [[
                        'id', 'original_name', 'mime_type', 'extension', 'size_bytes', 'owner_name', 'created_at',
                        'download_url', 'mobile_download_url',
                    ]],
                    'permissions',
                    'created_at',
                    'updated_at',
                ],
                'availableUsers' => [['id', 'name', 'email', 'user_group_id']],
                'availableGroups',
                'can' => ['createRoot'],
            ],
        ]);

    $this->getJson(route('mobile.v1.files.index', ['directory' => $archive->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.activeDirectory.id', $archive->id)
        ->assertJsonPath('data.activeDirectory.permission_level', FileDirectoryPermission::ACCESS_READ)
        ->assertJsonPath('data.activeDirectory.breadcrumbs', [
            ['id' => $contracts->id, 'name' => 'Contracts'],
            ['id' => $archive->id, 'name' => 'Archive'],
        ])
        ->assertJsonPath('data.activeDirectory.entries', []);

    $this->getJson(route('mobile.v1.files.index', ['directory' => $private->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.activeDirectory.id', $contracts->id);

    $webProps = $this->actingAs($reader)
        ->get(route('files.index', ['directory' => $contracts->id]))
        ->assertSuccessful()
        ->inertiaProps();

    app('auth')->forgetGuards();

    $mobileData = $this->withToken($token)
        ->getJson(route('mobile.v1.files.index', ['directory' => $contracts->id]))
        ->assertSuccessful()
        ->json('data');

    $mobileData['activeDirectory']['entries'] = array_map(
        fn (array $mobileEntry): array => Arr::except($mobileEntry, 'mobile_download_url'),
        $mobileData['activeDirectory']['entries'],
    );

    expect(array_keys($mobileData))->toBe(['tree', 'activeDirectory', 'availableUsers', 'availableGroups', 'can'])
        ->and($mobileData)->toEqual(Arr::only($webProps, array_keys($mobileData)));
});

test('read access through the mobile api allows browsing and downloading but not modifying a shared directory', function () {
    Storage::fake('local');

    $owner = User::factory()->create(['language' => 'en']);
    $reader = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);

    $directory = FileDirectory::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Contracts']);
    $permission = FileDirectoryPermission::query()->create([
        'file_directory_id' => $directory->id,
        'user_id' => $reader->id,
        'granted_by_user_id' => $owner->id,
        'access_level' => FileDirectoryPermission::ACCESS_READ,
    ]);

    $storedPath = 'files/'.$directory->id.'/guide.txt';
    Storage::disk('local')->put($storedPath, 'Shared file');

    $entry = FileEntry::factory()->create([
        'file_directory_id' => $directory->id,
        'owner_user_id' => $owner->id,
        'original_name' => 'guide.txt',
        'path' => $storedPath,
        'size_bytes' => 11,
    ]);

    $this->withToken(mobileFilesFormsConferencesAs($reader))->withHeader('Accept', 'application/json');

    $download = $this->get(route('mobile.v1.files.entries.download', $entry))
        ->assertSuccessful()
        ->assertDownload('guide.txt');

    expect($download->streamedContent())->toBe('Shared file');

    $this->post(route('mobile.v1.files.entries.store'), [
        'directory_id' => $directory->id,
        'file' => UploadedFile::fake()->create('forbidden.txt', 8, 'text/plain'),
    ])->assertForbidden();
    $this->postJson(route('mobile.v1.files.entries.store'), [
        'directory_id' => $directory->id,
        'name' => 'notes.txt',
    ])->assertForbidden();
    $this->postJson(route('mobile.v1.files.directories.store'), [
        'parent_id' => $directory->id,
        'name' => 'Nested',
    ])->assertForbidden();
    $this->postJson(route('mobile.v1.files.directories.permissions.store', $directory), [
        'access_level' => FileDirectoryPermission::ACCESS_EDIT,
        'user_id' => $reader->id,
    ])->assertForbidden();
    $this->deleteJson(route('mobile.v1.files.directories.permissions.destroy', [$directory, $permission]))->assertForbidden();
    $this->deleteJson(route('mobile.v1.files.entries.destroy', $entry))->assertForbidden();
    $this->deleteJson(route('mobile.v1.files.directories.destroy', $directory))->assertForbidden();

    $this->withToken(mobileFilesFormsConferencesAs($outsider));

    $this->getJson(route('mobile.v1.files.index', ['directory' => $directory->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.tree', [])
        ->assertJsonPath('data.activeDirectory', null);
    $this->get(route('mobile.v1.files.entries.download', $entry))->assertForbidden();
    $this->get(route('mobile.v1.files.entries.download', 999999))->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.entries.destroy', 999999))->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.directories.destroy', 999999))->assertNotFound();

    expect($entry->fresh())->not->toBeNull()
        ->and($directory->fresh())->not->toBeNull()
        ->and($permission->fresh()->access_level)->toBe(FileDirectoryPermission::ACCESS_READ)
        ->and(FileDirectory::query()->count())->toBe(1)
        ->and(FileEntry::query()->count())->toBe(1);

    Storage::disk('local')->assertExists($storedPath);
});

test('directory owners can create directories upload files and manage access through the mobile api', function () {
    Storage::fake('local');

    $owner = User::factory()->create(['name' => 'Olivia', 'last_name' => 'Owner', 'language' => 'en']);
    $colleague = User::factory()->create(['language' => 'en']);
    $group = UserGroup::factory()->create(['name' => 'Finance']);
    $foreignPermission = FileDirectoryPermission::factory()->create();

    $this->withToken(mobileFilesFormsConferencesAs($owner))->withHeader('Accept', 'application/json');

    $projectsId = $this->postJson(route('mobile.v1.files.directories.store'), ['name' => '  Projects  '])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.files.directory_created_success', [], 'en'))
        ->assertJsonPath('data.activeDirectory.name', 'Projects')
        ->assertJsonPath('data.activeDirectory.parent_id', null)
        ->assertJsonPath('data.activeDirectory.owner.id', $owner->id)
        ->assertJsonPath('data.activeDirectory.permission_level', FileDirectoryPermission::ACCESS_EDIT)
        ->assertJsonPath('data.activeDirectory.can_edit', true)
        ->assertJsonPath('data.activeDirectory.entries', [])
        ->json('data.activeDirectory.id');

    $projects = FileDirectory::query()->findOrFail($projectsId);

    expect($projects->owner_user_id)->toBe($owner->id)
        ->and($projects->sort_order)->toBe(10);

    $designsId = $this->postJson(route('mobile.v1.files.directories.store'), [
        'parent_id' => $projects->id,
        'name' => 'Designs',
    ])
        ->assertCreated()
        ->assertJsonPath('data.activeDirectory.name', 'Designs')
        ->assertJsonPath('data.activeDirectory.parent_id', $projects->id)
        ->json('data.activeDirectory.id');

    $this->getJson(route('mobile.v1.files.index', ['directory' => $designsId]))
        ->assertJsonPath('data.activeDirectory.breadcrumbs', [
            ['id' => $projects->id, 'name' => 'Projects'],
            ['id' => $designsId, 'name' => 'Designs'],
        ]);

    $this->postJson(route('mobile.v1.files.directories.store'), ['parent_id' => $projects->id, 'name' => 'designs'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->postJson(route('mobile.v1.files.directories.store'), ['name' => 'Reports/2026'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->postJson(route('mobile.v1.files.directories.store'), ['parent_id' => 999999, 'name' => 'Orphan'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->post(route('mobile.v1.files.entries.store'), [
        'directory_id' => $projects->id,
        'file' => UploadedFile::fake()->createWithContent('brief.txt', 'Project brief'),
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.files.file_uploaded_success', [], 'en'))
        ->assertJsonPath('data.activeDirectory.id', $projects->id)
        ->assertJsonCount(1, 'data.activeDirectory.entries')
        ->assertJsonPath('data.activeDirectory.entries.0.original_name', 'brief.txt')
        ->assertJsonPath('data.activeDirectory.entries.0.size_bytes', 13)
        ->assertJsonPath('data.activeDirectory.entries.0.extension', 'txt')
        ->assertJsonPath('data.activeDirectory.entries.0.owner_name', 'Olivia Owner');

    $brief = FileEntry::query()->where('original_name', 'brief.txt')->firstOrFail();

    Storage::disk('local')->assertExists($brief->path);

    expect($brief->file_directory_id)->toBe($projects->id)
        ->and($brief->owner_user_id)->toBe($owner->id)
        ->and($brief->mime_type)->toBe('text/plain')
        ->and($brief->size_bytes)->toBe(13)
        ->and(Storage::disk('local')->get($brief->path))->toBe('Project brief');

    $this->postJson(route('mobile.v1.files.entries.store'), [
        'directory_id' => $projects->id,
        'name' => ' notes.md ',
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.files.file_created_success', [], 'en'))
        ->assertJsonCount(2, 'data.activeDirectory.entries')
        ->assertJsonPath('data.activeDirectory.entries.1.original_name', 'notes.md')
        ->assertJsonPath('data.activeDirectory.entries.1.size_bytes', 0)
        ->assertJsonPath('data.activeDirectory.entries.1.mime_type', null)
        ->assertJsonPath('data.activeDirectory.entries.1.extension', 'md');

    Storage::disk('local')->assertExists(FileEntry::query()->where('original_name', 'notes.md')->firstOrFail()->path);

    $this->post(route('mobile.v1.files.entries.store'), [
        'directory_id' => $projects->id,
        'file' => UploadedFile::fake()->createWithContent('BRIEF.txt', 'Duplicate'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
    $this->postJson(route('mobile.v1.files.entries.store'), ['directory_id' => $projects->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->postJson(route('mobile.v1.files.entries.store'), ['name' => 'loose.txt'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('directory_id');

    $this->get(route('mobile.v1.files.entries.download', $brief))
        ->assertSuccessful()
        ->assertDownload('brief.txt')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $this->post(route('mobile.v1.files.entries.store'), [
        'directory_id' => $designsId,
        'file' => UploadedFile::fake()->createWithContent('Отчёт 2026.txt', 'Квартальный отчёт'),
    ])->assertCreated();

    $report = FileEntry::query()->where('original_name', 'Отчёт 2026.txt')->firstOrFail();

    $reportDownload = $this->get(route('mobile.v1.files.entries.download', $report))
        ->assertSuccessful()
        ->assertDownload();

    expect($reportDownload->headers->get('Content-Disposition'))->toContain("filename*=utf-8''".rawurlencode('Отчёт 2026.txt'))
        ->and($reportDownload->streamedContent())->toBe('Квартальный отчёт');

    $this->postJson(route('mobile.v1.files.directories.permissions.store', $projects), [
        'access_level' => FileDirectoryPermission::ACCESS_READ,
        'user_id' => $colleague->id,
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.files.permission_saved_success', [], 'en'))
        ->assertJsonPath('data.activeDirectory.id', $projects->id)
        ->assertJsonCount(1, 'data.activeDirectory.permissions')
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_type', 'user')
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_id', $colleague->id)
        ->assertJsonPath('data.activeDirectory.permissions.0.access_level', FileDirectoryPermission::ACCESS_READ)
        ->assertJsonPath('data.activeDirectory.permissions.0.granted_by_name', 'Olivia Owner');

    $this->postJson(route('mobile.v1.files.directories.permissions.store', $projects), [
        'access_level' => FileDirectoryPermission::ACCESS_EDIT,
        'user_id' => $colleague->id,
    ])
        ->assertOk()
        ->assertJsonPath('message', trans('ui.files.permission_saved_success', [], 'en'))
        ->assertJsonCount(1, 'data.activeDirectory.permissions')
        ->assertJsonPath('data.activeDirectory.permissions.0.access_level', FileDirectoryPermission::ACCESS_EDIT);

    $this->postJson(route('mobile.v1.files.directories.permissions.store', $projects), [
        'access_level' => FileDirectoryPermission::ACCESS_READ,
        'user_group_id' => $group->id,
    ])
        ->assertCreated()
        ->assertJsonCount(2, 'data.activeDirectory.permissions')
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_type', 'group')
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_id', $group->id)
        ->assertJsonPath('data.activeDirectory.permissions.0.subject_name', 'Finance');

    $this->postJson(route('mobile.v1.files.directories.permissions.store', $projects), [
        'access_level' => FileDirectoryPermission::ACCESS_READ,
        'user_id' => $colleague->id,
        'user_group_id' => $group->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id' => trans('ui.files.permission_target_required', [], 'en')]);
    $this->postJson(route('mobile.v1.files.directories.permissions.store', $projects), [
        'access_level' => 'owner',
        'user_id' => $colleague->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('access_level');

    $groupPermission = FileDirectoryPermission::query()
        ->where('file_directory_id', $projects->id)
        ->where('user_group_id', $group->id)
        ->firstOrFail();

    $this->deleteJson(route('mobile.v1.files.directories.permissions.destroy', [$projects, $foreignPermission]))->assertNotFound();
    $this->deleteJson(route('mobile.v1.files.directories.permissions.destroy', [$projects, $groupPermission]))
        ->assertOk()
        ->assertExactJson(['message' => trans('ui.files.permission_deleted_success', [], 'en')]);

    expect($groupPermission->fresh())->toBeNull()
        ->and($foreignPermission->fresh())->not->toBeNull();

    $this->withToken(mobileFilesFormsConferencesAs($colleague));

    $this->post(route('mobile.v1.files.entries.store'), [
        'directory_id' => $designsId,
        'file' => UploadedFile::fake()->createWithContent('mockup.png', 'png-bytes'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.activeDirectory.id', $designsId)
        ->assertJsonPath('data.activeDirectory.permission_level', FileDirectoryPermission::ACCESS_EDIT);

    $mockup = FileEntry::query()->where('original_name', 'mockup.png')->firstOrFail();

    $this->withToken(mobileFilesFormsConferencesAs($owner));

    $this->deleteJson(route('mobile.v1.files.entries.destroy', $brief))
        ->assertOk()
        ->assertExactJson(['message' => trans('ui.files.file_deleted_success', [], 'en')]);

    Storage::disk('local')->assertMissing($brief->path);

    expect($brief->fresh())->toBeNull();

    $this->deleteJson(route('mobile.v1.files.directories.destroy', $projects))
        ->assertOk()
        ->assertExactJson(['message' => trans('ui.files.directory_deleted_success', [], 'en')]);

    Storage::disk('local')->assertMissing($mockup->path);
    Storage::disk('local')->assertMissing($report->path);

    expect(FileDirectory::query()->whereKey([$projects->id, $designsId])->exists())->toBeFalse()
        ->and(FileEntry::query()->whereKey([$mockup->id, $report->id])->exists())->toBeFalse()
        ->and(FileDirectoryPermission::query()->where('file_directory_id', $projects->id)->exists())->toBeFalse();
});

test('the mobile forms index mirrors the web page props and opens the requested form', function () {
    $owner = User::factory()->create(['name' => 'Olga', 'last_name' => 'Owner', 'language' => 'en']);
    $target = User::factory()->create(['name' => 'Timur', 'last_name' => 'Target', 'language' => 'en']);
    $otherOwner = User::factory()->create(['language' => 'en']);

    $form = PortalForm::factory()->create([
        'owner_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'name' => 'Client intake',
        'description' => 'Collect client requests.',
        'submission_mode' => PortalForm::SUBMISSION_MODE_TASK,
        'is_active' => true,
    ]);

    PortalFormField::factory()->create([
        'portal_form_id' => $form->id,
        'key' => 'client_name',
        'label' => 'Client name',
        'type' => PortalFormField::TYPE_TEXT,
        'placeholder' => 'Jane Doe',
        'is_required' => true,
        'sort_order' => 10,
    ]);

    $submission = PortalFormSubmission::factory()->create([
        'portal_form_id' => $form->id,
        'target_user_id' => $target->id,
        'payload' => [[
            'field_id' => 1,
            'key' => 'client_name',
            'label' => 'Client name',
            'type' => PortalFormField::TYPE_TEXT,
            'value' => 'Acme Studio',
        ]],
    ]);

    $foreignForm = PortalForm::factory()->create(['owner_user_id' => $otherOwner->id, 'target_user_id' => $target->id]);

    $this->withToken(mobileFilesFormsConferencesAs($owner));

    $this->getJson(route('mobile.v1.forms.index'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.forms')
        ->assertJsonPath('data.forms.0.id', $form->id)
        ->assertJsonPath('data.forms.0.public_url', route('forms.public.show', ['portalForm' => $form->public_token]))
        ->assertJsonPath('data.forms.0.target_user_name', 'Timur Target')
        ->assertJsonPath('data.forms.0.fields_count', 1)
        ->assertJsonPath('data.forms.0.submissions_count', 1)
        ->assertJsonPath('data.activeForm', null)
        ->assertJsonPath('data.can', ['create' => true, 'manageActive' => false])
        ->assertJsonCount(4, 'data.formWidthOptions')
        ->assertJsonCount(3, 'data.formCompletionActionOptions')
        ->assertJsonPath('data.formStyleDefaults.container_width', 'lg')
        ->assertJsonPath('data.formCompletionDefaults.action', PortalForm::COMPLETION_ACTION_MESSAGE)
        ->assertJsonStructure([
            'data' => [
                'forms' => [[
                    'id', 'name', 'description', 'submission_mode', 'is_active', 'public_url', 'target_user_name',
                    'fields_count', 'submissions_count', 'style_settings', 'completion_settings', 'last_submission_at',
                ]],
                'activeForm',
                'availableUsers' => [['id', 'name', 'email']],
                'fieldTypes' => [['value', 'label']],
                'formStyleDefaults',
                'formWidthOptions' => [['value', 'label']],
                'formCompletionDefaults' => ['action', 'success_message', 'redirect_url'],
                'formCompletionActionOptions' => [['value', 'label']],
                'can' => ['create', 'manageActive'],
            ],
        ]);

    $this->getJson(route('mobile.v1.forms.index', ['form' => $form->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.activeForm.id', $form->id)
        ->assertJsonPath('data.activeForm.owner.name', 'Olga Owner')
        ->assertJsonPath('data.activeForm.target_user.id', $target->id)
        ->assertJsonPath('data.activeForm.fields.0.key', 'client_name')
        ->assertJsonPath('data.activeForm.submissions.0.id', $submission->id)
        ->assertJsonPath('data.activeForm.submissions.0.target_user_name', 'Timur Target')
        ->assertJsonPath('data.activeForm.submissions.0.payload.0.value', 'Acme Studio')
        ->assertJsonPath('data.can.manageActive', true);

    $this->getJson(route('mobile.v1.forms.index', ['form' => $foreignForm->id]))
        ->assertSuccessful()
        ->assertJsonPath('data.activeForm', null);

    $webProps = $this->actingAs($owner)
        ->get(route('forms.index', ['form' => $form->id]))
        ->assertSuccessful()
        ->inertiaProps();

    app('auth')->forgetGuards();

    $mobileData = $this->getJson(route('mobile.v1.forms.index', ['form' => $form->id]))->json('data');

    expect(array_keys($mobileData))->toBe([
        'forms',
        'activeForm',
        'availableUsers',
        'fieldTypes',
        'formStyleDefaults',
        'formWidthOptions',
        'formCompletionDefaults',
        'formCompletionActionOptions',
        'can',
    ])
        ->and($mobileData)->toEqual(Arr::only($webProps, array_keys($mobileData)));
});

test('the mobile form detail exposes fields the public link and the latest submissions of a managed form', function () {
    $owner = User::factory()->create(['name' => 'Olga', 'last_name' => 'Owner', 'language' => 'en']);
    $target = User::factory()->create(['name' => 'Timur', 'last_name' => 'Target', 'language' => 'en']);
    $otherOwner = User::factory()->create(['language' => 'en']);

    $form = PortalForm::factory()->create([
        'owner_user_id' => $owner->id,
        'target_user_id' => $target->id,
        'name' => 'Website brief',
        'description' => 'Tell us about your project.',
        'submission_mode' => PortalForm::SUBMISSION_MODE_CHAT,
        'is_active' => true,
        'style_settings' => ['container_width' => 'md', 'background_color' => '#fff7ed'],
        'completion_settings' => ['action' => PortalForm::COMPLETION_ACTION_REDIRECT, 'redirect_url' => '/thank-you'],
    ]);

    $companyField = PortalFormField::factory()->create([
        'portal_form_id' => $form->id,
        'key' => 'company_name',
        'label' => 'Company name',
        'type' => PortalFormField::TYPE_TEXT,
        'placeholder' => 'Acme',
        'is_required' => true,
        'sort_order' => 10,
    ]);
    PortalFormField::factory()->create([
        'portal_form_id' => $form->id,
        'key' => 'budget',
        'label' => 'Budget',
        'type' => PortalFormField::TYPE_NUMBER,
        'placeholder' => null,
        'is_required' => false,
        'sort_order' => 20,
    ]);

    $submissions = collect(range(1, PortalFormManager::RECENT_SUBMISSIONS_LIMIT + 1))->map(fn (int $index): PortalFormSubmission => PortalFormSubmission::factory()->create([
        'portal_form_id' => $form->id,
        'target_user_id' => $target->id,
        'chat_conversation_id' => null,
        'payload' => [[
            'field_id' => $companyField->id,
            'key' => 'company_name',
            'label' => 'Company name',
            'type' => PortalFormField::TYPE_TEXT,
            'value' => 'Company '.$index,
        ]],
        'created_at' => now()->subMinutes(100 - $index),
    ]));

    $foreignForm = PortalForm::factory()->create(['owner_user_id' => $otherOwner->id, 'target_user_id' => $target->id]);

    $this->withToken(mobileFilesFormsConferencesAs($owner));

    $response = $this->getJson(route('mobile.v1.forms.show', $form))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $form->id)
        ->assertJsonPath('data.name', 'Website brief')
        ->assertJsonPath('data.description', 'Tell us about your project.')
        ->assertJsonPath('data.submission_mode', PortalForm::SUBMISSION_MODE_CHAT)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.public_url', route('forms.public.show', ['portalForm' => $form->public_token]))
        ->assertJsonPath('data.style_settings.container_width', 'md')
        ->assertJsonPath('data.style_settings.background_color', '#FFF7ED')
        ->assertJsonPath('data.style_settings.border_radius', PortalForm::DEFAULT_STYLE_SETTINGS['border_radius'])
        ->assertJsonPath('data.completion_settings', [
            'action' => PortalForm::COMPLETION_ACTION_REDIRECT,
            'success_message' => null,
            'redirect_url' => '/thank-you',
        ])
        ->assertJsonPath('data.target_user', ['id' => $target->id, 'name' => 'Timur Target', 'email' => $target->email])
        ->assertJsonPath('data.target_user_name', 'Timur Target')
        ->assertJsonPath('data.owner', ['id' => $owner->id, 'name' => 'Olga Owner', 'email' => $owner->email])
        ->assertJsonPath('data.fields_count', 2)
        ->assertJsonPath('data.fields.0', [
            'id' => $companyField->id,
            'key' => 'company_name',
            'label' => 'Company name',
            'type' => PortalFormField::TYPE_TEXT,
            'placeholder' => 'Acme',
            'is_required' => true,
            'sort_order' => 10,
        ])
        ->assertJsonPath('data.fields.1.key', 'budget')
        ->assertJsonPath('data.submissions_count', PortalFormManager::RECENT_SUBMISSIONS_LIMIT + 1)
        ->assertJsonCount(PortalFormManager::RECENT_SUBMISSIONS_LIMIT, 'data.submissions')
        ->assertJsonPath('data.submissions.0.id', $submissions->last()->id)
        ->assertJsonPath('data.submissions.0.target_user_name', 'Timur Target')
        ->assertJsonPath('data.submissions.0.payload.0', [
            'field_id' => $companyField->id,
            'key' => 'company_name',
            'label' => 'Company name',
            'type' => PortalFormField::TYPE_TEXT,
            'value' => 'Company '.(PortalFormManager::RECENT_SUBMISSIONS_LIMIT + 1),
        ])
        ->assertJsonPath('data.can.manage', true)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'description', 'submission_mode', 'is_active', 'public_url', 'target_user_name',
                'fields_count', 'submissions_count', 'style_settings', 'completion_settings', 'last_submission_at',
                'target_user' => ['id', 'name', 'email'],
                'owner' => ['id', 'name', 'email'],
                'fields' => [['id', 'key', 'label', 'type', 'placeholder', 'is_required', 'sort_order']],
                'submissions' => [[
                    'id', 'created_at', 'project_task_id', 'chat_conversation_id', 'chat_message_id', 'target_user_name',
                    'payload' => [['field_id', 'key', 'label', 'type', 'value']],
                ]],
                'can' => ['manage'],
            ],
        ]);

    expect($response->json('data.public_url'))->toStartWith('http')
        ->and($response->json('data.last_submission_at'))->toBe($submissions->last()->created_at->toISOString())
        ->and(collect($response->json('data.submissions'))->pluck('id')->all())
        ->not->toContain($submissions->first()->id);

    $this->getJson(route('mobile.v1.forms.show', $foreignForm))->assertNotFound();
    $this->patchJson(route('mobile.v1.forms.update', $foreignForm), mobileFilesFormsConferencesFormPayload($target))->assertNotFound();
    $this->deleteJson(route('mobile.v1.forms.destroy', $foreignForm))->assertNotFound();
    $this->getJson(route('mobile.v1.forms.show', 999999))->assertNotFound();

    expect($foreignForm->fresh()->name)->not->toBe('Client intake');

    $superAdmin = mobileFilesFormsConferencesSuperAdmin();

    $this->withToken(mobileFilesFormsConferencesAs($superAdmin))
        ->getJson(route('mobile.v1.forms.show', $foreignForm))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $foreignForm->id)
        ->assertJsonPath('data.submissions', [])
        ->assertJsonPath('data.can.manage', true);
});

test('form owners can create update and delete forms through the mobile api', function () {
    $owner = User::factory()->create(['language' => 'en']);
    $target = User::factory()->create(['language' => 'en']);
    $nextTarget = User::factory()->create(['language' => 'en']);

    $this->withToken(mobileFilesFormsConferencesAs($owner));

    $response = $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($target))
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.forms.created_success', [], 'en'))
        ->assertJsonPath('data.name', 'Client intake')
        ->assertJsonPath('data.description', "Collect a short summary\nand contact details.")
        ->assertJsonPath('data.submission_mode', PortalForm::SUBMISSION_MODE_TASK)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.style_settings.container_width', 'xl')
        ->assertJsonPath('data.style_settings.background_color', '#FEF3C7')
        ->assertJsonPath('data.completion_settings.success_message', 'Thank you! We will contact you soon.')
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonPath('data.target_user.id', $target->id)
        ->assertJsonPath('data.fields_count', 2)
        ->assertJsonPath('data.fields.0.key', 'client_name')
        ->assertJsonPath('data.fields.0.sort_order', 10)
        ->assertJsonPath('data.fields.1.key', 'request_details')
        ->assertJsonPath('data.fields.1.placeholder', null)
        ->assertJsonPath('data.fields.1.is_required', false)
        ->assertJsonPath('data.submissions', [])
        ->assertJsonPath('data.submissions_count', 0)
        ->assertJsonPath('data.last_submission_at', null)
        ->assertJsonPath('data.can.manage', true);

    $form = PortalForm::query()->findOrFail($response->json('data.id'));

    expect($form->owner_user_id)->toBe($owner->id)
        ->and($form->public_token)->toHaveLength(32)
        ->and($response->json('data.public_url'))->toBe(route('forms.public.show', ['portalForm' => $form->public_token]))
        ->and($form->fields()->pluck('label')->all())->toBe(['Client name', 'Request details']);

    $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($target, ['fields' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fields');
    $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($target, [
        'completion_settings' => ['action' => PortalForm::COMPLETION_ACTION_REDIRECT, 'redirect_url' => null],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('completion_settings.redirect_url');
    $this->postJson(route('mobile.v1.forms.store'), mobileFilesFormsConferencesFormPayload($owner, [
        'submission_mode' => PortalForm::SUBMISSION_MODE_CHAT,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target_user_id' => trans('ui.forms.validation_target_user_chat', [], 'en')]);

    $clientNameField = $form->fields()->where('key', 'client_name')->firstOrFail();
    $requestDetailsField = $form->fields()->where('key', 'request_details')->firstOrFail();

    $this->patchJson(route('mobile.v1.forms.update', $form), mobileFilesFormsConferencesFormPayload($nextTarget, [
        'name' => 'Lead capture',
        'description' => null,
        'submission_mode' => PortalForm::SUBMISSION_MODE_CHAT,
        'is_active' => false,
        'style_settings' => ['container_width' => 'sm', 'padding' => 24],
        'completion_settings' => ['action' => PortalForm::COMPLETION_ACTION_REDIRECT, 'redirect_url' => 'https://example.com/thanks'],
        'fields' => [
            [
                'id' => $clientNameField->id,
                'label' => 'Full name',
                'type' => PortalFormField::TYPE_TEXT,
                'placeholder' => 'Jane Doe',
                'is_required' => true,
            ],
            [
                'label' => 'Project budget',
                'type' => PortalFormField::TYPE_NUMBER,
                'placeholder' => '1000',
                'is_required' => false,
            ],
        ],
    ]))
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.forms.updated_success', [], 'en'))
        ->assertJsonPath('data.id', $form->id)
        ->assertJsonPath('data.name', 'Lead capture')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.submission_mode', PortalForm::SUBMISSION_MODE_CHAT)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.target_user.id', $nextTarget->id)
        ->assertJsonPath('data.style_settings.container_width', 'sm')
        ->assertJsonPath('data.style_settings.padding', 24)
        ->assertJsonPath('data.completion_settings.redirect_url', 'https://example.com/thanks')
        ->assertJsonPath('data.fields_count', 2)
        ->assertJsonPath('data.fields.0.id', $clientNameField->id)
        ->assertJsonPath('data.fields.0.key', 'client_name')
        ->assertJsonPath('data.fields.0.label', 'Full name')
        ->assertJsonPath('data.fields.1.key', 'project_budget')
        ->assertJsonPath('data.fields.1.type', PortalFormField::TYPE_NUMBER);

    expect($requestDetailsField->fresh())->toBeNull()
        ->and($form->fresh()->public_token)->toBe($form->public_token);

    $this->patchJson(route('mobile.v1.forms.update', $form), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'submission_mode', 'target_user_id', 'fields']);

    $this->deleteJson(route('mobile.v1.forms.destroy', $form))
        ->assertSuccessful()
        ->assertExactJson(['message' => trans('ui.forms.deleted_success', [], 'en')]);

    expect(PortalForm::query()->whereKey($form->id)->exists())->toBeFalse()
        ->and(PortalFormField::query()->where('portal_form_id', $form->id)->exists())->toBeFalse();
});

test('the mobile conferences index mirrors the web page props', function () {
    $creator = User::factory()->create(['name' => 'Carla', 'last_name' => 'Creator', 'language' => 'en']);
    $invitee = User::factory()->create(['language' => 'en']);

    $live = Conference::factory()->create([
        'title' => 'Daily standup',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subMinutes(15),
    ]);
    $upcoming = Conference::factory()->withoutExternalGuests()->create([
        'title' => 'Planning',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->addDay(),
    ]);
    $past = Conference::factory()->ended()->create([
        'title' => 'Retrospective',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subDay(),
    ]);
    Conference::factory()->create(['title' => 'Hidden meeting']);

    ConferenceInvitation::factory()->create([
        'conference_id' => $live->id,
        'user_id' => $invitee->id,
        'invited_by_user_id' => $creator->id,
    ]);

    $this->withToken(mobileFilesFormsConferencesAs($creator));

    $this->getJson(route('mobile.v1.conferences.index'))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data.conferences')
        ->assertJsonPath('data.conferenceGroups.current.0.id', $live->id)
        ->assertJsonPath('data.conferenceGroups.current.0.status', Conference::STATUS_LIVE)
        ->assertJsonPath('data.conferenceGroups.current.0.invited_users_count', 1)
        ->assertJsonPath('data.conferenceGroups.current.0.external_join_url', route('conferences.public.show', ['conference' => $live->public_token]))
        ->assertJsonPath('data.conferenceGroups.current.0.creator.id', $creator->id)
        ->assertJsonPath('data.conferenceGroups.upcoming.0.id', $upcoming->id)
        ->assertJsonPath('data.conferenceGroups.upcoming.0.external_join_url', null)
        ->assertJsonPath('data.conferenceGroups.past.0.id', $past->id)
        ->assertJsonPath('data.conferenceGroups.past.0.status', Conference::STATUS_ENDED)
        ->assertJsonPath('data.provider.label', config('conference.provider_label'))
        ->assertJsonStructure([
            'data' => [
                'conferences' => [[
                    'id', 'title', 'description', 'starts_at', 'ended_at', 'status', 'allow_external_guests',
                    'external_join_url', 'invited_users_count', 'creator' => ['id', 'name', 'last_name', 'email'],
                ]],
                'conferenceGroups' => ['current', 'upcoming', 'past'],
                'availableUsers' => [['id', 'name', 'last_name', 'email', 'avatar', 'avatar_position_x', 'avatar_position_y', 'avatar_scale']],
                'provider' => ['label'],
            ],
        ]);

    $webProps = $this->actingAs($creator)
        ->get(route('conferences.index'))
        ->assertSuccessful()
        ->inertiaProps();

    app('auth')->forgetGuards();

    $mobileData = $this->getJson(route('mobile.v1.conferences.index'))->json('data');

    expect(array_keys($mobileData))->toBe(['conferences', 'conferenceGroups', 'availableUsers', 'provider'])
        ->and($mobileData)->toEqual(Arr::only($webProps, array_keys($mobileData)));

    $this->withToken(mobileFilesFormsConferencesAs($invitee))
        ->getJson(route('mobile.v1.conferences.index'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.conferences')
        ->assertJsonPath('data.conferences.0.id', $live->id);
});

test('conference creators can create show invite and end conferences through the mobile api', function () {
    $creator = User::factory()->create(['name' => 'Carla', 'last_name' => 'Creator', 'language' => 'en']);
    $invitee = User::factory()->create(['language' => 'en']);
    $secondInvitee = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $startsAt = now()->addHours(2)->startOfMinute();

    $this->withToken(mobileFilesFormsConferencesAs($creator));

    $response = $this->postJson(route('mobile.v1.conferences.store'), [
        'title' => '  Weekly sync  ',
        'description' => 'Team status updates.',
        'starts_at' => $startsAt->toIso8601ZuluString(),
        'allow_external_guests' => true,
        'invited_user_ids' => [$invitee->id, $creator->id],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.conferences.created_success', [], 'en'))
        ->assertJsonPath('data.title', 'Weekly sync')
        ->assertJsonPath('data.description', 'Team status updates.')
        ->assertJsonPath('data.starts_at', $startsAt->toISOString())
        ->assertJsonPath('data.ended_at', null)
        ->assertJsonPath('data.status', Conference::STATUS_SCHEDULED)
        ->assertJsonPath('data.allow_external_guests', true)
        ->assertJsonPath('data.invited_users_count', 1)
        ->assertJsonPath('data.invited_users.0.user.id', $invitee->id)
        ->assertJsonPath('data.creator.id', $creator->id)
        ->assertJsonPath('data.can', ['manage' => true, 'invite' => true]);

    $conference = Conference::query()->findOrFail($response->json('data.id'));

    expect($response->json('data.web_path'))->toBe('/conferences/'.$conference->id)
        ->and($response->json('data.public_url'))->toBe(route('conferences.public.show', ['conference' => $conference->public_token]))
        ->and($response->json('data.external_join_url'))->toBe($response->json('data.public_url'))
        ->and($response->json('data.room_key'))->toBe($conference->public_token)
        ->and($response->json('data.room_name'))->toBe($conference->room_name)
        ->and($conference->created_by_user_id)->toBe($creator->id)
        ->and($invitee->notifications()->count())->toBe(1);

    $this->postJson(route('mobile.v1.conferences.store'), ['title' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    $privateResponse = $this->postJson(route('mobile.v1.conferences.store'), [
        'title' => 'Board meeting',
        'allow_external_guests' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', Conference::STATUS_LIVE)
        ->assertJsonPath('data.external_join_url', null)
        ->assertJsonPath('data.public_url', null)
        ->assertJsonPath('data.invited_users', []);

    expect($privateResponse->json('data.web_path'))->toBe('/conferences/'.$privateResponse->json('data.id'));

    $this->getJson(route('mobile.v1.conferences.show', $conference))
        ->assertSuccessful()
        ->assertJsonPath('data.conference.id', $conference->id)
        ->assertJsonPath('data.conference.web_path', '/conferences/'.$conference->id)
        ->assertJsonPath('data.conference.public_url', route('conferences.public.show', ['conference' => $conference->public_token]))
        ->assertJsonPath('data.conference.provider_label', config('conference.provider_label'))
        ->assertJsonCount(1, 'data.conference.invited_users')
        ->assertJsonCount(2, 'data.conferences')
        ->assertJsonStructure([
            'data' => [
                'conference' => [
                    'id', 'title', 'description', 'starts_at', 'ended_at', 'status', 'allow_external_guests',
                    'external_join_url', 'invited_users_count', 'creator', 'public_token', 'room_name',
                    'provider_label', 'room_key', 'can' => ['manage', 'invite'],
                    'invited_users' => [['id', 'invited_at', 'joined_at', 'last_opened_at', 'user' => ['id', 'name', 'last_name', 'email', 'avatar']]],
                    'web_path', 'public_url',
                ],
                'conferences',
                'conferenceGroups' => ['current', 'upcoming', 'past'],
                'availableUsers',
                'provider' => ['label'],
            ],
        ]);

    $webProps = $this->actingAs($creator)
        ->get(route('conferences.show', $conference))
        ->assertSuccessful()
        ->inertiaProps();

    app('auth')->forgetGuards();

    $mobileData = $this->getJson(route('mobile.v1.conferences.show', $conference))->json('data');
    $mobileData['conference'] = Arr::except($mobileData['conference'], ['web_path', 'public_url']);

    expect(array_keys($mobileData))->toBe(['conference', 'conferences', 'conferenceGroups', 'availableUsers', 'provider'])
        ->and($mobileData)->toEqual(Arr::only($webProps, array_keys($mobileData)));

    $this->getJson(route('mobile.v1.conferences.show', 999999))->assertNotFound();

    $this->withToken(mobileFilesFormsConferencesAs($invitee));

    $this->getJson(route('mobile.v1.conferences.show', $conference))
        ->assertSuccessful()
        ->assertJsonPath('data.conference.can', ['manage' => false, 'invite' => false])
        ->assertJsonPath('data.conference.web_path', '/conferences/'.$conference->id);
    $this->postJson(route('mobile.v1.conferences.invitations.store', $conference), [
        'invited_user_ids' => [$outsider->id],
    ])->assertForbidden();
    $this->patchJson(route('mobile.v1.conferences.end', $conference))->assertForbidden();

    $this->withToken(mobileFilesFormsConferencesAs($outsider));

    $this->getJson(route('mobile.v1.conferences.show', $conference))->assertNotFound();
    $this->patchJson(route('mobile.v1.conferences.end', $conference))->assertForbidden();

    $this->withToken(mobileFilesFormsConferencesAs($creator));

    $this->postJson(route('mobile.v1.conferences.invitations.store', $conference), [
        'invited_user_ids' => [$invitee->id, $secondInvitee->id],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.conferences.invited_success', [], 'en'))
        ->assertJsonPath('invited_count', 1)
        ->assertJsonPath('data.id', $conference->id)
        ->assertJsonPath('data.invited_users_count', 2)
        ->assertJsonCount(2, 'data.invited_users')
        ->assertJsonPath('data.web_path', '/conferences/'.$conference->id);

    expect($secondInvitee->notifications()->count())->toBe(1)
        ->and($invitee->notifications()->count())->toBe(1);

    $this->postJson(route('mobile.v1.conferences.invitations.store', $conference), [
        'invited_user_ids' => [$invitee->id, $secondInvitee->id],
    ])
        ->assertOk()
        ->assertJsonPath('message', trans('ui.conferences.invited_already', [], 'en'))
        ->assertJsonPath('invited_count', 0)
        ->assertJsonCount(2, 'data.invited_users');

    $this->postJson(route('mobile.v1.conferences.invitations.store', $conference), ['invited_user_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('invited_user_ids');

    $firstParticipant = ConferenceParticipant::factory()->create(['conference_id' => $conference->id]);
    $secondParticipant = ConferenceParticipant::factory()->create(['conference_id' => $conference->id]);
    ConferenceSignal::factory()->create([
        'conference_id' => $conference->id,
        'sender_participant_id' => $firstParticipant->id,
        'recipient_participant_id' => $secondParticipant->id,
    ]);

    $this->patchJson(route('mobile.v1.conferences.end', $conference))
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.conferences.ended_success', [], 'en'))
        ->assertJsonPath('data.id', $conference->id)
        ->assertJsonPath('data.status', Conference::STATUS_ENDED)
        ->assertJsonPath('data.web_path', '/conferences/'.$conference->id);

    expect($conference->fresh()->ended_at)->not->toBeNull()
        ->and($firstParticipant->fresh()->left_at)->not->toBeNull()
        ->and($secondParticipant->fresh()->left_at)->not->toBeNull()
        ->and(ConferenceSignal::query()->where('conference_id', $conference->id)->exists())->toBeFalse();

    $this->getJson(route('mobile.v1.conferences.show', $conference))
        ->assertSuccessful()
        ->assertJsonPath('data.conference.status', Conference::STATUS_ENDED)
        ->assertJsonPath('data.conference.ended_at', $conference->fresh()->ended_at->toISOString());
});

test('mobile conference endpoints return service unavailable when the conference tables are missing', function () {
    $superAdmin = mobileFilesFormsConferencesSuperAdmin();
    $token = mobileFilesFormsConferencesAs($superAdmin);

    Schema::drop('conference_messages');
    Schema::drop('conference_signals');
    Schema::drop('conference_participants');
    Schema::drop('conference_invitations');
    Schema::drop('conferences');

    $this->withToken($token);

    $this->getJson(route('mobile.v1.conferences.index'))
        ->assertServiceUnavailable()
        ->assertJsonPath('message', trans('ui.conferences.module_not_ready', [], 'en'));
    $this->getJson(route('mobile.v1.conferences.show', 1))->assertServiceUnavailable();
    $this->postJson(route('mobile.v1.conferences.store'), ['title' => 'Unavailable'])->assertServiceUnavailable();
    $this->postJson(route('mobile.v1.conferences.invitations.store', 1), ['invited_user_ids' => [$superAdmin->id]])->assertServiceUnavailable();
    $this->patchJson(route('mobile.v1.conferences.end', 1))->assertServiceUnavailable();
});

test('edo documents can be listed created shown updated shared and deleted with a mobile token', function () {
    Storage::fake('local');

    $user = User::factory()->create(['language' => 'en']);
    $directory = FileDirectory::factory()->create(['owner_user_id' => $user->id, 'name' => 'Contracts']);

    Storage::disk('local')->put('files/'.$directory->id.'/master-agreement.pdf', 'master-agreement-body');

    $fileEntry = FileEntry::factory()->create([
        'file_directory_id' => $directory->id,
        'owner_user_id' => $user->id,
        'original_name' => 'master-agreement.pdf',
        'path' => 'files/'.$directory->id.'/master-agreement.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 21,
    ]);

    $this->withToken(mobileFilesFormsConferencesAs($user))->withHeader('Accept', 'application/json');

    $response = $this->post(route('mobile.v1.edo.store'), [
        'title' => 'Supply agreement',
        'external_reference' => 'EDO-2026-0042',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '1234 5678 9012',
        'counterparty_email' => 'legal@acme.test',
        'document_source' => EdoDocument::SOURCE_UPLOAD,
        'document_upload' => UploadedFile::fake()->createWithContent('supply-agreement.pdf', '%PDF-1.4 supply'),
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.edo.created_success', [], 'en'))
        ->assertJsonPath('data.title', 'Supply agreement')
        ->assertJsonPath('data.counterparty_identifier', '123456789012')
        ->assertJsonPath('data.document_source', EdoDocument::SOURCE_UPLOAD)
        ->assertJsonPath('data.document_file.original_name', 'supply-agreement.pdf')
        ->assertJsonPath('data.document_file.size_bytes', 15)
        ->assertJsonPath('data.status', EdoDocument::STATUS_DRAFT)
        ->assertJsonPath('data.has_active_public_link', false)
        ->assertJsonPath('data.creator.id', $user->id);

    $document = EdoDocument::query()->findOrFail($response->json('data.id'));
    $uploadedPath = (string) $document->document_file_path;

    Storage::disk('local')->assertExists($uploadedPath);

    expect($response->json('data.document_file.download_url'))->toBe(route('edo.file.download', $document))
        ->and($response->json('data.document_file.mobile_download_url'))->toBe(route('mobile.v1.edo.file.download', $document));

    $this->postJson(route('mobile.v1.edo.store'), [
        'title' => 'Copied agreement',
        'counterparty_name' => 'Beta LLP',
        'counterparty_identifier' => '123456789013',
        'document_source' => EdoDocument::SOURCE_FILE_ENTRY,
        'selected_file_entry_id' => $fileEntry->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.document_source', EdoDocument::SOURCE_FILE_ENTRY)
        ->assertJsonPath('data.source_file_entry_id', $fileEntry->id)
        ->assertJsonPath('data.document_file.original_name', 'master-agreement.pdf');

    $this->postJson(route('mobile.v1.edo.store'), [
        'title' => '',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '123',
        'document_source' => EdoDocument::SOURCE_UPLOAD,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'counterparty_identifier']);
    $this->postJson(route('mobile.v1.edo.store'), [
        'title' => 'Missing upload',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '123456789012',
        'document_source' => EdoDocument::SOURCE_UPLOAD,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_upload');

    $this->getJson(route('mobile.v1.edo.index'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id', 'title', 'external_reference', 'counterparty_name', 'counterparty_identifier', 'content',
                'document_source', 'source_file_entry_id', 'document_file', 'status', 'public_sign_url',
                'public_sign_expires_at', 'has_active_public_link', 'signature_subject', 'signature_serial_number',
                'signature_algorithm', 'signed_payload_hash', 'signature_metadata', 'signed_at', 'metadata',
                'created_at', 'updated_at', 'creator', 'updater',
            ]],
        ]);

    $this->getJson(route('mobile.v1.edo.show', $document))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $document->id)
        ->assertJsonPath('data.external_reference', 'EDO-2026-0042');

    $download = $this->get(route('mobile.v1.edo.file.download', $document))
        ->assertSuccessful()
        ->assertDownload('supply-agreement.pdf');

    expect($download->streamedContent())->toBe('%PDF-1.4 supply');

    $this->post(route('mobile.v1.edo.update', $document), [
        '_method' => 'PATCH',
        'title' => 'Supply agreement v2',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '123456789012',
        'document_source' => EdoDocument::SOURCE_UPLOAD,
        'document_upload' => UploadedFile::fake()->createWithContent('supply-agreement-v2.pdf', '%PDF-1.4 supply v2'),
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.edo.updated_success', [], 'en'))
        ->assertJsonPath('data.title', 'Supply agreement v2')
        ->assertJsonPath('data.document_file.original_name', 'supply-agreement-v2.pdf');

    Storage::disk('local')->assertMissing($uploadedPath);

    $this->postJson(route('mobile.v1.edo.public-link.store', $document))
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.edo.public_link_created', [], 'en'))
        ->assertJsonPath('data.status', EdoDocument::STATUS_PENDING_SIGNATURE)
        ->assertJsonPath('data.has_active_public_link', true);

    expect($document->fresh()->publicShowUrl())->not->toBeNull();

    $this->patchJson(route('mobile.v1.edo.update', $document), [
        'title' => 'Supply agreement v3',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '123456789012',
        'document_source' => EdoDocument::SOURCE_TEXT,
        'content' => 'Plain text agreement body.',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.document_source', EdoDocument::SOURCE_TEXT)
        ->assertJsonPath('data.content', 'Plain text agreement body.')
        ->assertJsonPath('data.document_file', null)
        ->assertJsonPath('data.status', EdoDocument::STATUS_DRAFT)
        ->assertJsonPath('data.has_active_public_link', false);

    $this->get(route('mobile.v1.edo.file.download', $document))->assertNotFound();

    $this->deleteJson(route('mobile.v1.edo.destroy', $document))
        ->assertSuccessful()
        ->assertExactJson([
            'message' => trans('ui.edo.deleted_success', [], 'en'),
            'data' => ['id' => $document->id],
        ]);

    expect(EdoDocument::query()->whereKey($document->id)->exists())->toBeFalse();
});

test('mobile edo access follows the web visibility rules', function () {
    Storage::fake('local');

    $author = User::factory()->create(['language' => 'en']);
    $colleague = User::factory()->create(['language' => 'en']);
    $auditor = mobileFilesFormsConferencesGroupUser([UserGroup::PERMISSION_VIEW_USERS]);

    $document = EdoDocument::factory()->create([
        'created_by_user_id' => $author->id,
        'updated_by_user_id' => $author->id,
        'document_source' => EdoDocument::SOURCE_UPLOAD,
        'document_file_name' => 'Договор поставки.pdf',
        'document_file_disk' => 'local',
        'document_file_path' => 'edo-documents/1/contract.pdf',
        'document_file_mime_type' => 'application/pdf',
        'document_file_size_bytes' => 13,
        'document_file_hash' => hash('sha256', 'contract-body'),
    ]);
    $textDocument = EdoDocument::factory()->create([
        'created_by_user_id' => $author->id,
        'updated_by_user_id' => $author->id,
    ]);
    $signedDocument = EdoDocument::factory()->signed()->create([
        'created_by_user_id' => $author->id,
        'updated_by_user_id' => $author->id,
    ]);

    Storage::disk('local')->put('edo-documents/1/contract.pdf', 'contract-body');

    $this->withToken(mobileFilesFormsConferencesAs($colleague));

    $this->getJson(route('mobile.v1.edo.index'))
        ->assertSuccessful()
        ->assertJsonPath('data', []);
    $this->getJson(route('mobile.v1.edo.show', $document))->assertNotFound();
    $this->get(route('mobile.v1.edo.file.download', $document))->assertNotFound();
    $this->patchJson(route('mobile.v1.edo.update', $textDocument), [
        'title' => 'Hijacked',
        'counterparty_name' => 'Acme LLP',
        'counterparty_identifier' => '123456789012',
        'content' => 'Hijacked body.',
    ])->assertNotFound();
    $this->postJson(route('mobile.v1.edo.public-link.store', $textDocument))->assertNotFound();
    $this->deleteJson(route('mobile.v1.edo.destroy', $textDocument))->assertNotFound();

    $this->withToken(mobileFilesFormsConferencesAs($auditor));

    $this->getJson(route('mobile.v1.edo.index'))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');

    $download = $this->get(route('mobile.v1.edo.file.download', $document))
        ->assertSuccessful()
        ->assertDownload();

    expect($download->headers->get('Content-Disposition'))->toContain("filename*=utf-8''".rawurlencode('Договор поставки.pdf'))
        ->and($download->streamedContent())->toBe('contract-body');

    $this->withToken(mobileFilesFormsConferencesAs($author));

    $this->get(route('mobile.v1.edo.file.download', $textDocument))->assertNotFound();
    $this->postJson(route('mobile.v1.edo.public-link.store', $signedDocument))
        ->assertUnprocessable()
        ->assertJsonPath('message', trans('ui.edo.already_signed', [], 'en'));

    expect($textDocument->fresh()->title)->not->toBe('Hijacked')
        ->and(EdoDocument::query()->count())->toBe(3);
});
