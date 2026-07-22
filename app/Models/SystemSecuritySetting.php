<?php

namespace App\Models;

use Database\Factories\SystemSecuritySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property bool $requires_two_factor_authentication
 * @property Carbon|null $enforced_at
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['requires_two_factor_authentication', 'enforced_at', 'updated_by_user_id'])]
class SystemSecuritySetting extends Model
{
    /** @use HasFactory<SystemSecuritySettingFactory> */
    use HasFactory;

    private const string TWO_FACTOR_CACHE_KEY = 'system-security:requires-two-factor-authentication';

    protected $attributes = [
        'requires_two_factor_authentication' => false,
    ];

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::TWO_FACTOR_CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::TWO_FACTOR_CACHE_KEY));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_two_factor_authentication' => 'boolean',
            'enforced_at' => 'datetime',
            'updated_by_user_id' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public static function requiresTwoFactorAuthentication(): bool
    {
        return Cache::remember(
            self::TWO_FACTOR_CACHE_KEY,
            now()->addMinute(),
            fn (): bool => Schema::hasTable((new self)->getTable())
                && (bool) static::query()->value('requires_two_factor_authentication'),
        );
    }

    public static function forgetCachedRequirement(): void
    {
        Cache::forget(self::TWO_FACTOR_CACHE_KEY);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
