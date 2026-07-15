<?php

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\TsdQrScan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open equipment page and manage equipment items', function () {
    $user = User::factory()->create();
    $responsibleUser = User::factory()->create([
        'name' => 'Responsible',
        'last_name' => 'Manager',
    ]);
    $issuedUser = User::factory()->create([
        'name' => 'Issued',
        'last_name' => 'Employee',
    ]);

    $this->actingAs($user)
        ->get(route('equipment.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('stats.total', 0)
            ->has('availableUsers')
            ->has('statusOptions', 5)
            ->where('filters.status', '')
            ->where('equipmentItems.meta.per_page', 50)
        );

    $this->actingAs($user)
        ->post(route('equipment.store'), [
            'name' => 'Lenovo ThinkPad X1',
            'qr_code' => 'EQ-LENOVO-001',
            'status' => EquipmentItem::STATUS_ON_BALANCE,
            'responsible_user_id' => $responsibleUser->id,
            'issued_to_user_id' => null,
        ])
        ->assertRedirect();

    $equipmentItem = EquipmentItem::query()->first();

    $this->assertModelExists($equipmentItem);

    expect($equipmentItem->name)->toBe('Lenovo ThinkPad X1')
        ->and($equipmentItem->qr_code)->toBe('EQ-LENOVO-001')
        ->and($equipmentItem->status)->toBe(EquipmentItem::STATUS_ON_BALANCE)
        ->and($equipmentItem->responsible_user_id)->toBe($responsibleUser->id)
        ->and($equipmentItem->issued_to_user_id)->toBeNull()
        ->and(EquipmentItemHistory::query()->where('equipment_item_id', $equipmentItem->id)->count())->toBe(1)
        ->and(EquipmentItemHistory::query()->where('equipment_item_id', $equipmentItem->id)->value('event_type'))
        ->toBe(EquipmentItemHistory::EVENT_CREATED);

    $this->actingAs($user)
        ->patch(route('equipment.update', $equipmentItem), [
            'name' => 'Lenovo ThinkPad X1 Carbon',
            'qr_code' => 'EQ-LENOVO-001',
            'status' => EquipmentItem::STATUS_ISSUED,
            'responsible_user_id' => $responsibleUser->id,
            'issued_to_user_id' => $issuedUser->id,
        ])
        ->assertRedirect();

    expect($equipmentItem->fresh()->name)->toBe('Lenovo ThinkPad X1 Carbon')
        ->and($equipmentItem->fresh()->status)->toBe(EquipmentItem::STATUS_ISSUED)
        ->and($equipmentItem->fresh()->issued_to_user_id)->toBe($issuedUser->id)
        ->and(EquipmentItemHistory::query()->where('equipment_item_id', $equipmentItem->id)->count())->toBe(2)
        ->and(
            EquipmentItemHistory::query()
                ->where('equipment_item_id', $equipmentItem->id)
                ->latest('id')
                ->value('event_type')
        )->toBe(EquipmentItemHistory::EVENT_UPDATED);

    $this->actingAs($user)
        ->get(route('equipment.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->has('equipmentItems.data', 1, fn (Assert $equipment) => $equipment
                ->where('name', 'Lenovo ThinkPad X1 Carbon')
                ->where('qr_code', 'EQ-LENOVO-001')
                ->where('qr_code_svg_data_uri', null)
                ->etc()
            )
        );
});

test('equipment page validates issued employee only for issued stage', function () {
    $user = User::factory()->create();
    $issuedUser = User::factory()->create();

    $this->actingAs($user)
        ->post(route('equipment.store'), [
            'name' => 'Printer Zebra',
            'status' => EquipmentItem::STATUS_ISSUED,
            'issued_to_user_id' => null,
        ])
        ->assertSessionHasErrors('issued_to_user_id');

    $this->actingAs($user)
        ->post(route('equipment.store'), [
            'name' => 'Printer Zebra',
            'status' => EquipmentItem::STATUS_ON_BALANCE,
            'issued_to_user_id' => $issuedUser->id,
        ])
        ->assertSessionHasErrors('issued_to_user_id');
});

test('equipment is wired into the sidebar and built in menu items', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain("isMenuItemVisible('equipment')")
        ->and($sidebar)->toContain('title: t.value.equipment.title')
        ->and($sidebar)->toContain('href: equipmentIndex()')
        ->and($builtInKeys->all())->toContain('equipment');
});

test('equipment qr svg includes the CRM369 brand mark in the center', function () {
    $equipmentItem = EquipmentItem::factory()->create([
        'qr_code' => 'EQ-BRAND-001',
    ]);

    $svg = $equipmentItem->qrCodeSvg();

    expect($svg)->toContain('>CRM369</text>')
        ->and($svg)->toContain('aria-label="CRM369 mark"')
        ->and($svg)->toContain('stroke="#0f172a"');
});

test('equipment page includes a qr preview and print action in the details dialog', function () {
    $page = file_get_contents(resource_path('js/pages/equipment/Index.vue'));

    expect($page)->toContain('selectedEquipmentItem.qr_code_svg_data_uri')
        ->and($page)->toContain('printEquipmentQr')
        ->and($page)->toContain("equipmentItem.status === 'issued'")
        ->and($page)->toContain('${statusLabel ? `<div class="status">${statusLabel}</div>` : \'\'}')
        ->and($page)->toContain("document.createElement('iframe')")
        ->and($page)->toContain('t.equipment.print_qr')
        ->not->toContain("window.open('', '_blank', 'noopener,noreferrer')");
});

test('equipment page renders only the selected status label inside the edit select trigger', function () {
    $page = file_get_contents(resource_path('js/pages/equipment/Index.vue'));

    expect($page)->toContain('const selectedStatusLabel = computed(() => {')
        ->and($page)->toContain('props.statusOptions.find((statusOption) => statusOption.value === form.status)?.label')
        ->and($page)->toContain('<SelectValue :placeholder="t.equipment.status">')
        ->and($page)->toContain('{{ selectedStatusLabel }}');
});

test('equipment page renders history action near the equipment name and shows combined history entries', function () {
    $user = User::factory()->create();
    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'History Laptop',
        'qr_code' => 'EQ-HISTORY-01',
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    EquipmentItemHistory::query()->create([
        'equipment_item_id' => $equipmentItem->id,
        'event_type' => EquipmentItemHistory::EVENT_CREATED,
        'source' => EquipmentItemHistory::SOURCE_WEB,
        'actor_user_id' => $user->id,
        'changes' => [
            'name' => ['from' => null, 'to' => 'History Laptop'],
        ],
        'snapshot' => [
            'name' => 'History Laptop',
            'qr_code' => 'EQ-HISTORY-01',
            'status' => EquipmentItem::STATUS_ON_BALANCE,
            'responsible_user' => null,
            'issued_to_user' => null,
        ],
        'changed_at' => now()->subDay(),
    ]);

    TsdQrScan::factory()->create([
        'qr_code' => 'EQ-HISTORY-01',
        'normalized_qr_code' => EquipmentItem::normalizeQrCode('EQ-HISTORY-01'),
        'scanned_by_user_id' => $user->id,
        'scanned_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('equipment.index', [
            'equipment' => $equipmentItem->id,
            'dialog' => 'history',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('activeDialog', 'history')
            ->where('activeEquipmentItem.name', 'History Laptop')
            ->where('activeEquipmentItem.history_entries.0.kind', 'scan')
            ->where('activeEquipmentItem.history_entries.1.kind', 'change')
            ->where('activeEquipmentItem.history_entries.1.event_label', __('ui.equipment.history_event_created'))
        );

    $page = file_get_contents(resource_path('js/pages/equipment/Index.vue'));

    expect($page)->toContain("equipmentDialogUrl(equipmentItem.id, 'history')")
        ->and($page)->toContain('t.equipment.history_title')
        ->and($page)->toContain('historyEquipmentItem.history_entries')
        ->and($page)->toContain('{{ t.equipment.history }}');
});

test('dialog components disable pointer events when closed so table actions remain clickable', function () {
    $dialogOverlay = file_get_contents(resource_path('js/components/ui/dialog/DialogOverlay.vue'));
    $dialogContent = file_get_contents(resource_path('js/components/ui/dialog/DialogContent.vue'));

    expect($dialogOverlay)->toContain('data-[state=closed]:pointer-events-none')
        ->and($dialogContent)->toContain('data-[state=closed]:pointer-events-none');
});

test('equipment page can search by name qr code and employee fields', function () {
    $user = User::factory()->create();
    $responsibleUser = User::factory()->create([
        'name' => 'Alice',
        'last_name' => 'Manager',
        'email' => 'alice.manager@example.com',
    ]);

    EquipmentItem::factory()->create([
        'name' => 'Lenovo Search Laptop',
        'qr_code' => 'EQ-SEARCH-001',
        'responsible_user_id' => $responsibleUser->id,
    ]);

    EquipmentItem::factory()->create([
        'name' => 'Zebra Scanner',
        'qr_code' => 'EQ-ZEBRA-002',
    ]);

    $byName = $this->actingAs($user)->get(route('equipment.index', [
        'search' => 'Lenovo',
    ]));
    $byQr = $this->actingAs($user)->get(route('equipment.index', [
        'search' => 'EQ-ZEBRA-002',
    ]));
    $byResponsibleEmail = $this->actingAs($user)->get(route('equipment.index', [
        'search' => 'alice.manager@example.com',
    ]));

    expect($byName->inertiaProps('equipmentItems.meta.total'))->toBe(1)
        ->and(collect($byName->inertiaProps('equipmentItems.data'))->pluck('name')->all())
        ->toBe(['Lenovo Search Laptop'])
        ->and($byName->inertiaProps('filters.search'))->toBe('Lenovo')
        ->and($byName->inertiaProps('filters.status'))->toBe('')
        ->and(collect($byQr->inertiaProps('equipmentItems.data'))->pluck('name')->all())
        ->toBe(['Zebra Scanner'])
        ->and($byQr->inertiaProps('filters.search'))->toBe('EQ-ZEBRA-002')
        ->and($byQr->inertiaProps('filters.status'))->toBe('')
        ->and(collect($byResponsibleEmail->inertiaProps('equipmentItems.data'))->pluck('name')->all())
        ->toBe(['Lenovo Search Laptop'])
        ->and($byResponsibleEmail->inertiaProps('filters.search'))->toBe('alice.manager@example.com')
        ->and($byResponsibleEmail->inertiaProps('filters.status'))->toBe('');
});

test('equipment page can filter items by stage', function () {
    $user = User::factory()->create();

    EquipmentItem::factory()->create([
        'name' => 'Maintenance Scanner',
        'status' => EquipmentItem::STATUS_MAINTENANCE,
    ]);

    EquipmentItem::factory()->create([
        'name' => 'Issued Laptop',
        'status' => EquipmentItem::STATUS_ISSUED,
    ]);

    $response = $this->actingAs($user)->get(route('equipment.index', [
        'status' => EquipmentItem::STATUS_MAINTENANCE,
    ]));

    expect($response->inertiaProps('equipmentItems.meta.total'))->toBe(1)
        ->and(collect($response->inertiaProps('equipmentItems.data'))->pluck('name')->all())
        ->toBe(['Maintenance Scanner'])
        ->and($response->inertiaProps('filters.status'))->toBe(EquipmentItem::STATUS_MAINTENANCE)
        ->and($response->inertiaProps('stats.total'))->toBe(1)
        ->and($response->inertiaProps('stats.maintenance'))->toBe(1);
});

test('equipment page paginates equipment items by 50 per page', function () {
    $user = User::factory()->create();

    EquipmentItem::factory()->count(55)->create();

    $firstPage = $this->actingAs($user)->get(route('equipment.index'));
    $secondPage = $this->actingAs($user)->get(route('equipment.index', ['page' => 2]));

    $firstPage
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('equipmentItems.meta.per_page', 50)
            ->where('equipmentItems.meta.total', 55)
            ->where('equipmentItems.meta.current_page', 1)
            ->where('equipmentItems.meta.last_page', 2)
            ->has('equipmentItems.data', 50)
        );

    $secondPage
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('equipmentItems.meta.current_page', 2)
            ->has('equipmentItems.data', 5)
        );
});

test('equipment page action links preserve filters and target dialog state', function () {
    $page = file_get_contents(resource_path('js/pages/equipment/Index.vue'));

    expect($page)->toContain('const equipmentDialogUrl = (equipmentItemId: number, dialog: Exclude<ActiveDialog, null>): string => {')
        ->and($page)->toContain('query.status = props.filters.status;')
        ->and($page)->toContain('equipment: equipmentItemId')
        ->and($page)->toContain('dialog,')
        ->and($page)->toContain("equipmentDialogUrl(equipmentItem.id, 'details')")
        ->and($page)->toContain("equipmentDialogUrl(equipmentItem.id, 'edit')")
        ->and($page)->toContain("equipmentDialogUrl(equipmentItem.id, 'history')");
});

test('equipment page still opens when history table is missing', function () {
    $user = User::factory()->create();
    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'Fallback Laptop',
        'qr_code' => 'EQ-FALLBACK-01',
    ]);

    if (Schema::hasTable('equipment_item_histories')) {
        Schema::drop('equipment_item_histories');
    }

    $this->actingAs($user)
        ->get(route('equipment.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('equipmentItems.data.0.name', $equipmentItem->name)
            ->where('equipmentItems.data.0.history_entries', [])
        );

    Artisan::call('migrate', [
        '--path' => database_path('migrations/2026_07_15_115347_create_equipment_item_histories_table.php'),
        '--realpath' => true,
        '--force' => true,
    ]);
});

test('equipment page can preselect an item from the query string', function () {
    $user = User::factory()->create();
    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'Scanner Terminal',
        'qr_code' => 'EQ-SCAN-001',
    ]);

    $this->actingAs($user)
        ->get(route('equipment.index', ['equipment' => $equipmentItem->id]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('activeEquipmentItem.id', $equipmentItem->id)
            ->where('activeEquipmentItem.name', 'Scanner Terminal')
            ->where('activeEquipmentItem.qr_code', 'EQ-SCAN-001')
        );
});

test('equipment page can open edit dialog state from the query string', function () {
    $user = User::factory()->create();
    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'Edit Terminal',
        'qr_code' => 'EQ-EDIT-001',
    ]);

    $this->actingAs($user)
        ->get(route('equipment.index', [
            'equipment' => $equipmentItem->id,
            'dialog' => 'edit',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->where('activeDialog', 'edit')
            ->where('activeEquipmentItem.id', $equipmentItem->id)
            ->where('activeEquipmentItem.name', 'Edit Terminal')
        );
});

test('authenticated users can export import equipment as csv and download a template', function () {
    $user = User::factory()->create();
    $responsibleUser = User::factory()->create([
        'email' => 'responsible@example.com',
    ]);
    $issuedUser = User::factory()->create([
        'email' => 'issued@example.com',
    ]);

    EquipmentItem::factory()->create([
        'name' => 'Export Laptop',
        'qr_code' => 'EQ-EXPORT-001',
        'status' => EquipmentItem::STATUS_ON_BALANCE,
        'responsible_user_id' => $responsibleUser->id,
        'issued_to_user_id' => null,
    ]);

    $exportResponse = $this->actingAs($user)
        ->get(route('equipment.export', ['delimiter' => '|']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($exportResponse->streamedContent())
        ->toContain('name|qr_code|status|responsible_user_email|issued_to_user_email')
        ->toContain('Export Laptop')
        ->toContain('responsible@example.com');

    $templateResponse = $this->actingAs($user)
        ->get(route('equipment.template', ['delimiter' => ';']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($templateResponse->streamedContent())
        ->toContain('name;qr_code;status;responsible_user_email;issued_to_user_email')
        ->toContain('EQ-LENOVO-001');

    $csv = <<<'CSV'
name;qr_code;status;responsible_user_email;issued_to_user_email
Imported Printer;EQ-IMPORT-001;issued;responsible@example.com;issued@example.com
CSV;

    $this->actingAs($user)
        ->from(route('equipment.index'))
        ->post(route('equipment.import'), [
            'delimiter' => ';',
            'file' => UploadedFile::fake()->createWithContent('equipment.csv', $csv),
        ])
        ->assertRedirect(route('equipment.index'));

    $importedItem = EquipmentItem::query()->where('qr_code', 'EQ-IMPORT-001')->firstOrFail();

    expect($importedItem->name)->toBe('Imported Printer')
        ->and($importedItem->status)->toBe(EquipmentItem::STATUS_ISSUED)
        ->and($importedItem->responsible_user_id)->toBe($responsibleUser->id)
        ->and($importedItem->issued_to_user_id)->toBe($issuedUser->id)
        ->and(
            EquipmentItemHistory::query()
                ->where('equipment_item_id', $importedItem->id)
                ->value('source')
        )->toBe(EquipmentItemHistory::SOURCE_CSV);
});
