import { usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    DatabaseZap,
    FileText,
    GitBranchPlus,
    LayoutGrid,
    LockKeyhole,
    Menu,
    MessageSquareShare,
    Palette,
    Shield,
    ShieldAlert,
    UserRound,
    UsersRound,
    Network,
    Webhook,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useLanguage } from '@/composables/useLanguage';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editApi } from '@/routes/settings/api';
import { index as businessProcessesIndex } from '@/routes/settings/business-processes';
import { index as groupsIndex } from '@/routes/settings/groups';
import { edit as editIntegrations } from '@/routes/settings/integrations';
import { edit as editLogs } from '@/routes/settings/logs';
import { edit as editMenu } from '@/routes/settings/menu';
import { edit as editModules } from '@/routes/settings/modules';
import { edit as editOneC } from '@/routes/settings/one-c';
import { edit as editPortal } from '@/routes/settings/portal';
import { index as rightsIndex } from '@/routes/settings/rights';
import { edit as editSystemSecurity } from '@/routes/settings/system-security';
import { index as usersIndex } from '@/routes/settings/users';
import { edit as editWebhooks } from '@/routes/settings/webhooks';
import type { NavItem } from '@/types';

export function useSettingsNavigation(): ComputedRef<NavItem[]> {
    const page = usePage();
    const { t } = useLanguage();

    const hiddenMenuItems = computed(
        () => new Set(page.props.menu.hiddenItems),
    );
    const enabledModules = computed(
        () => new Set(page.props.portal.enabledModules),
    );

    const isVisible = (key: string): boolean => {
        return !hiddenMenuItems.value.has(key);
    };

    const isModuleEnabled = (key: string): boolean => {
        return enabledModules.value.has(key);
    };

    return computed<NavItem[]>(() => {
        return [
            ...(isVisible('settings.profile')
                ? [
                      {
                          key: 'settings.profile',
                          title: t.value.settings.profile,
                          href: editProfile(),
                          icon: UserRound,
                      },
                  ]
                : []),
            ...(isVisible('settings.security')
                ? [
                      {
                          key: 'settings.security',
                          title: t.value.settings.security,
                          href: editSecurity(),
                          icon: Shield,
                      },
                  ]
                : []),
            ...(isVisible('settings.appearance')
                ? [
                      {
                          key: 'settings.appearance',
                          title: t.value.settings.appearance,
                          href: editAppearance(),
                          icon: Palette,
                      },
                  ]
                : []),
            ...(page.props.auth.canViewUsers && isVisible('settings.users')
                ? [
                      {
                          key: 'settings.users',
                          title: t.value.settings.users,
                          href: usersIndex(),
                          icon: UsersRound,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin && isVisible('settings.groups')
                ? [
                      {
                          key: 'settings.groups',
                          title: t.value.settings.groups,
                          href: groupsIndex(),
                          icon: Network,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin &&
            isVisible('settings.system-security')
                ? [
                      {
                          key: 'settings.system-security',
                          title: t.value.settings.system_security,
                          href: editSystemSecurity(),
                          icon: ShieldAlert,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin && isVisible('settings.rights')
                ? [
                      {
                          key: 'settings.rights',
                          title: t.value.settings.rights,
                          href: rightsIndex(),
                          icon: BadgeCheck,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin && isVisible('settings.portal')
                ? [
                      {
                          key: 'settings.portal',
                          title: t.value.settings.portal,
                          href: editPortal(),
                          icon: Building2,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin && isVisible('settings.modules')
                ? [
                      {
                          key: 'settings.modules',
                          title: t.value.settings.modules,
                          href: editModules(),
                          icon: LayoutGrid,
                      },
                  ]
                : []),
            ...(page.props.auth.canManageBusinessProcesses &&
            isVisible('settings.business-processes') &&
            isModuleEnabled('business-processes')
                ? [
                      {
                          key: 'settings.business-processes',
                          title: t.value.settings.business_processes,
                          href: businessProcessesIndex(),
                          icon: GitBranchPlus,
                      },
                  ]
                : []),
            ...(page.props.auth.canManageMessengerIntegrations &&
            isVisible('settings.integrations') &&
            isModuleEnabled('integrations')
                ? [
                      {
                          key: 'settings.integrations',
                          title: t.value.settings.integrations,
                          href: editIntegrations(),
                          icon: MessageSquareShare,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin &&
            isVisible('settings.one-c') &&
            isModuleEnabled('one-c')
                ? [
                      {
                          key: 'settings.one-c',
                          title: t.value.settings.one_c,
                          href: editOneC(),
                          icon: DatabaseZap,
                      },
                  ]
                : []),
            ...(page.props.auth.isSuperAdmin && isVisible('settings.logs')
                ? [
                      {
                          key: 'settings.logs',
                          title: t.value.settings.logs,
                          href: editLogs(),
                          icon: FileText,
                      },
                  ]
                : []),
            ...(page.props.auth.canManageApiTokens &&
            isVisible('settings.api') &&
            isModuleEnabled('api')
                ? [
                      {
                          key: 'settings.api',
                          title: t.value.settings.api,
                          href: editApi(),
                          icon: LockKeyhole,
                      },
                  ]
                : []),
            ...(page.props.auth.canManageWebhooks &&
            isVisible('settings.webhooks') &&
            isModuleEnabled('webhooks')
                ? [
                      {
                          key: 'settings.webhooks',
                          title: t.value.settings.webhooks,
                          href: editWebhooks(),
                          icon: Webhook,
                      },
                  ]
                : []),
            ...(isVisible('settings.menu')
                ? [
                      {
                          key: 'settings.menu',
                          title: t.value.settings.menu,
                          href: editMenu(),
                          icon: Menu,
                      },
                  ]
                : []),
        ];
    });
}
