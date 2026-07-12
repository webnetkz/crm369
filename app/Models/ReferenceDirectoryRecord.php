<?php

namespace App\Models;

use Database\Factories\ReferenceDirectoryRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $reference_directory_id
 * @property array<string, bool|float|int|string|null>|null $values
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reference_directory_id', 'values', 'created_by_user_id', 'updated_by_user_id'])]
class ReferenceDirectoryRecord extends Model
{
    /** @use HasFactory<ReferenceDirectoryRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_directory_id' => 'integer',
            'values' => 'json:unicode',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
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
     * @return BelongsTo<ReferenceDirectory, $this>
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(ReferenceDirectory::class, 'reference_directory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
