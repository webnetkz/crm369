<?php

namespace App\Models;

use Database\Factories\PortalSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_name
 * @property string|null $logo_path
 * @property string $default_language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_name', 'logo_path', 'default_language'])]
class PortalSetting extends Model
{
    public const string DEFAULT_COMPANY_NAME = 'CRM369';

    /**
     * @var array<int, string>
     */
    public const array SUPPORTED_LANGUAGES = ['ru', 'en'];

    /** @use HasFactory<PortalSettingFactory> */
    use HasFactory;

    public static function current(): self
    {
        return self::query()->firstOrCreate([
            'id' => 1,
        ], [
            'company_name' => self::DEFAULT_COMPANY_NAME,
            'default_language' => config('app.locale', 'ru'),
        ]);
    }

    public function companyName(): string
    {
        return $this->company_name ?: self::DEFAULT_COMPANY_NAME;
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return '/storage/'.ltrim($this->logo_path, '/');
    }

    public function defaultLanguage(): string
    {
        if (in_array($this->default_language, self::SUPPORTED_LANGUAGES, true)) {
            return $this->default_language;
        }

        $fallback = (string) config('app.locale', 'ru');

        if (in_array($fallback, self::SUPPORTED_LANGUAGES, true)) {
            return $fallback;
        }

        return self::SUPPORTED_LANGUAGES[0];
    }
}
