<?php

namespace App\Models;

use Database\Factories\CrmFunnelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property bool $is_active
 * @property array<int, array{key: string, label: string, type: string, is_required: bool}>|null $deal_fields
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'color', 'is_active', 'deal_fields', 'created_by_user_id', 'updated_by_user_id'])]
class CrmFunnel extends Model
{
    public const string FIELD_TYPE_TEXT = 'text';

    public const string FIELD_TYPE_TEXTAREA = 'textarea';

    public const string FIELD_TYPE_NUMBER = 'number';

    public const string FIELD_TYPE_DATE = 'date';

    /** @use HasFactory<CrmFunnelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deal_fields' => 'array',
        ];
    }

    /**
     * @param  Builder<CrmFunnel>  $query
     * @return Builder<CrmFunnel>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if (! $user->user_group_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('groups', fn (Builder $groupQuery): Builder => $groupQuery->whereKey($user->user_group_id));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<CrmFunnelStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(CrmFunnelStage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<CrmDeal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class)
            ->orderBy('sort_order')
            ->orderByDesc('updated_at');
    }

    /**
     * @return BelongsToMany<UserGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'crm_funnel_user_group')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->user_group_id) {
            return false;
        }

        if ($this->relationLoaded('groups')) {
            return $this->groups->contains('id', $user->user_group_id);
        }

        return $this->groups()
            ->whereKey($user->user_group_id)
            ->exists();
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->canManageFunnels();
    }

    /**
     * @return array<int, string>
     */
    public static function availableFieldTypes(): array
    {
        return [
            self::FIELD_TYPE_TEXT,
            self::FIELD_TYPE_TEXTAREA,
            self::FIELD_TYPE_NUMBER,
            self::FIELD_TYPE_DATE,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, is_required: bool}>
     */
    public function dealFieldDefinitions(): array
    {
        return self::normalizeDealFields($this->deal_fields);
    }

    /**
     * @param  mixed  $fields
     * @return array<int, array{key: string, label: string, type: string, is_required: bool}>
     */
    public static function normalizeDealFields(mixed $fields): array
    {
        return collect(is_array($fields) ? $fields : [])
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(function (array $field): ?array {
                $label = is_string($field['label'] ?? null) ? trim($field['label']) : '';
                $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
                $type = is_string($field['type'] ?? null) ? trim($field['type']) : self::FIELD_TYPE_TEXT;

                $normalizedKey = Str::of($key)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9_]+/', '_')
                    ->trim('_')
                    ->value();

                if ($label === '' || $normalizedKey === '' || ! in_array($type, self::availableFieldTypes(), true)) {
                    return null;
                }

                return [
                    'key' => $normalizedKey,
                    'label' => $label,
                    'type' => $type,
                    'is_required' => (bool) ($field['is_required'] ?? false),
                ];
            })
            ->filter(fn (mixed $field): bool => is_array($field))
            ->unique('key')
            ->values()
            ->all();
    }
}
