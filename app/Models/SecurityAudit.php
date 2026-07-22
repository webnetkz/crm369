<?php

namespace App\Models;

use Database\Factories\SecurityAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $score
 * @property string $risk_level
 * @property int $passed_count
 * @property int $warning_count
 * @property int $failed_count
 * @property int $skipped_count
 * @property int $total_count
 * @property array<int, array<string, mixed>> $checks
 * @property array<string, bool> $manual_answers
 * @property Carbon $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'score',
    'risk_level',
    'passed_count',
    'warning_count',
    'failed_count',
    'skipped_count',
    'total_count',
    'checks',
    'manual_answers',
    'checked_at',
])]
class SecurityAudit extends Model
{
    /** @use HasFactory<SecurityAuditFactory> */
    use HasFactory;

    protected $attributes = [
        'passed_count' => 0,
        'warning_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'score' => 'integer',
            'passed_count' => 'integer',
            'warning_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'total_count' => 'integer',
            'checks' => 'array',
            'manual_answers' => 'array',
            'checked_at' => 'datetime',
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
