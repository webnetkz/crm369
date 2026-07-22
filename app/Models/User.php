<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $last_name
 * @property string|null $middle_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $position
 * @property int|null $manager_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $appearance
 * @property string $language
 * @property bool $has_selected_language
 * @property string|null $background_color
 * @property string|null $background_image_path
 * @property int $background_blur
 * @property string|null $background_image
 * @property array<int, string>|null $hidden_menu_item_keys
 * @property array<int, int>|null $hidden_menu_item_ids
 * @property array<int, string>|null $menu_item_order
 * @property array<string, mixed>|null $dashboard_configuration
 * @property string $task_display_mode
 * @property array<int, string>|null $visible_user_table_columns
 * @property string|null $avatar_path
 * @property int $avatar_position_x
 * @property int $avatar_position_y
 * @property float $avatar_scale
 * @property int|null $user_group_id
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'last_name', 'middle_name', 'email', 'phone', 'position', 'manager_id', 'password', 'appearance', 'language', 'has_selected_language', 'background_color', 'background_image_path', 'background_blur', 'hidden_menu_item_keys', 'hidden_menu_item_ids', 'menu_item_order', 'dashboard_configuration', 'task_display_mode', 'visible_user_table_columns', 'avatar_path', 'avatar_position_x', 'avatar_position_y', 'avatar_scale', 'user_group_id', 'is_active', 'deactivated_at'])]
#[Hidden(['password', 'avatar_path', 'background_image_path', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const array VISIBLE_USER_TABLE_OPTIONAL_COLUMNS = [
        'position',
        'manager',
        'status',
        'email_verified',
        'group',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = ['avatar', 'background_image'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'has_selected_language' => 'boolean',
            'background_blur' => 'integer',
            'hidden_menu_item_keys' => 'array',
            'hidden_menu_item_ids' => 'array',
            'menu_item_order' => 'array',
            'dashboard_configuration' => 'array',
            'visible_user_table_columns' => 'array',
            'avatar_position_x' => 'integer',
            'avatar_position_y' => 'integer',
            'avatar_scale' => 'float',
            'manager_id' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasSelectedLanguage(): bool
    {
        return $this->has_selected_language
            && is_string($this->language)
            && in_array($this->language, PortalSetting::SUPPORTED_LANGUAGES, true);
    }

    public function preferredLanguage(): ?string
    {
        return $this->hasSelectedLanguage() ? $this->language : null;
    }

    /**
     * Get the two factor authentication QR code URL.
     */
    public function twoFactorQrCodeUrl()
    {
        $issuer = trim((string) config('app.two_factor_issuer', config('app.name', 'CRM369')));

        if ($issuer === '') {
            $issuer = 'CRM369';
        }

        return app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            $issuer,
            $this->{Fortify::username()},
            Fortify::currentEncrypter()->decrypt($this->two_factor_secret)
        );
    }

    public function resolvedLanguage(): string
    {
        return $this->preferredLanguage() ?? PortalSetting::current()->defaultLanguage();
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function backgroundImage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->background_image_path
            ? Storage::disk('public')->url($this->background_image_path)
            : null);
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
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id')
            ->orderBy('name')
            ->orderBy('last_name');
    }

    /**
     * @return HasMany<ApiAccessToken, $this>
     */
    public function apiAccessTokens(): HasMany
    {
        return $this->hasMany(ApiAccessToken::class)
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<UserLoginActivity, $this>
     */
    public function loginActivities(): HasMany
    {
        return $this->hasMany(UserLoginActivity::class)
            ->latest('logged_in_at');
    }

    /**
     * @return HasMany<EquipmentItem, $this>
     */
    public function issuedEquipmentItems(): HasMany
    {
        return $this->hasMany(EquipmentItem::class, 'issued_to_user_id')
            ->ordered();
    }

    public function isSuperAdmin(): bool
    {
        $superAdminEmail = config('admin.super_admin_email');

        if (! is_string($superAdminEmail)) {
            return false;
        }

        $superAdminEmail = trim($superAdminEmail);

        return $superAdminEmail !== ''
            && strcasecmp($this->email, $superAdminEmail) === 0;
    }

    public function canViewUsers(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_VIEW_USERS);
    }

    public function canManageUserActivation(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_USER_ACTIVATION);
    }

    public function canManageUserAccounts(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_USER_ACCOUNTS);
    }

    public function canImpersonateUsers(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_IMPERSONATE_USERS);
    }

    public function canAccessCompanyStructure(): bool
    {
        return $this->canAccessConfiguredModule('company-structure', [
            UserGroup::PERMISSION_ACCESS_COMPANY_STRUCTURE,
        ]);
    }

    public function canAccessNews(): bool
    {
        return $this->canAccessConfiguredModule('news', [
            UserGroup::PERMISSION_ACCESS_NEWS,
            UserGroup::PERMISSION_MANAGE_NEWS,
        ]);
    }

    public function canManageKnowledgeBases(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_KNOWLEDGE_BASES);
    }

    public function canManageNews(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_NEWS);
    }

    public function canAccessProjects(): bool
    {
        return $this->canAccessConfiguredModule('projects', [
            UserGroup::PERMISSION_ACCESS_PROJECTS,
        ]);
    }

    public function canAccessChats(): bool
    {
        return $this->canAccessConfiguredModule('chats', [
            UserGroup::PERMISSION_ACCESS_CHATS,
        ]);
    }

    public function canAccessConferences(): bool
    {
        return $this->canAccessConfiguredModule('conferences', [
            UserGroup::PERMISSION_ACCESS_CONFERENCES,
        ]);
    }

    public function canAccessCalendar(): bool
    {
        return $this->canAccessConfiguredModule('calendar', [
            UserGroup::PERMISSION_ACCESS_CALENDAR,
        ]);
    }

    public function canAccessKnowledgeBases(): bool
    {
        return $this->canAccessConfiguredModule('knowledge-bases', [
            UserGroup::PERMISSION_ACCESS_KNOWLEDGE_BASES,
            UserGroup::PERMISSION_MANAGE_KNOWLEDGE_BASES,
        ]);
    }

    public function canManageApiTokens(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_API_TOKENS)
            || $this->canManageUserAccounts();
    }

    public function canManageMessengerIntegrations(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_MESSENGER_INTEGRATIONS);
    }

    public function canManageWebhooks(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_WEBHOOKS)
            || $this->isGroupAdministrator();
    }

    public function canAccessForms(): bool
    {
        return $this->canAccessConfiguredModule('forms', [
            UserGroup::PERMISSION_ACCESS_FORMS,
        ]);
    }

    public function canAccessEdo(): bool
    {
        return $this->canAccessConfiguredModule('edo', [
            UserGroup::PERMISSION_ACCESS_EDO,
        ]);
    }

    public function canAccessFiles(): bool
    {
        return $this->canAccessConfiguredModule('files', [
            UserGroup::PERMISSION_ACCESS_FILES,
        ]);
    }

    public function canAccessProduction(): bool
    {
        return $this->canAccessConfiguredModule('production', [
            UserGroup::PERMISSION_ACCESS_PRODUCTION,
        ]);
    }

    public function canAccessProcurement(): bool
    {
        return $this->canAccessConfiguredModule('procurement', [
            UserGroup::PERMISSION_ACCESS_PROCUREMENT,
            UserGroup::PERMISSION_MANAGE_PROCUREMENT,
            UserGroup::PERMISSION_APPROVE_PROCUREMENT_BUDGET,
            UserGroup::PERMISSION_MANAGE_PROCUREMENT_ORDERS,
            UserGroup::PERMISSION_RECEIVE_PROCUREMENT_ORDERS,
            UserGroup::PERMISSION_RETURN_PROCUREMENT_GOODS,
        ]);
    }

    public function canManageProcurement(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_PROCUREMENT);
    }

    public function canApproveProcurementBudget(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_APPROVE_PROCUREMENT_BUDGET);
    }

    public function canManageProcurementOrders(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_PROCUREMENT_ORDERS);
    }

    public function canReceiveProcurementOrders(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_RECEIVE_PROCUREMENT_ORDERS);
    }

    public function canReturnProcurementGoods(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_RETURN_PROCUREMENT_GOODS);
    }

    public function canAccessWarehouses(): bool
    {
        return $this->canAccessConfiguredModule('warehouses', [
            UserGroup::PERMISSION_ACCESS_WAREHOUSES,
        ]);
    }

    public function canAccessTsd(): bool
    {
        return $this->canAccessConfiguredModule('tsd', [
            UserGroup::PERMISSION_ACCESS_TSD,
        ]);
    }

    public function canAccessEquipment(): bool
    {
        return $this->canAccessConfiguredModule('equipment', [
            UserGroup::PERMISSION_ACCESS_EQUIPMENT,
        ]);
    }

    public function canAccessDirectories(): bool
    {
        return $this->canAccessConfiguredModule('directories', [
            UserGroup::PERMISSION_ACCESS_DIRECTORIES,
            UserGroup::PERMISSION_MANAGE_DIRECTORIES,
        ], false);
    }

    public function canManageDirectories(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_DIRECTORIES);
    }

    public function canManageBusinessProcesses(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_BUSINESS_PROCESSES);
    }

    public function canAccessPersonContacts(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS);
    }

    public function canAccessCompanyContacts(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_ACCESS_COMPANY_CONTACTS);
    }

    public function canAccessContacts(): bool
    {
        return $this->canAccessPersonContacts() || $this->canAccessCompanyContacts();
    }

    public function canAccessContactType(?string $type): bool
    {
        return match ($type) {
            Contact::TYPE_PERSON => $this->canAccessPersonContacts(),
            Contact::TYPE_COMPANY => $this->canAccessCompanyContacts(),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public function accessibleContactTypes(): array
    {
        return collect(Contact::availableTypes())
            ->filter(fn (string $type): bool => $this->canAccessContactType($type))
            ->values()
            ->all();
    }

    public function canAccessContact(Contact $contact): bool
    {
        return $this->canAccessContactType($contact->type);
    }

    public function canAccessFunnels(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->user_group_id) {
            return false;
        }

        return CrmFunnel::query()
            ->whereHas('groups', fn ($query) => $query->whereKey($this->user_group_id))
            ->exists();
    }

    public function canManageFunnels(): bool
    {
        return $this->isSuperAdmin() || $this->hasGroupPermission(UserGroup::PERMISSION_MANAGE_FUNNELS);
    }

    public function canAccessFunnel(CrmFunnel $funnel): bool
    {
        return $funnel->canBeAccessedBy($this);
    }

    public function canManageFunnel(CrmFunnel $funnel): bool
    {
        return $funnel->canBeManagedBy($this);
    }

    public function canManageDeal(CrmDeal $deal): bool
    {
        $funnel = $deal->relationLoaded('funnel') ? $deal->funnel : $deal->funnel()->first();

        return $funnel ? $this->canAccessFunnel($funnel) : false;
    }

    public function canManageProject(Project $project): bool
    {
        return $this->isSuperAdmin() || $project->owner_user_id === $this->id;
    }

    public function canWorkOnProject(Project $project): bool
    {
        if ($this->isSuperAdmin() || $project->owner_user_id === $this->id) {
            return true;
        }

        if ($project->relationLoaded('members')) {
            return $project->members->contains('id', $this->id);
        }

        return $project->members()
            ->whereKey($this->id)
            ->exists();
    }

    public function canViewTask(ProjectTask $task): bool
    {
        if ($task->project_id !== null) {
            $project = $task->relationLoaded('project') ? $task->project : $task->project()->first();

            return $project ? $this->canWorkOnProject($project) : false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($task->creator_user_id === $this->id || $task->assignee_user_id === $this->id) {
            return true;
        }

        if ($task->relationLoaded('coAssignees')) {
            return $task->coAssignees->contains('id', $this->id);
        }

        return $task->coAssignees()
            ->whereKey($this->id)
            ->exists();
    }

    public function canManageTask(ProjectTask $task): bool
    {
        return $this->canViewTask($task);
    }

    public function canBeImpersonatedBy(?User $impersonator): bool
    {
        if (! $this->is_active || ! $impersonator || $this->is($impersonator) || $this->isSuperAdmin()) {
            return false;
        }

        if ($this->isGroupAdministrator()) {
            return $impersonator->isSuperAdmin();
        }

        return $impersonator->canImpersonateUsers();
    }

    public function canImpersonate(User $user): bool
    {
        return $user->canBeImpersonatedBy($this);
    }

    /**
     * @return array<int, string>
     */
    public function hiddenMenuItemKeys(): array
    {
        return collect($this->hidden_menu_item_keys ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function hiddenMenuItemIds(): array
    {
        return collect($this->hidden_menu_item_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function menuItemOrder(): array
    {
        return collect($this->menu_item_order ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->map(fn (string $key): string => trim($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function visibleUserTableColumns(): array
    {
        return collect($this->visible_user_table_columns ?? [])
            ->filter(fn (mixed $key): bool => is_string($key) && trim($key) !== '')
            ->map(fn (string $key): string => trim($key))
            ->filter(fn (string $key): bool => in_array($key, self::VISIBLE_USER_TABLE_OPTIONAL_COLUMNS, true))
            ->unique()
            ->values()
            ->all();
    }

    public function isGroupAdministrator(): bool
    {
        return $this->resolvedGroup()?->name === UserGroup::ADMINISTRATORS_NAME;
    }

    public function hasGroupPermission(string $permission): bool
    {
        return $this->resolvedGroup()?->hasPermission($permission) ?? false;
    }

    private function canAccessConfiguredModule(string $module, array $permissions, bool $legacyAccess = true): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $group = $this->resolvedGroup();

        if (! $group || ! $group->isPermissionModuleConfigured($module)) {
            return $legacyAccess;
        }

        return collect($permissions)
            ->contains(fn (string $permission): bool => $group->hasPermission($permission));
    }

    private function resolvedGroup(): ?UserGroup
    {
        return $this->loadMissing('group')->group;
    }
}
