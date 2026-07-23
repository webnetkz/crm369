<?php

namespace App\Models;

use Database\Factories\MobileDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property string $platform
 * @property string|null $name
 * @property string|null $app_version
 * @property string $fcm_token
 * @property string $fcm_token_hash
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'device_id',
    'platform',
    'name',
    'app_version',
    'fcm_token',
    'fcm_token_hash',
    'last_seen_at',
    'disabled_at',
])]
#[Hidden(['fcm_token', 'fcm_token_hash'])]
class MobileDevice extends Model
{
    /** @use HasFactory<MobileDeviceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fcm_token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disable(): void
    {
        $this->forceFill(['disabled_at' => now()])->save();
    }
}
