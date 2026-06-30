<?php

namespace App\Models;

use Database\Factories\FileDirectoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int $owner_user_id
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property FileDirectory|null $parent
 */
#[Fillable(['parent_id', 'owner_user_id', 'name', 'sort_order'])]
class FileDirectory extends Model
{
    /** @use HasFactory<FileDirectoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'owner_user_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FileDirectory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<FileDirectory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return HasMany<FileEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(FileEntry::class, 'file_directory_id')
            ->orderBy('original_name');
    }

    /**
     * @return HasMany<FileDirectoryPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(FileDirectoryPermission::class, 'file_directory_id')
            ->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
