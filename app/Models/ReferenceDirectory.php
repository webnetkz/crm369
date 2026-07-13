<?php

namespace App\Models;

use Database\Factories\ReferenceDirectoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property array<int, array{key: string, label: string, type: string, is_required: bool}>|null $columns
 * @property bool $csv_exchange_enabled
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'columns', 'csv_exchange_enabled', 'created_by_user_id', 'updated_by_user_id'])]
class ReferenceDirectory extends Model
{
    public const string FIELD_TYPE_TEXT = 'text';

    public const string FIELD_TYPE_TEXTAREA = 'textarea';

    public const string FIELD_TYPE_NUMBER = 'number';

    public const string FIELD_TYPE_DATE = 'date';

    public const string FIELD_TYPE_BOOLEAN = 'boolean';

    /** @use HasFactory<ReferenceDirectoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'columns' => 'json:unicode',
            'csv_exchange_enabled' => 'boolean',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableColumnTypes(): array
    {
        return [
            self::FIELD_TYPE_TEXT,
            self::FIELD_TYPE_TEXTAREA,
            self::FIELD_TYPE_NUMBER,
            self::FIELD_TYPE_DATE,
            self::FIELD_TYPE_BOOLEAN,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, is_required: bool}>
     */
    public function columnDefinitions(): array
    {
        return self::normalizeColumns($this->columns);
    }

    /**
     * @return array<string, array{key: string, label: string, type: string, is_required: bool}>
     */
    public function columnDefinitionMap(): array
    {
        return collect($this->columnDefinitions())
            ->mapWithKeys(fn (array $column): array => [
                $column['key'] => $column,
            ])
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ReferenceDirectoryRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(ReferenceDirectoryRecord::class)
            ->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, is_required: bool}>
     */
    public static function normalizeColumns(mixed $columns): array
    {
        return collect(is_array($columns) ? $columns : [])
            ->filter(fn (mixed $column): bool => is_array($column))
            ->map(function (array $column): ?array {
                $label = is_string($column['label'] ?? null) ? trim($column['label']) : '';
                $providedKey = is_string($column['key'] ?? null) ? trim($column['key']) : '';
                $type = is_string($column['type'] ?? null) ? trim($column['type']) : self::FIELD_TYPE_TEXT;
                $baseKey = $providedKey !== '' ? $providedKey : $label;

                $normalizedKey = Str::of(Str::slug($baseKey, '_'))
                    ->replace('-', '_')
                    ->replaceMatches('/_+/', '_')
                    ->trim('_')
                    ->value();

                if (
                    $label === ''
                    || $normalizedKey === ''
                    || ! in_array($type, self::availableColumnTypes(), true)
                ) {
                    return null;
                }

                return [
                    'key' => $normalizedKey,
                    'label' => $label,
                    'type' => $type,
                    'is_required' => (bool) ($column['is_required'] ?? false),
                ];
            })
            ->filter(fn (mixed $column): bool => is_array($column))
            ->unique('key')
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function normalizedRecordValues(mixed $values): array
    {
        $input = is_array($values) ? $values : [];
        $normalized = [];

        foreach ($this->columnDefinitions() as $column) {
            $normalized[$column['key']] = self::normalizeValueForType(
                $input[$column['key']] ?? null,
                $column['type'],
            );
        }

        return $normalized;
    }

    private static function normalizeValueForType(mixed $value, string $type): bool|float|int|string|null
    {
        return match ($type) {
            self::FIELD_TYPE_NUMBER => self::normalizeNumberValue($value),
            self::FIELD_TYPE_BOOLEAN => self::normalizeBooleanValue($value),
            default => self::normalizeStringValue($value),
        };
    }

    private static function normalizeBooleanValue(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (! is_string($value)) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }

    private static function normalizeNumberValue(mixed $value): float|int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return is_numeric($normalized) ? $normalized + 0 : $normalized;
    }

    private static function normalizeStringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
