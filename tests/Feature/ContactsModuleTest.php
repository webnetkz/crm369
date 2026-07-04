<?php

use App\Models\Contact;
use App\Models\ContactComment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can manage both person and company contacts', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    Contact::factory()->person()->create([
        'name' => 'Alice Person',
    ]);
    Contact::factory()->company()->create([
        'name' => 'Beta Company',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index')
            ->where('can.create_person', true)
            ->where('can.create_company', true)
            ->has('availableTypes', 2)
            ->has('contacts.data', 2)
        );

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Created Person',
            'email' => 'person@example.com',
            'company_requisites' => [
                'iin' => '123456789012',
            ],
        ])
        ->assertRedirect();

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Created Company',
            'contact_person' => 'Eva Manager',
            'email' => 'company@example.com',
            'company_requisites' => [
                'bin' => '123456789012',
                'legal_address' => 'г. Алматы, ул. Абая, 10',
                'actual_address' => 'г. Алматы, ул. Сатпаева, 11',
                'bank_name' => 'Kaspi Bank',
                'bank_bik' => 'CASPKZKA',
                'iban' => 'KZ123456789012345678',
                'kbe' => '17',
            ],
        ])
        ->assertRedirect();

    $createdCompany = Contact::query()
        ->where('name', 'Created Company')
        ->where('type', Contact::TYPE_COMPANY)
        ->first();

    $createdPerson = Contact::query()
        ->where('name', 'Created Person')
        ->where('type', Contact::TYPE_PERSON)
        ->first();

    expect($createdPerson)->not()->toBeNull()
        ->and($createdPerson?->company_requisites['iin'])->toBe('123456789012')
        ->and($createdCompany)->not()->toBeNull()
        ->and($createdCompany?->company_requisites['bin'])->toBe('123456789012')
        ->and($createdCompany?->company_requisites['bank_name'])->toBe('Kaspi Bank');
});

test('group permissions expose person and company contacts separately', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    Contact::factory()->person()->create([
        'name' => 'Visible Person',
    ]);
    Contact::factory()->company()->create([
        'name' => 'Hidden Company',
    ]);

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index')
            ->where('can.create_person', true)
            ->where('can.create_company', false)
            ->has('availableTypes', 1)
            ->where('availableTypes.0.value', Contact::TYPE_PERSON)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Visible Person')
            ->where('contacts.data.0.type', Contact::TYPE_PERSON)
        );

    $this->actingAs($user)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Forbidden Company',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Allowed Person',
        ])
        ->assertRedirect();

    expect(Contact::query()->where('name', 'Allowed Person')->where('type', Contact::TYPE_PERSON)->exists())->toBeTrue()
        ->and(Contact::query()->where('name', 'Forbidden Company')->exists())->toBeFalse();
});

test('super admin can update company contacts with requisites', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $company = Contact::factory()->company()->create([
        'name' => 'Original Company',
        'company_requisites' => null,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('contacts.update', $company), [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Updated Company',
            'contact_person' => 'Dana Manager',
            'email' => 'updated-company@example.com',
            'company_requisites' => [
                'bin' => '123456789012',
                'legal_address' => 'г. Алматы, ул. Абая, 10',
                'actual_address' => 'г. Алматы, ул. Сатпаева, 12',
                'bank_name' => 'Kaspi Bank',
                'bank_bik' => 'CASPKZKA',
                'iban' => 'KZ123456789012345678',
                'kbe' => '17',
            ],
        ])
        ->assertRedirect();

    $company->refresh();

    expect($company->name)->toBe('Updated Company')
        ->and($company->contact_person)->toBe('Dana Manager')
        ->and($company->email)->toBe('updated-company@example.com')
        ->and($company->company_requisites['bin'])->toBe('123456789012')
        ->and($company->company_requisites['bank_name'])->toBe('Kaspi Bank');
});

test('super admin can blacklist contacts and filter them in the workspace', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $blacklisted = Contact::factory()->person()->blacklisted()->create([
        'name' => 'Blocked Person',
    ]);
    Contact::factory()->person()->create([
        'name' => 'Open Person',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('contacts.index', ['blacklist' => Contact::BLACKLIST_FILTER_ONLY]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.blacklist', Contact::BLACKLIST_FILTER_ONLY)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $blacklisted->id)
            ->where('contacts.data.0.is_blacklisted', true)
        );

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Fresh Blacklisted Person',
            'is_blacklisted' => true,
        ])
        ->assertRedirect();

    $createdContact = Contact::query()
        ->where('name', 'Fresh Blacklisted Person')
        ->where('type', Contact::TYPE_PERSON)
        ->first();

    expect($createdContact)
        ->not()->toBeNull()
        ->and($createdContact?->is_blacklisted)
        ->toBeTrue();
});

test('super admin can add contact history comments', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $contact = Contact::factory()->person()->create([
        'name' => 'History Person',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('contacts.comments.store', $contact), [
            'content' => 'Созвон: уточнили потребность и договорились вернуться с КП в пятницу.',
        ])
        ->assertRedirect();

    $comment = ContactComment::query()
        ->whereBelongsTo($contact)
        ->first();

    expect($comment)->not->toBeNull()
        ->and($comment?->content)->toBe('Созвон: уточнили потребность и договорились вернуться с КП в пятницу.')
        ->and($comment?->created_by_user_id)->toBe($superAdmin->id);

    $this->actingAs($superAdmin)
        ->get(route('contacts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.name', 'History Person')
            ->has('contacts.data.0.comments', 1)
            ->where(
                'contacts.data.0.comments.0.content',
                'Созвон: уточнили потребность и договорились вернуться с КП в пятницу.',
            )
            ->where('contacts.data.0.comments.0.created_by.name', $superAdmin->name)
        );
});

test('super admin can upload and replace contact avatars', function () {
    Storage::fake('public');
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Avatar Person',
            'avatar' => UploadedFile::fake()->image('person-avatar.png'),
        ])
        ->assertRedirect();

    $person = Contact::query()
        ->where('name', 'Avatar Person')
        ->where('type', Contact::TYPE_PERSON)
        ->firstOrFail();

    Storage::disk('public')->assertExists($person->avatar_path);

    $company = Contact::factory()->company()->create([
        'name' => 'Avatar Company',
        'avatar_path' => 'contacts/company-existing/company-old.png',
    ]);

    Storage::disk('public')->put($company->avatar_path, 'old-avatar');
    $oldAvatarPath = $company->avatar_path;

    $this->actingAs($superAdmin)
        ->post(route('contacts.update', $company), [
            '_method' => 'PATCH',
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Avatar Company',
            'contact_person' => $company->contact_person,
            'position' => $company->position,
            'email' => $company->email,
            'phone' => $company->phone,
            'notes' => $company->notes,
            'company_requisites' => $company->company_requisites,
            'avatar' => UploadedFile::fake()->image('company-avatar.png'),
        ])
        ->assertRedirect();

    $company->refresh();

    Storage::disk('public')->assertMissing($oldAvatarPath);
    Storage::disk('public')->assertExists($company->avatar_path);

    expect($company->avatar_path)->not->toBe($oldAvatarPath)
        ->and($company->avatarUrl())->toBe(Storage::disk('public')->url($company->avatar_path));

    $this->actingAs($superAdmin)
        ->get(route('contacts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.name', 'Avatar Company')
            ->where('contacts.data.0.avatar', Storage::disk('public')->url($company->avatar_path))
            ->where('contacts.data.1.name', 'Avatar Person')
            ->where('contacts.data.1.avatar', Storage::disk('public')->url($person->avatar_path))
        );
});

test('company BIN must contain exactly 12 digits', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $company = Contact::factory()->company()->create();

    $this->actingAs($superAdmin)
        ->patch(route('contacts.update', $company), [
            'type' => Contact::TYPE_COMPANY,
            'name' => $company->name,
            'company_requisites' => [
                'bin' => '12AB5678901',
            ],
        ])
        ->assertSessionHasErrors([
            'company_requisites.bin' => __('ui.contacts.bin_validation'),
        ]);
});

test('company BIN must be unique across contacts', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    Contact::factory()->company()->create([
        'company_requisites' => [
            'bin' => '123456789012',
        ],
    ]);

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Duplicate BIN Company',
            'company_requisites' => [
                'bin' => '123456789012',
            ],
        ])
        ->assertSessionHasErrors([
            'company_requisites.bin' => __('ui.contacts.bin_unique'),
        ]);
});

test('company can keep its own BIN while updating', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $company = Contact::factory()->company()->create([
        'company_requisites' => [
            'bin' => '123456789012',
        ],
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('contacts.update', $company), [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Updated Company Name',
            'company_requisites' => [
                'bin' => '123456789012',
            ],
        ])
        ->assertRedirect();
});

test('person IIN must contain exactly 12 digits and be unique', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    Contact::factory()->person()->create([
        'company_requisites' => [
            'iin' => '123456789012',
        ],
    ]);

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Invalid IIN Person',
            'company_requisites' => [
                'iin' => '1234AB',
            ],
        ])
        ->assertSessionHasErrors([
            'company_requisites.iin' => __('ui.contacts.iin_validation'),
        ]);

    $this->actingAs($superAdmin)
        ->post(route('contacts.store'), [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Duplicate IIN Person',
            'company_requisites' => [
                'iin' => '123456789012',
            ],
        ])
        ->assertSessionHasErrors([
            'company_requisites.iin' => __('ui.contacts.iin_unique'),
        ]);
});

test('contacts access is hidden from regular users and visible in rights configuration', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contacts.index'))
        ->assertForbidden();

    $rightsResponse = $this->actingAs($superAdmin)
        ->get(route('settings.rights.index'))
        ->assertSuccessful();

    $permissionKeys = collect($rightsResponse->inertiaProps('availablePermissions'))
        ->pluck('key')
        ->all();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($permissionKeys)
        ->toContain(UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS)
        ->toContain(UserGroup::PERMISSION_ACCESS_COMPANY_CONTACTS)
        ->and($sidebar)->toContain("page.props.auth.canAccessContacts && isMenuItemVisible('contacts')");
});

test('contacts index returns service unavailable when the contacts table is missing', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    Schema::drop('contacts');

    $this->actingAs($superAdmin)
        ->get(route('contacts.index'))
        ->assertServiceUnavailable();
});
