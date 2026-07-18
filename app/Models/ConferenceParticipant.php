<?php

namespace App\Models;

use Database\Factories\ConferenceParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conference_id
 * @property int|null $user_id
 * @property string $display_name
 * @property string $access_token_hash
 * @property bool $is_guest
 * @property Carbon $joined_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $left_at
 */
#[Fillable([
    'conference_id',
    'user_id',
    'display_name',
    'access_token_hash',
    'is_guest',
    'joined_at',
    'last_seen_at',
    'left_at',
])]
class ConferenceParticipant extends Model
{
    /** @use HasFactory<ConferenceParticipantFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conference, $this> */
    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ConferenceSignal, $this> */
    public function sentSignals(): HasMany
    {
        return $this->hasMany(ConferenceSignal::class, 'sender_participant_id');
    }

    /** @return HasMany<ConferenceSignal, $this> */
    public function receivedSignals(): HasMany
    {
        return $this->hasMany(ConferenceSignal::class, 'recipient_participant_id');
    }

    public function tokenMatches(string $token): bool
    {
        return hash_equals($this->access_token_hash, hash('sha256', $token));
    }
}
