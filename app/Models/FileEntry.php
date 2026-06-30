<?php

namespace App\Models;

use Database\Factories\FileEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $file_directory_id
 * @property int $owner_user_id
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['file_directory_id', 'owner_user_id', 'original_name', 'disk', 'path', 'mime_type', 'extension', 'size_bytes'])]
class FileEntry extends Model
{
    /** @use HasFactory<FileEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_directory_id' => 'integer',
            'owner_user_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FileDirectory, $this>
     */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(FileDirectory::class, 'file_directory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
