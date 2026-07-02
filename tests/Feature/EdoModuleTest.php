<?php

use App\Models\ApiAccessToken;
use App\Models\EdoDocument;
use App\Models\FileDirectory;
use App\Models\FileEntry;
use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function edoAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function issueEdoApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'EDO token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

test('authenticated users can open the edo page and it appears in the sidebar menu', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $menuResponse = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($menuResponse->inertiaProps('builtInItems'))->pluck('key');

    $this->actingAs($user)
        ->get(route('edo.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('edo/Index')
            ->where('documents', [])
            ->where('activeDocument', null)
            ->has('availableFiles')
        );

    expect($sidebar)->toContain("isMenuItemVisible('edo')")
        ->and($sidebar)->toContain('title: t.value.edo.title')
        ->and($sidebar)->toContain('href: edoIndex()')
        ->and($builtInKeys->all())->toContain('edo');
});

test('users can create edo documents and issue a 12 hour public signing link', function () {
    CarbonImmutable::setTestNow('2026-07-01 10:00:00');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('edo.store'), [
            'title' => 'Supply agreement',
            'external_reference' => 'EDO-2026-0001',
            'counterparty_name' => 'Acme LLP',
            'counterparty_identifier' => '123456789012',
            'document_source' => EdoDocument::SOURCE_TEXT,
            'content' => 'Important supply agreement body.',
        ])
        ->assertRedirect();

    $document = EdoDocument::query()->where('title', 'Supply agreement')->firstOrFail();

    expect($document->status)->toBe(EdoDocument::STATUS_DRAFT);

    $this->actingAs($user)
        ->post(route('edo.public-link.store', $document))
        ->assertRedirect(route('edo.index', ['document' => $document->id]));

    $document->refresh();

    expect($document->status)->toBe(EdoDocument::STATUS_PENDING_SIGNATURE)
        ->and($document->public_token)->not->toBeNull()
        ->and($document->public_link_expires_at?->toDateTimeString())->toBe('2026-07-01 22:00:00')
        ->and($document->publicShowUrl())->not->toBeNull()
        ->and($document->publicSignUrl())->not->toBeNull();

    CarbonImmutable::setTestNow();
});

test('users can upload a document or choose an existing file from the files module', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $directory = FileDirectory::factory()->create([
        'owner_user_id' => $user->id,
        'name' => 'Contracts',
    ]);

    $sourcePath = 'files/'.$directory->id.'/master-agreement.pdf';
    Storage::disk('local')->put($sourcePath, 'contract-body');

    $fileEntry = FileEntry::query()->create([
        'file_directory_id' => $directory->id,
        'owner_user_id' => $user->id,
        'original_name' => 'master-agreement.pdf',
        'disk' => 'local',
        'path' => $sourcePath,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => Storage::disk('local')->size($sourcePath),
    ]);

    $this->actingAs($user)
        ->post(route('edo.store'), [
            'title' => 'Uploaded agreement',
            'counterparty_name' => 'Acme LLP',
            'counterparty_identifier' => '123456789012',
            'document_source' => EdoDocument::SOURCE_UPLOAD,
            'document_upload' => UploadedFile::fake()->create('uploaded-agreement.pdf', 12, 'application/pdf'),
        ])
        ->assertRedirect();

    $uploadedDocument = EdoDocument::query()->where('title', 'Uploaded agreement')->firstOrFail();

    expect($uploadedDocument->document_source)->toBe(EdoDocument::SOURCE_UPLOAD)
        ->and($uploadedDocument->hasDocumentFile())->toBeTrue()
        ->and($uploadedDocument->document_file_name)->toBe('uploaded-agreement.pdf');

    Storage::disk('local')->assertExists((string) $uploadedDocument->document_file_path);

    $this->actingAs($user)
        ->post(route('edo.store'), [
            'title' => 'Copied agreement',
            'counterparty_name' => 'Beta LLP',
            'counterparty_identifier' => '123456789013',
            'document_source' => EdoDocument::SOURCE_FILE_ENTRY,
            'selected_file_entry_id' => $fileEntry->id,
        ])
        ->assertRedirect();

    $copiedDocument = EdoDocument::query()->where('title', 'Copied agreement')->firstOrFail();

    expect($copiedDocument->document_source)->toBe(EdoDocument::SOURCE_FILE_ENTRY)
        ->and($copiedDocument->source_file_entry_id)->toBe($fileEntry->id)
        ->and($copiedDocument->document_file_name)->toBe('master-agreement.pdf')
        ->and($copiedDocument->document_file_hash)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $copiedDocument->document_file_path);
});

test('public edo signing page accepts a signature and marks the document as signed', function () {
    $document = EdoDocument::factory()->pendingSignature()->create([
        'title' => 'Employment contract',
        'content' => 'Document ready for signature.',
    ]);

    $showUrl = $document->publicShowUrl();
    $signUrl = $document->publicSignUrl();

    expect($showUrl)->not->toBeNull()
        ->and($signUrl)->not->toBeNull();

    $this->get($showUrl)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/edo/Show')
            ->where('document.title', 'Employment contract')
            ->where('document.state', 'ready')
        );

    $this->post($signUrl, [
        'signature_payload' => base64_encode(str_repeat('signature', 8)),
        'signature_subject' => 'CN=Test Signer',
        'signature_serial_number' => '12345678',
        'signature_algorithm' => 'GOST3411',
        'signed_payload_hash' => $document->signingPayloadHash(),
        'signature_metadata' => [
            'provider' => 'ncalayer',
        ],
    ])->assertRedirect();

    expect($document->fresh()->status)->toBe(EdoDocument::STATUS_SIGNED)
        ->and($document->fresh()->signature_subject)->toBe('CN=Test Signer')
        ->and($document->fresh()->signature_algorithm)->toBe('GOST3411')
        ->and($document->fresh()->signed_at)->not->toBeNull();
});

test('public signing page can download the attached document snapshot', function () {
    Storage::fake('local');

    $document = EdoDocument::factory()->pendingSignature()->create([
        'document_source' => EdoDocument::SOURCE_UPLOAD,
        'document_file_name' => 'signed-contract.pdf',
        'document_file_disk' => 'local',
        'document_file_path' => 'edo-documents/public/signed-contract.pdf',
        'document_file_mime_type' => 'application/pdf',
        'document_file_size_bytes' => 12,
        'document_file_hash' => hash('sha256', 'signed-contract'),
    ]);

    Storage::disk('local')->put((string) $document->document_file_path, 'signed-contract');

    $downloadUrl = $document->publicDownloadUrl();

    expect($downloadUrl)->not->toBeNull();

    $this->get($downloadUrl)
        ->assertDownload('signed-contract.pdf');
});

test('edo api endpoints allow creating, listing, and issuing public links', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => edoAdministratorsGroup()->id,
    ]);

    $token = issueEdoApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_EDO_READ,
        ApiAccessToken::PERMISSION_EDO_WRITE,
    ]);

    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];

    $createResponse = $this->withHeaders($headers)
        ->postJson(route('api.v1.edo.store'), [
            'title' => 'API document',
            'external_reference' => 'EDO-API-1',
            'counterparty_name' => 'API Signer',
            'counterparty_identifier' => '123456789012',
            'content' => 'API generated payload.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'API document');

    $documentId = $createResponse->json('data.id');

    $this->withHeaders($headers)
        ->getJson(route('api.v1.edo.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $documentId);

    $this->withHeaders($headers)
        ->postJson(route('api.v1.edo.public-link.store', $documentId))
        ->assertOk()
        ->assertJsonPath('data.has_active_public_link', true)
        ->assertJsonPath('data.status', EdoDocument::STATUS_PENDING_SIGNATURE);
});

test('edo webhook endpoints return documents and can issue a public link', function () {
    $creator = User::factory()->create();
    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [
            PortalWebhook::PERMISSION_EDO_READ,
            PortalWebhook::PERMISSION_EDO_WRITE,
        ],
    ]);
    $webhook->issueToken('edo-webhook-token');

    $document = EdoDocument::factory()->create([
        'title' => 'Webhook document',
        'created_by_user_id' => $creator->id,
        'updated_by_user_id' => $creator->id,
    ]);

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=edo-webhook-token')
        ->assertOk()
        ->assertJsonPath('edo_documents.0.id', $document->id)
        ->assertJsonPath('endpoints.edo.index', route('portal-webhooks.edo.index', $webhook).'?token=edo-webhook-token');

    $createResponse = $this->postJson(route('portal-webhooks.edo.store', $webhook).'?token=edo-webhook-token', [
        'title' => 'Webhook created document',
        'external_reference' => 'WH-01',
        'counterparty_name' => 'Webhook signer',
        'counterparty_identifier' => '123456789012',
        'content' => 'Created through webhook.',
    ])->assertCreated();

    $createdDocumentId = $createResponse->json('data.id');

    $this->postJson(route('portal-webhooks.edo.public-link.store', [
        'portalWebhook' => $webhook,
        'edoDocument' => $createdDocumentId,
    ]).'?token=edo-webhook-token')
        ->assertOk()
        ->assertJsonPath('data.has_active_public_link', true)
        ->assertJsonPath('data.status', EdoDocument::STATUS_PENDING_SIGNATURE);
});
