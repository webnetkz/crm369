<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateWarehouseRequest extends FormRequest
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
        $attributes = [];

        if ($this->exists('name')) {
            $attributes['name'] = $this->normalizeRequiredString($this->input('name'));
        }

        if ($this->exists('rows')) {
            $attributes['rows'] = $this->normalizeRows($this->input('rows'));
        }

        if ($attributes !== []) {
            $this->merge($attributes);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'area_sqm' => ['sometimes', 'required', 'numeric', 'min:0'],
            'rows' => ['sometimes', 'required', 'array', 'min:1'],
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
     *     name?: string,
     *     area_sqm?: float,
     *     rows?: array<int, array{name: string, columns: array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>}>
     * }
     */
    public function payload(): array
    {
        $validated = Arr::only($this->validated(), ['name', 'area_sqm', 'rows']);

        if (array_key_exists('area_sqm', $validated)) {
            $validated['area_sqm'] = (float) $validated['area_sqm'];
        }

        return $validated;
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
