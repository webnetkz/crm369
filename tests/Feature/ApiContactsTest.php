<?php

use App\Models\ApiAccessToken;
use App\Models\Contact;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function issueContactApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Contacts API token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

function contactApiHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('api contacts endpoints respect token permissions and contact type access', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS,
            UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => $group->id,
    ]);

    $person = Contact::factory()->person()->create([
        'name' => 'API Person',
    ]);
    $company = Contact::factory()->company()->create([
        'name' => 'API Company',
    ]);

    $token = issueContactApiTokenFor($user, [
        ApiAccessToken::PERMISSION_CONTACTS_READ,
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
    ]);

    $this->withHeaders(contactApiHeaders($token))
        ->getJson('/api/v1/contacts')
        ->assertOk()
        ->assertJsonPath('can.create_person', true)
        ->assertJsonPath('can.create_company', false)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $person->id)
        ->assertJsonPath('data.0.name', 'API Person');

    $this->withHeaders(contactApiHeaders($token))
        ->getJson('/api/v1/contacts/'.$company->id)
        ->assertNotFound();

    $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Blocked Company',
        ])
        ->assertForbidden();

    $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Created via API',
            'email' => 'api-person@example.com',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', Contact::TYPE_PERSON)
        ->assertJsonPath('data.name', 'Created via API');

    expect(Contact::query()->where('name', 'Created via API')->where('type', Contact::TYPE_PERSON)->exists())->toBeTrue()
        ->and(Contact::query()->where('name', 'Blocked Company')->exists())->toBeFalse();

    $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Created via API Company',
            'contact_person' => 'API Manager',
            'company_requisites' => [
                'bin' => '112233445566',
                'legal_address' => 'Almaty, Dostyk 10',
                'actual_address' => 'Almaty, Nazarbayev 12',
                'bank_name' => 'Freedom Bank',
                'bank_bik' => 'KSNVKZKA',
                'iban' => 'KZ333456789012345678',
                'kbe' => '17',
            ],
        ])
        ->assertForbidden();
});

test('api contacts prevent duplicate company BIN values', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS,
            UserGroup::PERMISSION_ACCESS_COMPANY_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => $group->id,
    ]);

    Contact::factory()->company()->create([
        'company_requisites' => [
            'bin' => '123456789012',
        ],
    ]);

    $token = issueContactApiTokenFor($user, [
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
    ]);

    $response = $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Duplicate API Company',
            'company_requisites' => [
                'bin' => '123456789012',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'company_requisites.bin',
        ]);

    expect($response->json('errors')['company_requisites.bin'][0])->toBe(__('ui.contacts.bin_unique'));
});

test('api contacts validate person IIN length and uniqueness', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS,
            UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => $group->id,
    ]);

    Contact::factory()->person()->create([
        'company_requisites' => [
            'iin' => '123456789012',
        ],
    ]);

    $token = issueContactApiTokenFor($user, [
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
    ]);

    $invalidResponse = $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Invalid IIN Person',
            'company_requisites' => [
                'iin' => '1234AB',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'company_requisites.iin',
        ]);

    expect($invalidResponse->json('errors')['company_requisites.iin'][0])->toBe(__('ui.contacts.iin_validation'));

    $duplicateResponse = $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Duplicate IIN Person',
            'company_requisites' => [
                'iin' => '123456789012',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'company_requisites.iin',
        ]);

    expect($duplicateResponse->json('errors')['company_requisites.iin'][0])->toBe(__('ui.contacts.iin_unique'));
});

test('api contacts can create and filter blacklisted contacts', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS,
            UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => $group->id,
    ]);

    $blacklisted = Contact::factory()->person()->blacklisted()->create([
        'name' => 'API Blacklisted Person',
    ]);
    Contact::factory()->person()->create([
        'name' => 'API Open Person',
    ]);

    $token = issueContactApiTokenFor($user, [
        ApiAccessToken::PERMISSION_CONTACTS_READ,
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
    ]);

    $this->withHeaders(contactApiHeaders($token))
        ->getJson('/api/v1/contacts?blacklist='.Contact::BLACKLIST_FILTER_ONLY)
        ->assertOk()
        ->assertJsonPath('filters.blacklist', Contact::BLACKLIST_FILTER_ONLY)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $blacklisted->id)
        ->assertJsonPath('data.0.is_blacklisted', true);

    $this->withHeaders(contactApiHeaders($token))
        ->postJson('/api/v1/contacts', [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Created API Blacklisted Person',
            'is_blacklisted' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Created API Blacklisted Person')
        ->assertJsonPath('data.is_blacklisted', true);
});

test('api contacts can upload avatar images', function () {
    Storage::fake('public');

    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS,
            UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        ],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => $group->id,
    ]);

    $token = issueContactApiTokenFor($user, [
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
    ]);

    $response = $this->withHeaders(contactApiHeaders($token))
        ->post('/api/v1/contacts', [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Avatar API Person',
            'avatar' => UploadedFile::fake()->image('api-person-avatar.png'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', Contact::TYPE_PERSON);

    $contact = Contact::query()
        ->where('name', 'Avatar API Person')
        ->where('type', Contact::TYPE_PERSON)
        ->firstOrFail();

    Storage::disk('public')->assertExists($contact->avatar_path);

    expect($response->json('data.avatar'))->toBe(
        Storage::disk('public')->url($contact->avatar_path),
    );
});
