<?php

use App\Models\ApiAccessToken;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function directoriesApiSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'directories-api-admin@example.com']);

    return User::factory()->create([
        'email' => 'directories-api-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

function issueDirectoriesApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Directories API token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

/**
 * @return array<string, string>
 */
function directoriesApiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

/**
 * @return array<string, mixed>
 */
function directoriesApiPayload(string $name = 'Suppliers', string $slug = 'suppliers'): array
{
    return [
        'name' => $name,
        'slug' => $slug,
        'description' => 'Supplier reference directory.',
        'columns' => [
            [
                'label' => 'Company',
                'key' => 'company',
                'type' => 'text',
                'is_required' => true,
            ],
            [
                'label' => 'Rating',
                'key' => 'rating',
                'type' => 'number',
                'is_required' => false,
            ],
            [
                'label' => 'Approved',
                'key' => 'approved',
                'type' => 'boolean',
                'is_required' => true,
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function directoriesApiRecordPayload(
    string $company = 'Northwind Trade',
    string $rating = '5',
    bool $approved = true,
): array {
    return [
        'values' => [
            'company' => $company,
            'rating' => $rating,
            'approved' => $approved,
        ],
    ];
}

test('api settings include directories documentation and permissions', function () {
    $admin = directoriesApiSuperAdmin();

    $this->actingAs($admin)
        ->get(route('settings.api.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiDocumentation')
            ->where('documentation', fn ($documentation): bool => collect($documentation)->contains(
                fn (array $section): bool => $section['title'] === __('ui.api.section_directories')
                    && collect($section['endpoints'])->contains(
                        fn (array $endpoint): bool => $endpoint['path'] === '/api/v1/directories'
                            && $endpoint['permission'] === ApiAccessToken::PERMISSION_DIRECTORIES_READ
                    )
                    && collect($section['endpoints'])->contains(
                        fn (array $endpoint): bool => $endpoint['path'] === '/api/v1/directories/{referenceDirectory}/records'
                            && $endpoint['permission'] === ApiAccessToken::PERMISSION_DIRECTORIES_WRITE
                    )
            ))
        );

    $this->actingAs($admin)
        ->get(route('settings.api.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Api')
            ->where('permissions', fn ($permissions): bool => collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === ApiAccessToken::PERMISSION_DIRECTORIES_READ
            ) && collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === ApiAccessToken::PERMISSION_DIRECTORIES_WRITE
            ))
        );
});

test('api tokens can manage directories and their records', function () {
    $admin = directoriesApiSuperAdmin();

    $token = issueDirectoriesApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_DIRECTORIES_READ,
        ApiAccessToken::PERMISSION_DIRECTORIES_WRITE,
    ]);

    $existingDirectory = ReferenceDirectory::factory()->create([
        'name' => 'Existing directory',
        'slug' => 'existing-directory',
    ]);

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->getJson(route('api.v1.directories.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Existing directory');

    $createResponse = $this->withHeaders(directoriesApiHeadersFor($token))
        ->postJson(route('api.v1.directories.store'), directoriesApiPayload())
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.created_success'))
        ->assertJsonPath('data.slug', 'suppliers')
        ->assertJsonPath('data.records_count', 0);

    $directoryId = $createResponse->json('data.id');

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->getJson(route('api.v1.directories.show', $directoryId))
        ->assertOk()
        ->assertJsonPath('data.id', $directoryId)
        ->assertJsonPath('data.columns.0.key', 'company')
        ->assertJsonPath('data.records', []);

    $createRecordResponse = $this->withHeaders(directoriesApiHeadersFor($token))
        ->postJson(route('api.v1.directories.records.store', $directoryId), directoriesApiRecordPayload())
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.record_created_success'))
        ->assertJsonPath('data.reference_directory_id', $directoryId)
        ->assertJsonPath('data.values.company', 'Northwind Trade')
        ->assertJsonPath('data.values.rating', 5)
        ->assertJsonPath('data.values.approved', true);

    $recordId = $createRecordResponse->json('data.id');

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->patchJson(
            route('api.v1.directories.records.update', [$directoryId, $recordId]),
            directoriesApiRecordPayload(company: 'Litware', rating: '9', approved: false),
        )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.record_updated_success'))
        ->assertJsonPath('data.values.company', 'Litware')
        ->assertJsonPath('data.values.rating', 9)
        ->assertJsonPath('data.values.approved', false);

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->patchJson(
            route('api.v1.directories.update', $directoryId),
            directoriesApiPayload(name: 'Approved Suppliers', slug: 'approved-suppliers'),
        )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.updated_success'))
        ->assertJsonPath('data.name', 'Approved Suppliers')
        ->assertJsonPath('data.slug', 'approved-suppliers');

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->deleteJson(route('api.v1.directories.records.destroy', [$directoryId, $recordId]))
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.record_deleted_success'))
        ->assertJsonPath('data.id', $recordId);

    expect(ReferenceDirectoryRecord::query()->whereKey($recordId)->exists())->toBeFalse();

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->deleteJson(route('api.v1.directories.destroy', $directoryId))
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.deleted_success'))
        ->assertJsonPath('data.id', $directoryId);

    expect(ReferenceDirectory::query()->whereKey($directoryId)->exists())->toBeFalse();
});

test('api directories write endpoints require write permission', function () {
    $admin = directoriesApiSuperAdmin();

    $token = issueDirectoriesApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_DIRECTORIES_READ,
    ]);

    $this->withHeaders(directoriesApiHeadersFor($token))
        ->postJson(route('api.v1.directories.store'), directoriesApiPayload())
        ->assertForbidden();
});
