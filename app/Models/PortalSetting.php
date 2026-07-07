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
 * @property array<int, string>|null $disabled_modules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_name', 'logo_path', 'default_language', 'disabled_modules'])]
class PortalSetting extends Model
{
    public const string DEFAULT_COMPANY_NAME = 'CRM369';

    /**
     * @var array<int, string>
     */
    public const array SUPPORTED_LANGUAGES = ['ru', 'en'];

    /** @use HasFactory<PortalSettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disabled_modules' => 'array',
        ];
    }

    /**
     * @return array<string, array{title_key: string, description_key: string}>
     */
    public static function availableModules(): array
    {
        return [
            'news' => [
                'title_key' => 'ui.news.title',
                'description_key' => 'ui.news.description',
            ],
            'projects' => [
                'title_key' => 'ui.projects.title',
                'description_key' => 'ui.projects.description',
            ],
            'chats' => [
                'title_key' => 'ui.chat.title',
                'description_key' => 'ui.chat.description',
            ],
            'company-structure' => [
                'title_key' => 'ui.company_structure.title',
                'description_key' => 'ui.company_structure.description',
            ],
            'knowledge-bases' => [
                'title_key' => 'ui.knowledge.title',
                'description_key' => 'ui.knowledge.description',
            ],
            'funnels' => [
                'title_key' => 'ui.funnels.title',
                'description_key' => 'ui.funnels.description',
            ],
            'forms' => [
                'title_key' => 'ui.forms.title',
                'description_key' => 'ui.forms.description',
            ],
            'contacts' => [
                'title_key' => 'ui.contacts.title',
                'description_key' => 'ui.contacts.description',
            ],
            'edo' => [
                'title_key' => 'ui.edo.title',
                'description_key' => 'ui.edo.description',
            ],
            'files' => [
                'title_key' => 'ui.files.title',
                'description_key' => 'ui.files.description',
            ],
            'production' => [
                'title_key' => 'ui.production.title',
                'description_key' => 'ui.production.description',
            ],
            'warehouses' => [
                'title_key' => 'ui.warehouses.title',
                'description_key' => 'ui.warehouses.description',
            ],
            'tsd' => [
                'title_key' => 'ui.tsd.title',
                'description_key' => 'ui.tsd.description',
            ],
            'equipment' => [
                'title_key' => 'ui.equipment.title',
                'description_key' => 'ui.equipment.description',
            ],
            'api' => [
                'title_key' => 'ui.api.title',
                'description_key' => 'ui.api.description',
            ],
            'webhooks' => [
                'title_key' => 'ui.webhooks.title',
                'description_key' => 'ui.webhooks.description',
            ],
            'integrations' => [
                'title_key' => 'ui.integrations.title',
                'description_key' => 'ui.integrations.description',
            ],
            'business-processes' => [
                'title_key' => 'ui.business_processes.title',
                'description_key' => 'ui.business_processes.description',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableModuleKeys(): array
    {
        return array_keys(self::availableModules());
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeDisabledModules(mixed $modules): array
    {
        $requestedModules = collect(is_array($modules) ? $modules : [])
            ->filter(fn (mixed $module): bool => is_string($module) && $module !== '')
            ->unique()
            ->values()
            ->all();

        return collect(self::availableModuleKeys())
            ->filter(fn (string $module): bool => in_array($module, $requestedModules, true))
            ->values()
            ->all();
    }

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

    /**
     * @return array<int, string>
     */
    public function disabledModules(): array
    {
        return self::normalizeDisabledModules($this->disabled_modules);
    }

    /**
     * @return array<int, string>
     */
    public function enabledModules(): array
    {
        return collect(self::availableModuleKeys())
            ->reject(fn (string $module): bool => in_array($module, $this->disabledModules(), true))
            ->values()
            ->all();
    }

    public function isModuleEnabled(string $module): bool
    {
        return in_array($module, self::availableModuleKeys(), true)
            && ! in_array($module, $this->disabledModules(), true);
    }
}
