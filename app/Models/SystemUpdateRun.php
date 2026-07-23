<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $requested_by_user_id
 * @property string $component
 * @property string $status
 * @property string|null $current_version
 * @property string|null $target_version
 * @property string|null $target_reference
 * @property int $progress
 * @property string|null $stage
 * @property string|null $message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uuid',
    'requested_by_user_id',
    'component',
    'status',
    'current_version',
    'target_version',
    'target_reference',
    'progress',
    'stage',
    'message',
    'started_at',
    'finished_at',
])]
class SystemUpdateRun extends Model
{
    protected $attributes = [
        'status' => 'queued',
        'progress' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_by_user_id' => 'integer',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }
}
