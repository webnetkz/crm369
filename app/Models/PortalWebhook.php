<?php

namespace App\Models;

use Database\Factories\PortalWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property array<int, string>|null $permissions
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'token_prefix', 'token_hash', 'permissions', 'is_active', 'expires_at', 'last_used_at', 'created_by_user_id'])]
#[Hidden(['token_hash'])]
class PortalWebhook extends Model
{
    public const string PERMISSION_USERS_READ = 'users.read';

    public const string PERMISSION_USERS_WRITE = 'users.write';

    public const string PERMISSION_CONTACTS_READ = 'contacts.read';

    public const string PERMISSION_CONTACTS_WRITE = 'contacts.write';

    public const string PERMISSION_EDO_READ = 'edo.read';

    public const string PERMISSION_EDO_WRITE = 'edo.write';

    public const string PERMISSION_KNOWLEDGE_READ = 'knowledge.read';

    public const string PERMISSION_KNOWLEDGE_WRITE = 'knowledge.write';

    public const string PERMISSION_PROJECTS_READ = 'projects.read';

    public const string PERMISSION_PROJECTS_WRITE = 'projects.write';

    public const string PERMISSION_CHAT_READ = 'chat.read';

    public const string PERMISSION_CHAT_WRITE = 'chat.write';

    public const string PERMISSION_NOTIFICATIONS_READ = 'notifications.read';

    public const string PERMISSION_WAREHOUSES_READ = 'warehouses.read';

    public const string PERMISSION_WAREHOUSES_WRITE = 'warehouses.write';

    public const string PERMISSION_EQUIPMENT_READ = 'equipment.read';

    public const string PERMISSION_EQUIPMENT_WRITE = 'equipment.write';

    public const string PERMISSION_TSD_READ = 'tsd.read';

    public const string PERMISSION_TSD_WRITE = 'tsd.write';

    /** @use HasFactory<PortalWebhookFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'created_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function permissionDefinitions(): array
    {
        return [
            self::PERMISSION_USERS_READ => [
                'label_key' => 'ui.webhooks.permission_users_read',
                'description_key' => 'ui.webhooks.permission_users_read_description',
            ],
            self::PERMISSION_USERS_WRITE => [
                'label_key' => 'ui.webhooks.permission_users_write',
                'description_key' => 'ui.webhooks.permission_users_write_description',
            ],
            self::PERMISSION_CONTACTS_READ => [
                'label_key' => 'ui.webhooks.permission_contacts_read',
                'description_key' => 'ui.webhooks.permission_contacts_read_description',
            ],
            self::PERMISSION_CONTACTS_WRITE => [
                'label_key' => 'ui.webhooks.permission_contacts_write',
                'description_key' => 'ui.webhooks.permission_contacts_write_description',
            ],
            self::PERMISSION_EDO_READ => [
                'label_key' => 'ui.webhooks.permission_edo_read',
                'description_key' => 'ui.webhooks.permission_edo_read_description',
            ],
            self::PERMISSION_EDO_WRITE => [
                'label_key' => 'ui.webhooks.permission_edo_write',
                'description_key' => 'ui.webhooks.permission_edo_write_description',
            ],
            self::PERMISSION_KNOWLEDGE_READ => [
                'label_key' => 'ui.webhooks.permission_knowledge_read',
                'description_key' => 'ui.webhooks.permission_knowledge_read_description',
            ],
            self::PERMISSION_KNOWLEDGE_WRITE => [
                'label_key' => 'ui.webhooks.permission_knowledge_write',
                'description_key' => 'ui.webhooks.permission_knowledge_write_description',
            ],
            self::PERMISSION_PROJECTS_READ => [
                'label_key' => 'ui.webhooks.permission_projects_read',
                'description_key' => 'ui.webhooks.permission_projects_read_description',
            ],
            self::PERMISSION_PROJECTS_WRITE => [
                'label_key' => 'ui.webhooks.permission_projects_write',
                'description_key' => 'ui.webhooks.permission_projects_write_description',
            ],
            self::PERMISSION_CHAT_READ => [
                'label_key' => 'ui.webhooks.permission_chat_read',
                'description_key' => 'ui.webhooks.permission_chat_read_description',
            ],
            self::PERMISSION_CHAT_WRITE => [
                'label_key' => 'ui.webhooks.permission_chat_write',
                'description_key' => 'ui.webhooks.permission_chat_write_description',
            ],
            self::PERMISSION_NOTIFICATIONS_READ => [
                'label_key' => 'ui.webhooks.permission_notifications_read',
                'description_key' => 'ui.webhooks.permission_notifications_read_description',
            ],
            self::PERMISSION_WAREHOUSES_READ => [
                'label_key' => 'ui.webhooks.permission_warehouses_read',
                'description_key' => 'ui.webhooks.permission_warehouses_read_description',
            ],
            self::PERMISSION_WAREHOUSES_WRITE => [
                'label_key' => 'ui.webhooks.permission_warehouses_write',
                'description_key' => 'ui.webhooks.permission_warehouses_write_description',
            ],
            self::PERMISSION_EQUIPMENT_READ => [
                'label_key' => 'ui.webhooks.permission_equipment_read',
                'description_key' => 'ui.webhooks.permission_equipment_read_description',
            ],
            self::PERMISSION_EQUIPMENT_WRITE => [
                'label_key' => 'ui.webhooks.permission_equipment_write',
                'description_key' => 'ui.webhooks.permission_equipment_write_description',
            ],
            self::PERMISSION_TSD_READ => [
                'label_key' => 'ui.webhooks.permission_tsd_read',
                'description_key' => 'ui.webhooks.permission_tsd_read_description',
            ],
            self::PERMISSION_TSD_WRITE => [
                'label_key' => 'ui.webhooks.permission_tsd_write',
                'description_key' => 'ui.webhooks.permission_tsd_write_description',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public function resolvedPermissions(): array
    {
        return collect($this->permissions ?? [])
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

    public static function generatePlainTextToken(): string
    {
        return Str::random(64);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array{token_prefix: string, token_hash: string}
     */
    public static function tokenAttributes(string $token): array
    {
        return [
            'token_prefix' => Str::substr($token, 0, 12),
            'token_hash' => self::hashToken($token),
        ];
    }

    public function issueToken(?string $token = null): string
    {
        $plainTextToken = $token ?? self::generatePlainTextToken();

        $this->forceFill(self::tokenAttributes($plainTextToken))->save();

        return $plainTextToken;
    }

    public function matchesToken(?string $token): bool
    {
        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        return hash_equals($this->token_hash, self::hashToken(trim($token)));
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function endpointUrl(): string
    {
        return route('portal-webhooks.invoke', $this);
    }

    public function signedUrl(string $token): string
    {
        return $this->endpointUrl().'?token='.urlencode($token);
    }
}
