<?php

namespace App\Models;

use Database\Factories\FileDirectoryPermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $file_directory_id
 * @property int|null $user_id
 * @property int|null $user_group_id
 * @property int|null $granted_by_user_id
 * @property string $access_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['file_directory_id', 'user_id', 'user_group_id', 'granted_by_user_id', 'access_level'])]
class FileDirectoryPermission extends Model
{
    public const string ACCESS_READ = 'read';

    public const string ACCESS_EDIT = 'edit';

    /** @use HasFactory<FileDirectoryPermissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_directory_id' => 'integer',
            'user_id' => 'integer',
            'user_group_id' => 'integer',
            'granted_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableAccessLevels(): array
    {
        return [
            self::ACCESS_READ,
            self::ACCESS_EDIT,
        ];
    }

    public function grantsEdit(): bool
    {
        return $this->access_level === self::ACCESS_EDIT;
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<UserGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
