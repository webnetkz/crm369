<?php

namespace App\Models;

use Database\Factories\PortalFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property int $target_user_id
 * @property string $name
 * @property string|null $description
 * @property string $submission_mode
 * @property string $public_token
 * @property bool $is_active
 * @property array<string, mixed>|null $style_settings
 * @property array<string, mixed>|null $completion_settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['owner_user_id', 'target_user_id', 'name', 'description', 'submission_mode', 'public_token', 'is_active', 'style_settings', 'completion_settings'])]
class PortalForm extends Model
{
    public const string SUBMISSION_MODE_TASK = 'task';

    public const string SUBMISSION_MODE_CHAT = 'chat';

    public const array CONTAINER_WIDTHS = ['sm', 'md', 'lg', 'xl'];

    public const string COMPLETION_ACTION_MESSAGE = 'message';

    public const string COMPLETION_ACTION_REDIRECT = 'redirect';

    public const string COMPLETION_ACTION_CLOSE = 'close';

    public const array DEFAULT_STYLE_SETTINGS = [
        'container_width' => 'lg',
        'background_color' => '#FFFFFF',
        'border_color' => '#D4D7E1',
        'text_color' => '#0F172A',
        'input_background_color' => '#FFFFFF',
        'input_border_color' => '#CBD5E1',
        'button_background_color' => '#111827',
        'button_text_color' => '#FFFFFF',
        'border_radius' => 24,
        'padding' => 32,
    ];

    public const array DEFAULT_COMPLETION_SETTINGS = [
        'action' => self::COMPLETION_ACTION_MESSAGE,
        'success_message' => null,
        'redirect_url' => null,
    ];

    /** @use HasFactory<PortalFormFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_user_id' => 'integer',
            'target_user_id' => 'integer',
            'is_active' => 'boolean',
            'style_settings' => 'array',
            'completion_settings' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableSubmissionModes(): array
    {
        return [
            self::SUBMISSION_MODE_TASK,
            self::SUBMISSION_MODE_CHAT,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableContainerWidths(): array
    {
        return self::CONTAINER_WIDTHS;
    }

    /**
     * @return array{container_width: string, background_color: string, border_color: string, text_color: string, input_background_color: string, input_border_color: string, button_background_color: string, button_text_color: string, border_radius: int, padding: int}
     */
    public static function defaultStyleSettings(): array
    {
        return self::DEFAULT_STYLE_SETTINGS;
    }

    /**
     * @return array{container_width: string, background_color: string, border_color: string, text_color: string, input_background_color: string, input_border_color: string, button_background_color: string, button_text_color: string, border_radius: int, padding: int}
     */
    public static function normalizeStyleSettings(mixed $settings): array
    {
        $defaults = self::defaultStyleSettings();
        $settings = is_array($settings) ? $settings : [];
        $containerWidth = is_string($settings['container_width'] ?? null)
            ? trim($settings['container_width'])
            : null;

        return [
            'container_width' => in_array($containerWidth, self::availableContainerWidths(), true)
                ? $containerWidth
                : $defaults['container_width'],
            'background_color' => self::normalizeHexColor($settings['background_color'] ?? null, $defaults['background_color']),
            'border_color' => self::normalizeHexColor($settings['border_color'] ?? null, $defaults['border_color']),
            'text_color' => self::normalizeHexColor($settings['text_color'] ?? null, $defaults['text_color']),
            'input_background_color' => self::normalizeHexColor($settings['input_background_color'] ?? null, $defaults['input_background_color']),
            'input_border_color' => self::normalizeHexColor($settings['input_border_color'] ?? null, $defaults['input_border_color']),
            'button_background_color' => self::normalizeHexColor($settings['button_background_color'] ?? null, $defaults['button_background_color']),
            'button_text_color' => self::normalizeHexColor($settings['button_text_color'] ?? null, $defaults['button_text_color']),
            'border_radius' => self::normalizeInteger($settings['border_radius'] ?? null, 12, 32, $defaults['border_radius']),
            'padding' => self::normalizeInteger($settings['padding'] ?? null, 20, 48, $defaults['padding']),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableCompletionActions(): array
    {
        return [
            self::COMPLETION_ACTION_MESSAGE,
            self::COMPLETION_ACTION_REDIRECT,
            self::COMPLETION_ACTION_CLOSE,
        ];
    }

    /**
     * @return array{action: string, success_message: string|null, redirect_url: string|null}
     */
    public static function defaultCompletionSettings(): array
    {
        return self::DEFAULT_COMPLETION_SETTINGS;
    }

    /**
     * @return array{action: string, success_message: string|null, redirect_url: string|null}
     */
    public static function normalizeCompletionSettings(mixed $settings): array
    {
        $defaults = self::defaultCompletionSettings();
        $settings = is_array($settings) ? $settings : [];
        $action = is_string($settings['action'] ?? null)
            ? trim($settings['action'])
            : null;

        return [
            'action' => in_array($action, self::availableCompletionActions(), true)
                ? $action
                : $defaults['action'],
            'success_message' => self::normalizeNullableString($settings['success_message'] ?? null, 1000),
            'redirect_url' => self::normalizeNullableString($settings['redirect_url'] ?? null, 2048),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return HasMany<PortalFormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(PortalFormField::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<PortalFormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(PortalFormSubmission::class)
            ->latest('id');
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $normalizedValue = strtoupper(trim($value));

        if (! preg_match('/^#[0-9A-F]{6}$/', $normalizedValue)) {
            return $fallback;
        }

        return $normalizedValue;
    }

    private static function normalizeInteger(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }

    private static function normalizeNullableString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ($normalizedValue === '') {
            return null;
        }

        return mb_substr($normalizedValue, 0, $maxLength);
    }
}
