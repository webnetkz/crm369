<?php

use App\Models\EquipmentItem;
use App\Models\User;
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
        ->and($equipmentItem->issued_to_user_id)->toBeNull();

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
        ->and($equipmentItem->fresh()->issued_to_user_id)->toBe($issuedUser->id);

    $this->actingAs($user)
        ->get(route('equipment.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('equipment/Index')
            ->has('equipmentItems', 1, fn (Assert $equipment) => $equipment
                ->where('name', 'Lenovo ThinkPad X1 Carbon')
                ->where('qr_code', 'EQ-LENOVO-001')
                ->where('qr_code_svg_data_uri', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml;utf8,'))
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
