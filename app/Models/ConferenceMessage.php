<?php

namespace App\Models;

use Database\Factories\ConferenceMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conference_id
 * @property int|null $participant_id
 * @property string $display_name
 * @property string $body
 * @property Carbon $created_at
 */
#[Fillable([
    'conference_id',
    'participant_id',
    'display_name',
    'body',
])]
class ConferenceMessage extends Model
{
    /** @use HasFactory<ConferenceMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conference, $this> */
    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    /** @return BelongsTo<ConferenceParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(ConferenceParticipant::class);
    }
}
