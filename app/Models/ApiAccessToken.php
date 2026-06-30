<?php

namespace App\Models;

use Database\Factories\ApiAccessTokenFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property array<int, string>|null $permissions
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip_address
 * @property string|null $last_used_user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'token_prefix',
    'token_hash',
    'permissions',
    'expires_at',
    'last_used_at',
    'last_used_ip_address',
    'last_used_user_agent',
])]
#[Hidden(['token_hash'])]
class ApiAccessToken extends Model
{
    public const int TOKEN_PREFIX_LENGTH = 12;
    public const int TOKEN_ISSUE_ATTEMPTS = 5;

    public const string PERMISSION_PROFILE_READ = 'profile.read';
    public const string PERMISSION_PROFILE_WRITE = 'profile.write';
    public const string PERMISSION_NOTIFICATIONS_READ = 'notifications.read';
    public const string PERMISSION_NOTIFICATIONS_WRITE = 'notifications.write';
    public const string PERMISSION_CHAT_READ = 'chat.read';
    public const string PERMISSION_CHAT_WRITE = 'chat.write';
    public const string PERMISSION_KNOWLEDGE_READ = 'knowledge.read';
    public const string PERMISSION_KNOWLEDGE_WRITE = 'knowledge.write';
    public const string PERMISSION_PROJECTS_READ = 'projects.read';
    public const string PERMISSION_PROJECTS_WRITE = 'projects.write';
    public const string PERMISSION_TASKS_READ = 'tasks.read';
    public const string PERMISSION_TASKS_WRITE = 'tasks.write';
    public const string PERMISSION_USERS_READ = 'users.read';
    public const string PERMISSION_USERS_WRITE = 'users.write';
    public const string PERMISSION_GROUPS_READ = 'groups.read';
    public const string PERMISSION_GROUPS_WRITE = 'groups.write';
    public const string PERMISSION_MENU_READ = 'menu.read';
    public const string PERMISSION_MENU_WRITE = 'menu.write';
    public const string PERMISSION_PORTAL_READ = 'portal.read';
    public const string PERMISSION_PORTAL_WRITE = 'portal.write';
    public const string PERMISSION_INTEGRATIONS_READ = 'integrations.read';
    public const string PERMISSION_INTEGRATIONS_WRITE = 'integrations.write';
    public const string PERMISSION_WEBHOOKS_READ = 'webhooks.read';
    public const string PERMISSION_WEBHOOKS_WRITE = 'webhooks.write';

    /** @use HasFactory<ApiAccessTokenFactory> */
    use HasFactory;

    protected static string $plainTextPrefix = 'crm369_pat_';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, array{label_key: string, description_key: string}>
     */
    public static function permissionDefinitions(): array
    {
        return [
            self::PERMISSION_PROFILE_READ => [
                'label_key' => 'ui.api.permission_profile_read',
                'description_key' => 'ui.api.permission_profile_read_description',
            ],
            self::PERMISSION_PROFILE_WRITE => [
                'label_key' => 'ui.api.permission_profile_write',
                'description_key' => 'ui.api.permission_profile_write_description',
            ],
            self::PERMISSION_NOTIFICATIONS_READ => [
                'label_key' => 'ui.api.permission_notifications_read',
                'description_key' => 'ui.api.permission_notifications_read_description',
            ],
            self::PERMISSION_NOTIFICATIONS_WRITE => [
                'label_key' => 'ui.api.permission_notifications_write',
                'description_key' => 'ui.api.permission_notifications_write_description',
            ],
            self::PERMISSION_CHAT_READ => [
                'label_key' => 'ui.api.permission_chat_read',
                'description_key' => 'ui.api.permission_chat_read_description',
            ],
            self::PERMISSION_CHAT_WRITE => [
                'label_key' => 'ui.api.permission_chat_write',
                'description_key' => 'ui.api.permission_chat_write_description',
            ],
            self::PERMISSION_KNOWLEDGE_READ => [
                'label_key' => 'ui.api.permission_knowledge_read',
                'description_key' => 'ui.api.permission_knowledge_read_description',
            ],
            self::PERMISSION_KNOWLEDGE_WRITE => [
                'label_key' => 'ui.api.permission_knowledge_write',
                'description_key' => 'ui.api.permission_knowledge_write_description',
            ],
            self::PERMISSION_PROJECTS_READ => [
                'label_key' => 'ui.api.permission_projects_read',
                'description_key' => 'ui.api.permission_projects_read_description',
            ],
            self::PERMISSION_PROJECTS_WRITE => [
                'label_key' => 'ui.api.permission_projects_write',
                'description_key' => 'ui.api.permission_projects_write_description',
            ],
            self::PERMISSION_TASKS_READ => [
                'label_key' => 'ui.api.permission_tasks_read',
                'description_key' => 'ui.api.permission_tasks_read_description',
            ],
            self::PERMISSION_TASKS_WRITE => [
                'label_key' => 'ui.api.permission_tasks_write',
                'description_key' => 'ui.api.permission_tasks_write_description',
            ],
            self::PERMISSION_USERS_READ => [
                'label_key' => 'ui.api.permission_users_read',
                'description_key' => 'ui.api.permission_users_read_description',
            ],
            self::PERMISSION_USERS_WRITE => [
                'label_key' => 'ui.api.permission_users_write',
                'description_key' => 'ui.api.permission_users_write_description',
            ],
            self::PERMISSION_GROUPS_READ => [
                'label_key' => 'ui.api.permission_groups_read',
                'description_key' => 'ui.api.permission_groups_read_description',
            ],
            self::PERMISSION_GROUPS_WRITE => [
                'label_key' => 'ui.api.permission_groups_write',
                'description_key' => 'ui.api.permission_groups_write_description',
            ],
            self::PERMISSION_MENU_READ => [
                'label_key' => 'ui.api.permission_menu_read',
                'description_key' => 'ui.api.permission_menu_read_description',
            ],
            self::PERMISSION_MENU_WRITE => [
                'label_key' => 'ui.api.permission_menu_write',
                'description_key' => 'ui.api.permission_menu_write_description',
            ],
            self::PERMISSION_PORTAL_READ => [
                'label_key' => 'ui.api.permission_portal_read',
                'description_key' => 'ui.api.permission_portal_read_description',
            ],
            self::PERMISSION_PORTAL_WRITE => [
                'label_key' => 'ui.api.permission_portal_write',
                'description_key' => 'ui.api.permission_portal_write_description',
            ],
            self::PERMISSION_INTEGRATIONS_READ => [
                'label_key' => 'ui.api.permission_integrations_read',
                'description_key' => 'ui.api.permission_integrations_read_description',
            ],
            self::PERMISSION_INTEGRATIONS_WRITE => [
                'label_key' => 'ui.api.permission_integrations_write',
                'description_key' => 'ui.api.permission_integrations_write_description',
            ],
            self::PERMISSION_WEBHOOKS_READ => [
                'label_key' => 'ui.api.permission_webhooks_read',
                'description_key' => 'ui.api.permission_webhooks_read_description',
            ],
            self::PERMISSION_WEBHOOKS_WRITE => [
                'label_key' => 'ui.api.permission_webhooks_write',
                'description_key' => 'ui.api.permission_webhooks_write_description',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public static function generatePlainTextToken(): string
    {
        return self::$plainTextPrefix.Str::random(64);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /**
     * @return array<int, string>
     */
    public static function prefixCandidatesFor(?string $token): array
    {
        if (! is_string($token) || trim($token) === '') {
            return [];
        }

        $normalizedToken = trim($token);

        return collect([
            self::hashedTokenPrefix($normalizedToken),
            Str::substr($normalizedToken, 0, self::TOKEN_PREFIX_LENGTH),
        ])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{token_prefix: string, token_hash: string}
     */
    public static function tokenAttributes(string $token): array
    {
        return [
            'token_prefix' => self::hashedTokenPrefix($token),
            'token_hash' => self::hashToken($token),
        ];
    }

    /**
     * @return array{api_access_token: self, plain_text_token: string}
     */
    public static function issueToUser(User $user, string $name, array $permissions, ?Carbon $expiresAt): array
    {
        for ($attempt = 0; $attempt < self::TOKEN_ISSUE_ATTEMPTS; $attempt++) {
            $plainTextToken = self::generatePlainTextToken();

            try {
                return [
                    'api_access_token' => self::create([
                        'user_id' => $user->id,
                        'name' => $name,
                        ...self::tokenAttributes($plainTextToken),
                        'permissions' => $permissions,
                        'expires_at' => $expiresAt,
                    ]),
                    'plain_text_token' => $plainTextToken,
                ];
            } catch (UniqueConstraintViolationException $exception) {
                if (! self::wasTokenPrefixCollision($exception) || $attempt === self::TOKEN_ISSUE_ATTEMPTS - 1) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('API token could not be issued.');
    }

    public function issueToken(?string $token = null): string
    {
        if (is_string($token)) {
            $this->forceFill(self::tokenAttributes($token))->save();

            return $token;
        }

        for ($attempt = 0; $attempt < self::TOKEN_ISSUE_ATTEMPTS; $attempt++) {
            $plainTextToken = self::generatePlainTextToken();

            try {
                $this->forceFill(self::tokenAttributes($plainTextToken))->save();

                return $plainTextToken;
            } catch (UniqueConstraintViolationException $exception) {
                if (! self::wasTokenPrefixCollision($exception) || $attempt === self::TOKEN_ISSUE_ATTEMPTS - 1) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('API token could not be re-issued.');
    }

    public function matchesToken(?string $token): bool
    {
        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        return hash_equals($this->token_hash, self::hashToken($token));
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isAvailable(): bool
    {
        return ! $this->isExpired();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->resolvedPermissions(), true);
    }

    public function touchUsage(Request $request): void
    {
        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip_address' => $request->ip(),
            'last_used_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ])->save();
    }

    private static function hashedTokenPrefix(string $token): string
    {
        return Str::substr(self::hashToken($token), 0, self::TOKEN_PREFIX_LENGTH);
    }

    private static function wasTokenPrefixCollision(UniqueConstraintViolationException $exception): bool
    {
        return str_contains($exception->getMessage(), 'api_access_tokens.token_prefix');
    }
}
