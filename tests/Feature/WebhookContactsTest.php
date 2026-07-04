<?php

use App\Models\Contact;
use App\Models\PortalWebhook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('webhook contacts read permission exposes contacts in the payload and endpoint list', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_CONTACTS_READ],
    ]);
    $webhook->issueToken('contacts-read-token');

    $person = Contact::factory()->person()->create([
        'name' => 'Webhook Person',
    ]);
    $company = Contact::factory()->company()->create([
        'name' => 'Webhook Company',
    ]);

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=contacts-read-token')
        ->assertOk()
        ->assertJsonPath(
            'endpoints.contacts.index',
            route('portal-webhooks.contacts.index', $webhook).'?token=contacts-read-token'
        )
        ->assertJsonFragment([
            'id' => $person->id,
            'name' => 'Webhook Person',
        ])
        ->assertJsonFragment([
            'id' => $company->id,
            'name' => 'Webhook Company',
        ]);

    $this->get(route('portal-webhooks.contacts.index', $webhook).'?token=contacts-read-token')
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment([
            'id' => $person->id,
            'name' => 'Webhook Person',
        ]);
});

test('webhook contacts write permission can create update and delete contacts', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_CONTACTS_WRITE],
    ]);
    $webhook->issueToken('contacts-write-token');

    $createdResponse = $this->postJson(
        route('portal-webhooks.contacts.store', $webhook).'?token=contacts-write-token',
        [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Webhook Managed Company',
            'contact_person' => 'Maria Admin',
            'company_requisites' => [
                'bin' => '987654321098',
                'legal_address' => 'Astana, Mangilik El 1',
                'actual_address' => 'Astana, Kabanbay Batyr 2',
                'bank_name' => 'Halyk Bank',
                'bank_bik' => 'HSBKKZKX',
                'iban' => 'KZ223456789012345678',
                'kbe' => '17',
            ],
        ],
    )->assertCreated()
        ->assertJsonPath('data.type', Contact::TYPE_COMPANY)
        ->assertJsonPath('data.name', 'Webhook Managed Company')
        ->assertJsonPath('data.company_requisites.bin', '987654321098');

    $contactId = $createdResponse->json('data.id');

    $this->patchJson(
        route('portal-webhooks.contacts.update', [$webhook, $contactId]).'?token=contacts-write-token',
        [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Webhook Managed Company Updated',
            'contact_person' => 'Maria Admin',
            'company_requisites' => [
                'bin' => '987654321098',
                'legal_address' => 'Astana, Mangilik El 1',
                'actual_address' => 'Astana, Kabanbay Batyr 3',
                'bank_name' => 'Halyk Bank',
                'bank_bik' => 'HSBKKZKX',
                'iban' => 'KZ223456789012345679',
                'kbe' => '18',
            ],
        ],
    )->assertOk()
        ->assertJsonPath('data.name', 'Webhook Managed Company Updated')
        ->assertJsonPath('data.company_requisites.kbe', '18');

    $this->deleteJson(
        route('portal-webhooks.contacts.destroy', [$webhook, $contactId]).'?token=contacts-write-token',
    )->assertOk()
        ->assertJsonPath('data.id', $contactId);

    expect(Contact::query()->whereKey($contactId)->exists())->toBeFalse();
});

test('webhook contacts endpoints support blacklisted contacts', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [
            PortalWebhook::PERMISSION_CONTACTS_READ,
            PortalWebhook::PERMISSION_CONTACTS_WRITE,
        ],
    ]);
    $webhook->issueToken('contacts-blacklist-token');

    $blacklisted = Contact::factory()->person()->blacklisted()->create([
        'name' => 'Webhook Blacklisted Person',
    ]);
    Contact::factory()->person()->create([
        'name' => 'Webhook Open Person',
    ]);

    $this->get(
        route('portal-webhooks.contacts.index', $webhook)
        .'?token=contacts-blacklist-token&blacklist='
        .Contact::BLACKLIST_FILTER_ONLY,
    )
        ->assertOk()
        ->assertJsonPath('filters.blacklist', Contact::BLACKLIST_FILTER_ONLY)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $blacklisted->id)
        ->assertJsonPath('data.0.is_blacklisted', true);

    $this->postJson(
        route('portal-webhooks.contacts.store', $webhook).'?token=contacts-blacklist-token',
        [
            'type' => Contact::TYPE_PERSON,
            'name' => 'Webhook Created Blacklisted Person',
            'is_blacklisted' => true,
        ],
    )
        ->assertCreated()
        ->assertJsonPath('data.name', 'Webhook Created Blacklisted Person')
        ->assertJsonPath('data.is_blacklisted', true);
});

test('webhook contacts write permission can upload and clean up avatars', function () {
    Storage::fake('public');

    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_CONTACTS_WRITE],
    ]);
    $webhook->issueToken('contacts-avatar-token');

    $response = $this->post(
        route('portal-webhooks.contacts.store', $webhook).'?token=contacts-avatar-token',
        [
            'type' => Contact::TYPE_COMPANY,
            'name' => 'Webhook Avatar Company',
            'avatar' => UploadedFile::fake()->image('webhook-company-avatar.png'),
        ],
        [
            'Accept' => 'application/json',
        ],
    )->assertCreated()
        ->assertJsonPath('data.type', Contact::TYPE_COMPANY);

    $contactId = $response->json('data.id');
    $contact = Contact::query()->findOrFail($contactId);

    Storage::disk('public')->assertExists($contact->avatar_path);

    expect($response->json('data.avatar'))->toBe(
        Storage::disk('public')->url($contact->avatar_path),
    );

    $avatarPath = $contact->avatar_path;

    $this->deleteJson(
        route('portal-webhooks.contacts.destroy', [$webhook, $contactId]).'?token=contacts-avatar-token',
    )->assertOk();

    Storage::disk('public')->assertMissing($avatarPath);
});
