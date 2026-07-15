<?php

namespace App\Models;

use Database\Factories\ConferenceInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conference_id
 * @property int $user_id
 * @property int|null $invited_by_user_id
 * @property Carbon|null $joined_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'conference_id',
    'user_id',
    'invited_by_user_id',
    'joined_at',
    'last_opened_at',
])]
class ConferenceInvitation extends Model
{
    /** @use HasFactory<ConferenceInvitationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conference, $this>
     */
    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
