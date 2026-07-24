<?php

namespace App\Models;

use Database\Factories\ConferenceSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conference_id
 * @property int $sender_participant_id
 * @property int $recipient_participant_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property Carbon $expires_at
 * @property Carbon $created_at
 */
#[Fillable([
    'conference_id',
    'sender_participant_id',
    'recipient_participant_id',
    'type',
    'payload',
    'expires_at',
])]
class ConferenceSignal extends Model
{
    /** @use HasFactory<ConferenceSignalFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return Builder<ConferenceSignal> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /** @return BelongsTo<Conference, $this> */
    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    /** @return BelongsTo<ConferenceParticipant, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(ConferenceParticipant::class, 'sender_participant_id');
    }

    /** @return BelongsTo<ConferenceParticipant, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(ConferenceParticipant::class, 'recipient_participant_id');
    }
}
