<?php

namespace App\Models;

use Database\Factories\ConferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $room_name
 * @property string $public_token
 * @property int $created_by_user_id
 * @property Carbon|null $starts_at
 * @property Carbon|null $ended_at
 * @property bool $allow_external_guests
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'description',
    'room_name',
    'public_token',
    'created_by_user_id',
    'starts_at',
    'ended_at',
    'allow_external_guests',
])]
class Conference extends Model
{
    public const string STATUS_SCHEDULED = 'scheduled';

    public const string STATUS_LIVE = 'live';

    public const string STATUS_ENDED = 'ended';

    /** @use HasFactory<ConferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ended_at' => 'datetime',
            'allow_external_guests' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ConferenceInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ConferenceInvitation::class)
            ->orderByDesc('created_at');
    }

    /** @return HasMany<ConferenceParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConferenceParticipant::class);
    }

    /** @return HasMany<ConferenceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConferenceMessage::class);
    }

    /**
     * @param  Builder<Conference>  $query
     * @return Builder<Conference>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $conferenceQuery) use ($user): void {
            $conferenceQuery
                ->where('created_by_user_id', $user->id)
                ->orWhereHas('invitations', fn (Builder $invitationQuery) => $invitationQuery->where('user_id', $user->id));
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->isSuperAdmin() || $this->created_by_user_id === $user->id) {
            return true;
        }

        if ($this->relationLoaded('invitations')) {
            return $this->invitations->contains(
                fn (ConferenceInvitation $invitation): bool => $invitation->user_id === $user->id,
            );
        }

        return $this->invitations()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->created_by_user_id === $user->id;
    }

    public function status(): string
    {
        if ($this->ended_at !== null) {
            return self::STATUS_ENDED;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_LIVE;
    }

    public function allowsPublicJoin(): bool
    {
        return $this->allow_external_guests;
    }

    public static function generateRoomName(): string
    {
        return 'crm369-'.Str::lower((string) Str::ulid());
    }

    public static function generatePublicToken(): string
    {
        return Str::random(40);
    }
}
