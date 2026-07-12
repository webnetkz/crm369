<?php

namespace App\Models;

use Database\Factories\UserLoginActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $platform
 * @property string $device_type
 * @property string $device_signature
 * @property bool $is_new_device
 * @property bool $is_new_ip
 * @property Carbon $logged_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'ip_address',
    'user_agent',
    'browser',
    'platform',
    'device_type',
    'device_signature',
    'is_new_device',
    'is_new_ip',
    'logged_in_at',
])]
class UserLoginActivity extends Model
{
    /** @use HasFactory<UserLoginActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_new_device' => 'boolean',
            'is_new_ip' => 'boolean',
            'logged_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
