<?php

use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

function companyStructureAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

test('authenticated users can open the company structure module and receive the org tree', function () {
    $chiefExecutive = User::factory()->create([
        'name' => 'Aruzhan',
        'last_name' => 'Sarsenova',
        'position' => 'Chief Executive Officer',
    ]);

    $manager = User::factory()->create([
        'name' => 'Timur',
        'last_name' => 'Aitbayev',
        'position' => 'Operations Manager',
        'manager_id' => $chiefExecutive->id,
    ]);

    $staff = User::factory()->create([
        'name' => 'Dana',
        'last_name' => 'Abdullina',
        'position' => 'Account Executive',
        'manager_id' => $manager->id,
    ]);

    $this->actingAs($chiefExecutive)
        ->get(route('company-structure.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-structure/Index')
            ->where('stats.total_users', 3)
            ->where('stats.root_users', 1)
            ->where('stats.managers', 2)
            ->has('roots', 1)
            ->where('roots.0.full_name', 'Aruzhan Sarsenova')
            ->where('roots.0.position', 'Chief Executive Officer')
            ->where('roots.0.children.0.full_name', 'Timur Aitbayev')
            ->where('roots.0.children.0.children.0.full_name', 'Dana Abdullina')
            ->where('roots.0.children.0.manager.full_name', 'Aruzhan Sarsenova')
            ->where('roots.0.children.0.subordinates.0.full_name', 'Dana Abdullina'));
});

test('company structure module returns 404 when disabled', function () {
    $user = User::factory()->create();

    PortalSetting::current()->update([
        'disabled_modules' => ['company-structure'],
    ]);

    $this->actingAs($user)
        ->get(route('company-structure.index'))
        ->assertNotFound();
});

test('administrators can update a managed user position and manager and see subordinate fields', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => companyStructureAdministratorsGroup()->id,
    ]);

    $manager = User::factory()->create([
        'name' => 'Miras',
        'last_name' => 'Kudaibergen',
        'position' => 'Sales Director',
    ]);

    $employee = User::factory()->create([
        'name' => 'Aliya',
        'last_name' => 'Rysbek',
        'position' => null,
        'manager_id' => null,
    ]);

    $this->actingAs($admin)
        ->patch(route('settings.users.profile.update', $employee), [
            'name' => 'Aliya',
            'last_name' => 'Rysbek',
            'email' => $employee->email,
            'phone' => '+77011234567',
            'position' => 'Sales Specialist',
            'manager_id' => $manager->id,
        ])
        ->assertRedirect();

    expect($employee->refresh()->position)->toBe('Sales Specialist')
        ->and($employee->manager_id)->toBe($manager->id);

    $this->actingAs($admin)
        ->getJson(route('settings.users.show', $manager))
        ->assertSuccessful()
        ->assertJsonPath('data.position', 'Sales Director')
        ->assertJsonPath('data.subordinates.0.full_name', 'Aliya Rysbek');

    $this->actingAs($admin)
        ->getJson(route('settings.users.show', $employee))
        ->assertSuccessful()
        ->assertJsonPath('data.position', 'Sales Specialist')
        ->assertJsonPath('data.manager.full_name', 'Miras Kudaibergen')
        ->assertJsonPath('data.manager.position', 'Sales Director')
        ->assertJsonPath('data.subordinates_count', 0);
});

test('administrators cannot create a manager cycle in company structure', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => companyStructureAdministratorsGroup()->id,
    ]);

    $chiefExecutive = User::factory()->create([
        'manager_id' => null,
    ]);
    $manager = User::factory()->create([
        'manager_id' => $chiefExecutive->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('settings.users.profile.update', $chiefExecutive), [
            'name' => $chiefExecutive->name,
            'last_name' => $chiefExecutive->last_name,
            'email' => $chiefExecutive->email,
            'phone' => null,
            'position' => 'Chief Executive Officer',
            'manager_id' => $manager->id,
        ])
        ->assertSessionHasErrors('manager_id');
});
