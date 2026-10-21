<?php

namespace App\Support;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class UserAbilityData
{
    /** @var list<string>|null */
    private ?array $tableNames = null;

    private ?PortalSetting $portalSettings = null;

    /**
     * Build the module and administration abilities of a user.
     *
     * The keys are shared by the web application (Inertia "auth" props) and
     * the native mobile application bootstrap payload.
     *
     * @return array<string, bool>
     */
    public function build(?User $user): array
    {
        return [
            'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            'canViewUsers' => $user?->canViewUsers() ?? false,
            'canImpersonateUsers' => $user?->canImpersonateUsers() ?? false,
            'canAccessCompanyStructure' => $this->moduleAbility('company-structure', fn (User $user): bool => $user->canAccessCompanyStructure(), $user),
            'canAccessNews' => $this->moduleAbility('news', fn (User $user): bool => $user->canAccessNews(), $user),
            'canAccessProjects' => $this->moduleAbility('projects', fn (User $user): bool => $user->canAccessProjects(), $user),
            'canAccessChats' => $this->moduleAbility('chats', fn (User $user): bool => $user->canAccessChats(), $user),
            'canAccessConferences' => $this->moduleAbility('conferences', fn (User $user): bool => $user->canAccessConferences(), $user),
            'canAccessCalendar' => $this->moduleAbility('calendar', fn (User $user): bool => $user->canAccessCalendar(), $user),
            'canAccessKnowledgeBases' => $this->moduleAbility('knowledge-bases', fn (User $user): bool => $user->canAccessKnowledgeBases(), $user),
            'canAccessForms' => $this->moduleAbility('forms', fn (User $user): bool => $user->canAccessForms(), $user),
            'canAccessEdo' => $this->moduleAbility('edo', fn (User $user): bool => $user->canAccessEdo(), $user),
            'canAccessFiles' => $this->moduleAbility('files', fn (User $user): bool => $user->canAccessFiles(), $user),
            'canAccessProduction' => $this->moduleAbility('production', fn (User $user): bool => $user->canAccessProduction(), $user),
            'canAccessProcurement' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canAccessProcurement(), $user),
            'canManageProcurement' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canManageProcurement(), $user),
            'canApproveProcurementBudget' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canApproveProcurementBudget(), $user),
            'canManageProcurementOrders' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canManageProcurementOrders(), $user),
            'canReceiveProcurementOrders' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canReceiveProcurementOrders(), $user),
            'canReturnProcurementGoods' => $this->moduleAbility('procurement', fn (User $user): bool => $user->canReturnProcurementGoods(), $user),
            'canAccessWarehouses' => $this->moduleAbility('warehouses', fn (User $user): bool => $user->canAccessWarehouses(), $user),
            'canAccessEquipment' => $this->moduleAbility('equipment', fn (User $user): bool => $user->canAccessEquipment(), $user),
            'canAccessTsd' => $this->moduleAbility('tsd', fn (User $user): bool => $user->canAccessTsd(), $user),
            'canAccessDirectories' => $this->moduleAbility('directories', fn (User $user): bool => $user->canAccessDirectories(), $user),
            'canManageDirectories' => $this->moduleAbility('directories', fn (User $user): bool => $user->canManageDirectories(), $user),
            'canManageApiTokens' => $this->moduleAbility('api', fn (User $user): bool => $user->canManageApiTokens(), $user),
            'canManageWebhooks' => $this->moduleAbility('webhooks', fn (User $user): bool => $user->canManageWebhooks(), $user),
            'canManageMessengerIntegrations' => $this->moduleAbility('integrations', fn (User $user): bool => $user->canManageMessengerIntegrations(), $user),
            'canManageBusinessProcesses' => $this->moduleAbility('business-processes', fn (User $user): bool => $user->canManageBusinessProcesses(), $user),
            'canAccessContacts' => $this->moduleAbility('contacts', fn (User $user): bool => $user->canAccessContacts(), $user),
            'canAccessPersonContacts' => $this->moduleAbility('contacts', fn (User $user): bool => $user->canAccessPersonContacts(), $user),
            'canAccessCompanyContacts' => $this->moduleAbility('contacts', fn (User $user): bool => $user->canAccessCompanyContacts(), $user),
            'canManageKnowledgeBases' => $this->moduleAbility('knowledge-bases', fn (User $user): bool => $user->canManageKnowledgeBases(), $user),
            'canManageNews' => $this->moduleAbility('news', fn (User $user): bool => $user->canManageNews(), $user),
            'canAccessFunnels' => $this->crmFunnelsEnabled()
                ? $this->moduleAbility('funnels', fn (User $user): bool => $user->canAccessFunnels(), $user)
                : false,
            'canManageFunnels' => $this->crmFunnelsEnabled()
                ? $this->moduleAbility('funnels', fn (User $user): bool => $user->canManageFunnels(), $user)
                : false,
        ];
    }

    /**
     * @param  callable(User): bool  $ability
     */
    private function moduleAbility(string $module, callable $ability, ?User $user): bool
    {
        if (! $user instanceof User || ! $this->portalSettings()->isModuleEnabled($module)) {
            return false;
        }

        return $ability($user);
    }

    private function crmFunnelsEnabled(): bool
    {
        return $this->portalSettings()->isModuleEnabled('funnels')
            && $this->hasTable('crm_funnels')
            && $this->hasTable('crm_funnel_user_group');
    }

    private function portalSettings(): PortalSetting
    {
        return $this->portalSettings ??= PortalSetting::current();
    }

    private function hasTable(string $table): bool
    {
        $this->tableNames ??= array_values(Schema::getTableListing(null, false));

        return in_array($table, $this->tableNames, true);
    }
}
