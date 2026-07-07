<?php

use App\Models\ApiAccessToken;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Testing\Fluent\AssertableJson;

function warehouseApiAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function issueWarehouseApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Warehouse token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

function warehouseApiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('warehouse api endpoints create read update and delete warehouse hierarchies with qr codes', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => warehouseApiAdministratorsGroup()->id,
    ]);

    $token = issueWarehouseApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_WAREHOUSES_READ,
        ApiAccessToken::PERMISSION_WAREHOUSES_WRITE,
    ]);

    $createResponse = $this->withHeaders(warehouseApiHeadersFor($token))
        ->postJson(route('api.v1.warehouses.store'), warehouseHierarchyPayload('API склад', 980.75))
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('message', __('ui.warehouses.created_success'))
            ->where('data.name', 'API склад')
            ->where('data.area_sqm', 980.75)
            ->where('data.row_count', 2)
            ->where('data.column_count', 2)
            ->where('data.floor_count', 2)
            ->where('data.place_count', 3)
            ->whereType('data.qr_code', 'string')
            ->whereType('data.rows.0.qr_code', 'string')
            ->whereType('data.rows.0.columns.0.qr_code', 'string')
            ->whereType('data.rows.0.columns.0.floors.0.qr_code', 'string')
            ->whereType('data.rows.0.columns.0.floors.0.places.0.qr_code', 'string')
            ->etc()
        );

    $warehouseId = $createResponse->json('data.id');

    $this->withHeaders(warehouseApiHeadersFor($token))
        ->getJson(route('api.v1.warehouses.index'))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('meta.total', 1)
            ->where('meta.row_count', 2)
            ->where('data.0.id', $warehouseId)
            ->where('data.0.rows.0.name', 'Ряд A')
            ->etc()
        );

    $this->withHeaders(warehouseApiHeadersFor($token))
        ->getJson(route('api.v1.warehouses.show', $warehouseId))
        ->assertOk()
        ->assertJsonPath('data.rows.1.columns.0.floors.0.places.0.name', 'B-02-1-001');

    $this->withHeaders(warehouseApiHeadersFor($token))
        ->patchJson(route('api.v1.warehouses.update', $warehouseId), [
            'area_sqm' => 1045.25,
            'rows' => [
                [
                    'name' => 'Ряд C',
                    'columns' => [
                        [
                            'name' => 'Колонка 05',
                            'floors' => [
                                [
                                    'name' => 'Этаж 2',
                                    'places' => [
                                        ['name' => 'C-05-2-001'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('message', __('ui.warehouses.updated_success'))
            ->where('data.area_sqm', 1045.25)
            ->where('data.row_count', 1)
            ->where('data.place_count', 1)
            ->where('data.rows.0.name', 'Ряд C')
            ->etc()
        );

    $this->withHeaders(warehouseApiHeadersFor($token))
        ->deleteJson(route('api.v1.warehouses.destroy', $warehouseId))
        ->assertOk()
        ->assertJsonPath('data.id', $warehouseId);
});

test('warehouse api write actions are blocked without write permission', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => warehouseApiAdministratorsGroup()->id,
    ]);

    $token = issueWarehouseApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_WAREHOUSES_READ,
    ]);

    $this->withHeaders(warehouseApiHeadersFor($token))
        ->postJson(route('api.v1.warehouses.store'), warehouseHierarchyPayload('Только чтение'))
        ->assertForbidden();
});
