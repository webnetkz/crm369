<?php

namespace App\Models;

use Database\Factories\MessengerIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $driver
 * @property string $name
 * @property bool $is_active
 * @property array<string, string>|null $settings
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['driver', 'name', 'is_active', 'settings', 'updated_by_user_id'])]
class MessengerIntegration extends Model
{
    public const string DRIVER_WHATSAPP_BUSINESS = 'whatsapp_business';

    public const string DRIVER_TELEGRAM = 'telegram';

    public const string ACCESS_NONE = 'none';

    public const string ACCESS_VIEW = 'view';

    public const string ACCESS_REPLY = 'reply';

    public const string ACCESS_FULL = 'full_access';

    /** @use HasFactory<MessengerIntegrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function drivers(): array
    {
        return [
            self::DRIVER_WHATSAPP_BUSINESS,
            self::DRIVER_TELEGRAM,
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function driverDefinitions(): array
    {
        return [
            self::DRIVER_WHATSAPP_BUSINESS => [
                'label_key' => 'ui.integrations.whatsapp_business',
                'description_key' => 'ui.integrations.whatsapp_business_description',
            ],
            self::DRIVER_TELEGRAM => [
                'label_key' => 'ui.integrations.telegram',
                'description_key' => 'ui.integrations.telegram_description',
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function defaultSettingsForDriver(string $driver): array
    {
        return match ($driver) {
            self::DRIVER_WHATSAPP_BUSINESS => [
                'api_url' => null,
                'channel_id' => null,
                'phone_number' => null,
                'api_token' => null,
            ],
            self::DRIVER_TELEGRAM => [
                'bot_username' => null,
                'bot_token' => null,
                'webhook_secret' => null,
            ],
            default => [],
        };
    }

    /**
     * @return HasMany<MessengerIntegrationGroupAccess, $this>
     */
    public function groupAccesses(): HasMany
    {
        return $this->hasMany(MessengerIntegrationGroupAccess::class)
            ->orderBy('user_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<string, string|null>
     */
    public function normalizedSettings(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return collect(self::defaultSettingsForDriver($this->driver))
            ->mapWithKeys(function (mixed $defaultValue, string $key) use ($settings): array {
                $value = $settings[$key] ?? $defaultValue;

                if (! is_string($value)) {
                    return [$key => null];
                }

                $trimmed = trim($value);

                return [$key => $trimmed !== '' ? $trimmed : null];
            })
            ->all();
    }

    public function accessLevelForUser(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return self::ACCESS_FULL;
        }

        if (! $user->user_group_id) {
            return self::ACCESS_NONE;
        }

        if ($this->relationLoaded('groupAccesses')) {
            return $this->groupAccesses
                ->firstWhere('user_group_id', $user->user_group_id)?->access_level
                ?? self::ACCESS_NONE;
        }

        return $this->groupAccesses()
            ->where('user_group_id', $user->user_group_id)
            ->value('access_level')
            ?? self::ACCESS_NONE;
    }

    public function canUserView(User $user): bool
    {
        return in_array($this->accessLevelForUser($user), [
            self::ACCESS_VIEW,
            self::ACCESS_REPLY,
            self::ACCESS_FULL,
        ], true);
    }

    public function canUserReply(User $user, ?int $ownerUserId = null): bool
    {
        $accessLevel = $this->accessLevelForUser($user);

        if ($accessLevel === self::ACCESS_FULL) {
            return true;
        }

        if ($accessLevel !== self::ACCESS_REPLY) {
            return false;
        }

        return $ownerUserId === null || $ownerUserId === $user->id;
    }
}
