<?php

namespace App\Models;

use Database\Factories\UserGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const string PERMISSION_MODULE_ADMINISTRATION = 'administration';

    private const string CONFIGURED_MODULE_MARKER_PREFIX = 'configured_permission_module:';

    public const string PERMISSION_VIEW_USERS = 'view_users';

    public const string PERMISSION_MANAGE_USER_ACTIVATION = 'manage_user_activation';

    public const string PERMISSION_MANAGE_USER_ACCOUNTS = 'manage_user_accounts';

    public const string PERMISSION_IMPERSONATE_USERS = 'impersonate_users';

    public const string PERMISSION_ACCESS_PERSON_CONTACTS = 'access_person_contacts';

    public const string PERMISSION_ACCESS_COMPANY_CONTACTS = 'access_company_contacts';

    public const string PERMISSION_ACCESS_COMPANY_STRUCTURE = 'access_company_structure';

    public const string PERMISSION_ACCESS_NEWS = 'access_news';

    public const string PERMISSION_MANAGE_NEWS = 'manage_news';

    public const string PERMISSION_ACCESS_PROJECTS = 'access_projects';

    public const string PERMISSION_ACCESS_CHATS = 'access_chats';

    public const string PERMISSION_ACCESS_CONFERENCES = 'access_conferences';

    public const string PERMISSION_ACCESS_KNOWLEDGE_BASES = 'access_knowledge_bases';

    public const string PERMISSION_MANAGE_KNOWLEDGE_BASES = 'manage_knowledge_bases';

    public const string PERMISSION_MANAGE_FUNNELS = 'manage_funnels';

    public const string PERMISSION_ACCESS_FORMS = 'access_forms';

    public const string PERMISSION_ACCESS_EDO = 'access_edo';

    public const string PERMISSION_ACCESS_FILES = 'access_files';

    public const string PERMISSION_ACCESS_PRODUCTION = 'access_production';

    public const string PERMISSION_ACCESS_WAREHOUSES = 'access_warehouses';

    public const string PERMISSION_ACCESS_TSD = 'access_tsd';

    public const string PERMISSION_ACCESS_EQUIPMENT = 'access_equipment';

    public const string PERMISSION_ACCESS_DIRECTORIES = 'access_directories';

    public const string PERMISSION_MANAGE_DIRECTORIES = 'manage_directories';

    public const string PERMISSION_MANAGE_API_TOKENS = 'manage_api_tokens';

    public const string PERMISSION_MANAGE_WEBHOOKS = 'manage_webhooks';

    public const string PERMISSION_MANAGE_MESSENGER_INTEGRATIONS = 'manage_messenger_integrations';

    public const string PERMISSION_MANAGE_BUSINESS_PROCESSES = 'manage_business_processes';

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
     * @return array<string, array{label_key: string, description_key: string, module_key: string}>
     */
    public static function permissionDefinitions(): array
    {
        return [
            self::PERMISSION_VIEW_USERS => [
                'label_key' => 'ui.admin.permission_view_users',
                'description_key' => 'ui.admin.permission_view_users_description',
                'module_key' => self::PERMISSION_MODULE_ADMINISTRATION,
            ],
            self::PERMISSION_MANAGE_USER_ACTIVATION => [
                'label_key' => 'ui.admin.permission_manage_user_activation',
                'description_key' => 'ui.admin.permission_manage_user_activation_description',
                'module_key' => self::PERMISSION_MODULE_ADMINISTRATION,
            ],
            self::PERMISSION_MANAGE_USER_ACCOUNTS => [
                'label_key' => 'ui.admin.permission_manage_user_accounts',
                'description_key' => 'ui.admin.permission_manage_user_accounts_description',
                'module_key' => self::PERMISSION_MODULE_ADMINISTRATION,
            ],
            self::PERMISSION_IMPERSONATE_USERS => [
                'label_key' => 'ui.admin.permission_impersonate_users',
                'description_key' => 'ui.admin.permission_impersonate_users_description',
                'module_key' => self::PERMISSION_MODULE_ADMINISTRATION,
            ],
            self::PERMISSION_ACCESS_PERSON_CONTACTS => [
                'label_key' => 'ui.admin.permission_access_person_contacts',
                'description_key' => 'ui.admin.permission_access_person_contacts_description',
                'module_key' => 'contacts',
            ],
            self::PERMISSION_ACCESS_COMPANY_CONTACTS => [
                'label_key' => 'ui.admin.permission_access_company_contacts',
                'description_key' => 'ui.admin.permission_access_company_contacts_description',
                'module_key' => 'contacts',
            ],
            self::PERMISSION_ACCESS_COMPANY_STRUCTURE => [
                'label_key' => 'ui.admin.permission_access_company_structure',
                'description_key' => 'ui.admin.permission_access_company_structure_description',
                'module_key' => 'company-structure',
            ],
            self::PERMISSION_ACCESS_NEWS => [
                'label_key' => 'ui.admin.permission_access_news',
                'description_key' => 'ui.admin.permission_access_news_description',
                'module_key' => 'news',
            ],
            self::PERMISSION_MANAGE_NEWS => [
                'label_key' => 'ui.admin.permission_manage_news',
                'description_key' => 'ui.admin.permission_manage_news_description',
                'module_key' => 'news',
            ],
            self::PERMISSION_ACCESS_PROJECTS => [
                'label_key' => 'ui.admin.permission_access_projects',
                'description_key' => 'ui.admin.permission_access_projects_description',
                'module_key' => 'projects',
            ],
            self::PERMISSION_ACCESS_CHATS => [
                'label_key' => 'ui.admin.permission_access_chats',
                'description_key' => 'ui.admin.permission_access_chats_description',
                'module_key' => 'chats',
            ],
            self::PERMISSION_ACCESS_CONFERENCES => [
                'label_key' => 'ui.admin.permission_access_conferences',
                'description_key' => 'ui.admin.permission_access_conferences_description',
                'module_key' => 'conferences',
            ],
            self::PERMISSION_ACCESS_KNOWLEDGE_BASES => [
                'label_key' => 'ui.admin.permission_access_knowledge_bases',
                'description_key' => 'ui.admin.permission_access_knowledge_bases_description',
                'module_key' => 'knowledge-bases',
            ],
            self::PERMISSION_MANAGE_KNOWLEDGE_BASES => [
                'label_key' => 'ui.admin.permission_manage_knowledge_bases',
                'description_key' => 'ui.admin.permission_manage_knowledge_bases_description',
                'module_key' => 'knowledge-bases',
            ],
            self::PERMISSION_MANAGE_FUNNELS => [
                'label_key' => 'ui.admin.permission_manage_funnels',
                'description_key' => 'ui.admin.permission_manage_funnels_description',
                'module_key' => 'funnels',
            ],
            self::PERMISSION_ACCESS_FORMS => [
                'label_key' => 'ui.admin.permission_access_forms',
                'description_key' => 'ui.admin.permission_access_forms_description',
                'module_key' => 'forms',
            ],
            self::PERMISSION_ACCESS_EDO => [
                'label_key' => 'ui.admin.permission_access_edo',
                'description_key' => 'ui.admin.permission_access_edo_description',
                'module_key' => 'edo',
            ],
            self::PERMISSION_ACCESS_FILES => [
                'label_key' => 'ui.admin.permission_access_files',
                'description_key' => 'ui.admin.permission_access_files_description',
                'module_key' => 'files',
            ],
            self::PERMISSION_ACCESS_PRODUCTION => [
                'label_key' => 'ui.admin.permission_access_production',
                'description_key' => 'ui.admin.permission_access_production_description',
                'module_key' => 'production',
            ],
            self::PERMISSION_ACCESS_WAREHOUSES => [
                'label_key' => 'ui.admin.permission_access_warehouses',
                'description_key' => 'ui.admin.permission_access_warehouses_description',
                'module_key' => 'warehouses',
            ],
            self::PERMISSION_ACCESS_TSD => [
                'label_key' => 'ui.admin.permission_access_tsd',
                'description_key' => 'ui.admin.permission_access_tsd_description',
                'module_key' => 'tsd',
            ],
            self::PERMISSION_ACCESS_EQUIPMENT => [
                'label_key' => 'ui.admin.permission_access_equipment',
                'description_key' => 'ui.admin.permission_access_equipment_description',
                'module_key' => 'equipment',
            ],
            self::PERMISSION_ACCESS_DIRECTORIES => [
                'label_key' => 'ui.admin.permission_access_directories',
                'description_key' => 'ui.admin.permission_access_directories_description',
                'module_key' => 'directories',
            ],
            self::PERMISSION_MANAGE_DIRECTORIES => [
                'label_key' => 'ui.admin.permission_manage_directories',
                'description_key' => 'ui.admin.permission_manage_directories_description',
                'module_key' => 'directories',
            ],
            self::PERMISSION_MANAGE_API_TOKENS => [
                'label_key' => 'ui.admin.permission_manage_api_tokens',
                'description_key' => 'ui.admin.permission_manage_api_tokens_description',
                'module_key' => 'api',
            ],
            self::PERMISSION_MANAGE_WEBHOOKS => [
                'label_key' => 'ui.admin.permission_manage_webhooks',
                'description_key' => 'ui.admin.permission_manage_webhooks_description',
                'module_key' => 'webhooks',
            ],
            self::PERMISSION_MANAGE_MESSENGER_INTEGRATIONS => [
                'label_key' => 'ui.admin.permission_manage_messenger_integrations',
                'description_key' => 'ui.admin.permission_manage_messenger_integrations_description',
                'module_key' => 'integrations',
            ],
            self::PERMISSION_MANAGE_BUSINESS_PROCESSES => [
                'label_key' => 'ui.admin.permission_manage_business_processes',
                'description_key' => 'ui.admin.permission_manage_business_processes_description',
                'module_key' => 'business-processes',
            ],
        ];
    }

    /**
     * @return array<string, array{title_key: string, description_key: string}>
     */
    public static function permissionModuleDefinitions(): array
    {
        $portalModules = PortalSetting::availableModules();

        return [
            self::PERMISSION_MODULE_ADMINISTRATION => [
                'title_key' => 'ui.admin.permission_module_administration',
                'description_key' => 'ui.admin.permission_module_administration_description',
            ],
            'contacts' => $portalModules['contacts'],
            'company-structure' => $portalModules['company-structure'],
            'news' => $portalModules['news'],
            'projects' => $portalModules['projects'],
            'chats' => $portalModules['chats'],
            'conferences' => $portalModules['conferences'],
            'knowledge-bases' => $portalModules['knowledge-bases'],
            'funnels' => $portalModules['funnels'],
            'forms' => $portalModules['forms'],
            'edo' => $portalModules['edo'],
            'files' => $portalModules['files'],
            'production' => $portalModules['production'],
            'warehouses' => $portalModules['warehouses'],
            'tsd' => $portalModules['tsd'],
            'equipment' => $portalModules['equipment'],
            'directories' => $portalModules['directories'],
            'api' => $portalModules['api'],
            'webhooks' => $portalModules['webhooks'],
            'integrations' => $portalModules['integrations'],
            'business-processes' => $portalModules['business-processes'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function configurableAccessModules(): array
    {
        return [
            'company-structure',
            'news',
            'projects',
            'chats',
            'conferences',
            'knowledge-bases',
            'forms',
            'edo',
            'files',
            'production',
            'warehouses',
            'tsd',
            'equipment',
            'directories',
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

    public static function permissionModuleMarker(string $module): string
    {
        return self::CONFIGURED_MODULE_MARKER_PREFIX.$module;
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $configuredModules
     * @return array<int, string>
     */
    public static function normalizePermissionsWithConfiguredModules(array $permissions, array $configuredModules): array
    {
        $normalizedPermissions = collect($permissions)
            ->filter(fn (mixed $permission): bool => is_string($permission))
            ->map(fn (string $permission): string => trim($permission))
            ->filter(fn (string $permission): bool => in_array($permission, self::availablePermissions(), true))
            ->values();

        $markers = collect($configuredModules)
            ->filter(fn (mixed $module): bool => is_string($module))
            ->map(fn (string $module): string => trim($module))
            ->filter(fn (string $module): bool => in_array($module, self::configurableAccessModules(), true))
            ->map(fn (string $module): string => self::permissionModuleMarker($module))
            ->values();

        return $normalizedPermissions
            ->merge($markers)
            ->unique()
            ->values()
            ->all();
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

    /**
     * @return array<int, string>
     */
    public function configuredPermissionModules(): array
    {
        return collect($this->permissions ?? [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => str_starts_with($value, self::CONFIGURED_MODULE_MARKER_PREFIX))
            ->map(fn (string $value): string => substr($value, strlen(self::CONFIGURED_MODULE_MARKER_PREFIX)))
            ->filter(fn (string $module): bool => in_array($module, self::configurableAccessModules(), true))
            ->unique()
            ->values()
            ->all();
    }

    public function isPermissionModuleConfigured(string $module): bool
    {
        return in_array($module, $this->configuredPermissionModules(), true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->resolvedPermissions(), true);
    }
}
