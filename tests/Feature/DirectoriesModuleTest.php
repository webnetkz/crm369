<?php

use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function directoriesWebSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'directories-web-admin@example.com']);

    return User::factory()->create([
        'email' => 'directories-web-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function directoriesWebPayload(string $name = 'Employee Registry', string $slug = 'employee-registry'): array
{
    return [
        'name' => $name,
        'slug' => $slug,
        'description' => 'Reusable employee reference data.',
        'csv_exchange_enabled' => true,
        'columns' => [
            [
                'label' => 'Full name',
                'key' => 'full_name',
                'type' => 'text',
                'is_required' => true,
            ],
            [
                'label' => 'Age',
                'key' => 'age',
                'type' => 'number',
                'is_required' => false,
            ],
            [
                'label' => 'Is active',
                'key' => 'is_active',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'label' => 'Start date',
                'key' => 'start_date',
                'type' => 'date',
                'is_required' => false,
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function directoriesWebRecordPayload(
    string $fullName = 'Aruzhan Sarsenova',
    string $age = '29',
    bool $isActive = true,
    string $startDate = '2026-07-09',
): array {
    return [
        'values' => [
            'full_name' => $fullName,
            'age' => $age,
            'is_active' => $isActive,
            'start_date' => $startDate,
        ],
    ];
}

test('directories module is available in the menu and opens for users with access', function () {
    $user = directoriesWebSuperAdmin();
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    $menuSettingsPage = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('directories.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('directories/Index')
            ->where('directories', [])
            ->where('activeDirectory', null)
            ->where('can.manageDirectories', true)
        );

    expect($sidebar)->toContain('href: directoriesIndex()')
        ->and($sidebar)->toContain("key: 'directories'")
        ->and(collect($menuSettingsPage->inertiaProps('builtInItems'))->pluck('key')->all())
        ->toContain('directories');
});

test('super admin can manage directories and their records from the web module', function () {
    $user = directoriesWebSuperAdmin();

    $this->actingAs($user)
        ->post(route('directories.store'), directoriesWebPayload())
        ->assertRedirect();

    $directory = ReferenceDirectory::query()->firstOrFail();

    expect($directory->slug)->toBe('employee-registry')
        ->and($directory->created_by_user_id)->toBe($user->id)
        ->and($directory->csv_exchange_enabled)->toBeTrue()
        ->and($directory->columnDefinitions())->toHaveCount(4)
        ->and($directory->columnDefinitions()[0]['key'])->toBe('full_name');

    $this->actingAs($user)
        ->get(route('directories.show', $directory))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('directories/Index')
            ->where('activeDirectory.id', $directory->id)
            ->where('activeDirectory.slug', 'employee-registry')
            ->where('activeDirectory.csv_exchange_enabled', true)
            ->where('activeDirectory.records', [])
        );

    $this->actingAs($user)
        ->post(route('directories.records.store', $directory), directoriesWebRecordPayload())
        ->assertRedirect();

    $record = ReferenceDirectoryRecord::query()->firstOrFail();

    expect($record->reference_directory_id)->toBe($directory->id)
        ->and($record->created_by_user_id)->toBe($user->id)
        ->and($record->values)->toMatchArray([
            'full_name' => 'Aruzhan Sarsenova',
            'age' => 29,
            'is_active' => true,
            'start_date' => '2026-07-09',
        ]);

    $this->actingAs($user)
        ->patch(route('directories.records.update', [$directory, $record]), directoriesWebRecordPayload(
            fullName: 'Dana Abdullina',
            age: '31',
            isActive: false,
            startDate: '2026-08-01',
        ))
        ->assertRedirect();

    expect($record->fresh()->values)->toMatchArray([
        'full_name' => 'Dana Abdullina',
        'age' => 31,
        'is_active' => false,
        'start_date' => '2026-08-01',
    ]);

    $this->actingAs($user)
        ->patch(route('directories.update', $directory), [
            ...directoriesWebPayload(
                name: 'Employee Registry Updated',
                slug: 'employee-registry-updated',
            ),
            'csv_exchange_enabled' => false,
        ])
        ->assertRedirect();

    $directory = $directory->fresh();

    expect($directory->name)->toBe('Employee Registry Updated')
        ->and($directory->slug)->toBe('employee-registry-updated')
        ->and($directory->csv_exchange_enabled)->toBeFalse()
        ->and($directory->updated_by_user_id)->toBe($user->id);

    $this->actingAs($user)
        ->delete(route('directories.records.destroy', [$directory, $record]))
        ->assertRedirect();

    $this->assertModelMissing($record);

    $this->actingAs($user)
        ->delete(route('directories.destroy', $directory))
        ->assertRedirect(route('directories.index'));

    $this->assertModelMissing($directory);
});

test('directory records can be exported and template can be downloaded as csv with a custom delimiter', function () {
    $user = directoriesWebSuperAdmin();
    $directory = ReferenceDirectory::factory()->create(directoriesWebPayload());

    $directory->records()->create([
        'values' => [
            'full_name' => 'Aruzhan Sarsenova',
            'age' => 29,
            'is_active' => true,
            'start_date' => '2026-07-09',
        ],
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);

    $exportResponse = $this->actingAs($user)
        ->get(route('directories.export', ['referenceDirectory' => $directory, 'delimiter' => '|']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($exportResponse->streamedContent())
        ->toContain('full_name|age|is_active|start_date')
        ->toContain('"Aruzhan Sarsenova"|29|true|2026-07-09');

    $templateResponse = $this->actingAs($user)
        ->get(route('directories.template', ['referenceDirectory' => $directory, 'delimiter' => ';']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($templateResponse->streamedContent())
        ->toContain('full_name;age;is_active;start_date')
        ->toContain('"'.__('ui.directories.csv_template_sample_value').'"'.';123;true;2026-01-15');
});

test('directory records can be imported from csv with a custom delimiter', function () {
    $user = directoriesWebSuperAdmin();
    $directory = ReferenceDirectory::factory()->create(directoriesWebPayload());

    $csv = <<<'CSV'
full_name;age;is_active;start_date
Aruzhan Sarsenova;29;true;2026-07-09
Dana Abdullina;31;false;2026-08-01
CSV;

    $response = $this->actingAs($user)
        ->from(route('directories.show', $directory))
        ->post(route('directories.import', $directory), [
            'delimiter' => ';',
            'file' => UploadedFile::fake()->createWithContent('directory.csv', $csv),
        ])
        ->assertRedirect(route('directories.show', $directory));

    expect(ReferenceDirectoryRecord::query()->where('reference_directory_id', $directory->id)->count())->toBe(2)
        ->and(ReferenceDirectoryRecord::query()->orderBy('id')->firstOrFail()->values)->toMatchArray([
            'full_name' => 'Aruzhan Sarsenova',
            'age' => 29,
            'is_active' => true,
            'start_date' => '2026-07-09',
        ])
        ->and(ReferenceDirectoryRecord::query()->latest('id')->firstOrFail()->values)->toMatchArray([
            'full_name' => 'Dana Abdullina',
            'age' => 31,
            'is_active' => false,
            'start_date' => '2026-08-01',
        ]);

    $response->assertSessionHasNoErrors();
});

test('directory csv endpoints are forbidden when csv exchange is disabled', function () {
    $user = directoriesWebSuperAdmin();
    $directory = ReferenceDirectory::factory()->create([
        ...directoriesWebPayload(),
        'csv_exchange_enabled' => false,
    ]);

    $csv = <<<'CSV'
full_name;age;is_active;start_date
Aruzhan Sarsenova;29;true;2026-07-09
CSV;

    $this->actingAs($user)
        ->get(route('directories.export', $directory))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('directories.template', $directory))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('directories.import', $directory), [
            'delimiter' => ';',
            'file' => UploadedFile::fake()->createWithContent('directory.csv', $csv),
        ])
        ->assertForbidden();
});
