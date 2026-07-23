<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MobileAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_id
 * @property string $token_prefix
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip_address
 * @property string|null $last_used_user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'device_id',
    'token_prefix',
    'token_hash',
    'expires_at',
    'last_used_at',
    'last_used_ip_address',
    'last_used_user_agent',
])]
#[Hidden(['token_hash'])]
class MobileAccessToken extends Model
{
    /** @use HasFactory<MobileAccessTokenFactory> */
    use HasFactory;

    public const int TOKEN_PREFIX_LENGTH = 12;

    public const int TOKEN_ISSUE_ATTEMPTS = 5;

    private const string PLAIN_TEXT_PREFIX = 'crm369_mobile_';

    private const int USAGE_UPDATE_INTERVAL_MINUTES = 5;

    /**
     * Permissions intentionally exclude administration, integrations, webhooks,
     * user management, and other server-wide settings.
     *
     * @var array<int, string>
     */
    private const array PERMISSIONS = [
        ApiAccessToken::PERMISSION_PROFILE_READ,
        ApiAccessToken::PERMISSION_PROFILE_WRITE,
        ApiAccessToken::PERMISSION_NOTIFICATIONS_READ,
        ApiAccessToken::PERMISSION_NOTIFICATIONS_WRITE,
        ApiAccessToken::PERMISSION_CHAT_READ,
        ApiAccessToken::PERMISSION_CHAT_WRITE,
        ApiAccessToken::PERMISSION_COMPANY_STRUCTURE_READ,
        ApiAccessToken::PERMISSION_CONTACTS_READ,
        ApiAccessToken::PERMISSION_CONTACTS_WRITE,
        ApiAccessToken::PERMISSION_EDO_READ,
        ApiAccessToken::PERMISSION_KNOWLEDGE_READ,
        ApiAccessToken::PERMISSION_PROJECTS_READ,
        ApiAccessToken::PERMISSION_PROJECTS_WRITE,
        ApiAccessToken::PERMISSION_TASKS_READ,
        ApiAccessToken::PERMISSION_TASKS_WRITE,
        ApiAccessToken::PERMISSION_CALENDAR_READ,
        ApiAccessToken::PERMISSION_MENU_READ,
        ApiAccessToken::PERMISSION_WAREHOUSES_READ,
        ApiAccessToken::PERMISSION_EQUIPMENT_READ,
        ApiAccessToken::PERMISSION_DIRECTORIES_READ,
        ApiAccessToken::PERMISSION_TSD_READ,
        ApiAccessToken::PERMISSION_TSD_WRITE,
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function looksLikeToken(?string $token): bool
    {
        return is_string($token) && str_starts_with(trim($token), self::PLAIN_TEXT_PREFIX);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /**
     * @return array{mobile_access_token: self, plain_text_token: string}
     */
    public static function issueToUser(User $user, string $deviceId): array
    {
        $user->mobileAccessTokens()->where('device_id', $deviceId)->delete();

        for ($attempt = 0; $attempt < self::TOKEN_ISSUE_ATTEMPTS; $attempt++) {
            $plainTextToken = self::PLAIN_TEXT_PREFIX.Str::random(80);
            $tokenHash = self::hashToken($plainTextToken);

            try {
                return [
                    'mobile_access_token' => self::query()->create([
                        'user_id' => $user->id,
                        'device_id' => $deviceId,
                        'token_prefix' => Str::substr($tokenHash, 0, self::TOKEN_PREFIX_LENGTH),
                        'token_hash' => $tokenHash,
                        'expires_at' => self::freshExpiration(),
                    ]),
                    'plain_text_token' => $plainTextToken,
                ];
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::TOKEN_ISSUE_ATTEMPTS - 1) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Mobile access token could not be issued.');
    }

    public static function resolve(?string $plainTextToken): ?self
    {
        if (! self::looksLikeToken($plainTextToken)) {
            return null;
        }

        $tokenHash = self::hashToken((string) $plainTextToken);

        return self::query()
            ->with('user.group')
            ->where('token_prefix', Str::substr($tokenHash, 0, self::TOKEN_PREFIX_LENGTH))
            ->get()
            ->first(fn (self $token): bool => hash_equals($token->token_hash, $tokenHash));
    }

    public function isAvailable(): bool
    {
        return ! $this->expires_at?->isPast();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, self::PERMISSIONS, true);
    }

    public function touchUsage(Request $request): void
    {
        if ($this->last_used_at?->isAfter(now()->subMinutes(self::USAGE_UPDATE_INTERVAL_MINUTES))) {
            return;
        }

        $this->forceFill([
            'last_used_at' => now(),
            'last_used_ip_address' => $request->ip(),
            'last_used_user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ])->save();
    }

    private static function freshExpiration(): ?CarbonInterface
    {
        $sessionDays = (int) config('services.mobile.session_days', 365);

        return $sessionDays > 0 ? now()->addDays($sessionDays) : null;
    }
}
