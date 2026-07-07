<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizeRequiredString($this->input('name')),
            'rows' => $this->normalizeRows($this->input('rows')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'area_sqm' => ['required', 'numeric', 'min:0'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.columns' => ['required', 'array', 'min:1'],
            'rows.*.columns.*.name' => ['required', 'string', 'max:255'],
            'rows.*.columns.*.floors' => ['required', 'array', 'min:1'],
            'rows.*.columns.*.floors.*.name' => ['required', 'string', 'max:255'],
            'rows.*.columns.*.floors.*.places' => ['required', 'array', 'min:1'],
            'rows.*.columns.*.floors.*.places.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     area_sqm: float,
     *     rows: array<int, array{name: string, columns: array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>}>
     * }
     */
    public function payload(): array
    {
        return [
            'name' => $this->validated('name'),
            'area_sqm' => (float) $this->validated('area_sqm'),
            'rows' => $this->validated('rows'),
        ];
    }

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, array{name: string|null, columns: array<int, array{name: string|null, floors: array<int, array{name: string|null, places: array<int, array{name: string|null}>}>}>}>|mixed
     */
    private function normalizeRows(mixed $rows): mixed
    {
        if (! is_array($rows)) {
            return $rows;
        }

        return collect($rows)
            ->map(function (mixed $row): mixed {
                if (! is_array($row)) {
                    return $row;
                }

                return [
                    'name' => $this->normalizeNullableString($row['name'] ?? null),
                    'columns' => collect(is_array($row['columns'] ?? null) ? $row['columns'] : [])
                        ->map(function (mixed $column): mixed {
                            if (! is_array($column)) {
                                return $column;
                            }

                            return [
                                'name' => $this->normalizeNullableString($column['name'] ?? null),
                                'floors' => collect(is_array($column['floors'] ?? null) ? $column['floors'] : [])
                                    ->map(function (mixed $floor): mixed {
                                        if (! is_array($floor)) {
                                            return $floor;
                                        }

                                        return [
                                            'name' => $this->normalizeNullableString($floor['name'] ?? null),
                                            'places' => collect(is_array($floor['places'] ?? null) ? $floor['places'] : [])
                                                ->map(function (mixed $place): mixed {
                                                    if (! is_array($place)) {
                                                        return $place;
                                                    }

                                                    return [
                                                        'name' => $this->normalizeNullableString($place['name'] ?? null),
                                                    ];
                                                })
                                                ->values()
                                                ->all(),
                                        ];
                                    })
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
