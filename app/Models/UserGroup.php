<?php

namespace App\Models;

use Database\Factories\UserGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array<int, string>|null $permissions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'permissions'])]
class UserGroup extends Model
{
    public const string ADMINISTRATORS_NAME = 'Administrators';
    public const string PERMISSION_VIEW_USERS = 'view_users';
    public const string PERMISSION_MANAGE_USER_ACTIVATION = 'manage_user_activation';
    public const string PERMISSION_MANAGE_USER_ACCOUNTS = 'manage_user_accounts';
    public const string PERMISSION_IMPERSONATE_USERS = 'impersonate_users';

    /** @use HasFactory<UserGroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsToMany<CrmFunnel, $this>
     */
    public function crmFunnels(): BelongsToMany
    {
        return $this->belongsToMany(CrmFunnel::class, 'crm_funnel_user_group')
            ->withTimestamps();
    }

    public function displayName(): string
    {
        return $this->name === self::ADMINISTRATORS_NAME
            ? __('ui.admin.administrators_group')
            : $this->name;
    }

    public function displayDescription(): ?string
    {
        return $this->name === self::ADMINISTRATORS_NAME
            ? __('ui.admin.administrators_group_description')
            : $this->description;
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function permissionDefinitions(): array
    {
        return [
            self::PERMISSION_VIEW_USERS => [
                'label_key' => 'ui.admin.permission_view_users',
                'description_key' => 'ui.admin.permission_view_users_description',
            ],
            self::PERMISSION_MANAGE_USER_ACTIVATION => [
                'label_key' => 'ui.admin.permission_manage_user_activation',
                'description_key' => 'ui.admin.permission_manage_user_activation_description',
            ],
            self::PERMISSION_MANAGE_USER_ACCOUNTS => [
                'label_key' => 'ui.admin.permission_manage_user_accounts',
                'description_key' => 'ui.admin.permission_manage_user_accounts_description',
            ],
            self::PERMISSION_IMPERSONATE_USERS => [
                'label_key' => 'ui.admin.permission_impersonate_users',
                'description_key' => 'ui.admin.permission_impersonate_users_description',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availablePermissions(): array
    {
        return array_keys(self::permissionDefinitions());
    }

    /**
     * @return array<int, string>
     */
    public static function defaultPermissionsForName(string $name): array
    {
        if ($name !== self::ADMINISTRATORS_NAME) {
            return [];
        }

        return [
            self::PERMISSION_VIEW_USERS,
            self::PERMISSION_MANAGE_USER_ACTIVATION,
            self::PERMISSION_MANAGE_USER_ACCOUNTS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPermissions(): array
    {
        $permissions = is_array($this->permissions)
            ? $this->permissions
            : self::defaultPermissionsForName($this->name);

        return collect($permissions)
            ->filter(fn (mixed $permission): bool => is_string($permission))
            ->filter(fn (string $permission): bool => in_array($permission, self::availablePermissions(), true))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->resolvedPermissions(), true);
    }
}
