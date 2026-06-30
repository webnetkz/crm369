<?php

namespace App\Models;

use Database\Factories\PortalFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property int $target_user_id
 * @property string $name
 * @property string|null $description
 * @property string $submission_mode
 * @property string $public_token
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['owner_user_id', 'target_user_id', 'name', 'description', 'submission_mode', 'public_token', 'is_active'])]
class PortalForm extends Model
{
    public const string SUBMISSION_MODE_TASK = 'task';

    public const string SUBMISSION_MODE_CHAT = 'chat';

    /** @use HasFactory<PortalFormFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'target_user_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableSubmissionModes(): array
    {
        return [
            self::SUBMISSION_MODE_TASK,
            self::SUBMISSION_MODE_CHAT,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return HasMany<PortalFormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(PortalFormField::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<PortalFormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(PortalFormSubmission::class)
            ->latest('id');
    }
}
