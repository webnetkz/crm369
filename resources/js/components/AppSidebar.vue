<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    BookText,
    BookOpenText,
    Boxes,
    ClipboardList,
    FileText,
    Factory,
    LayoutGrid,
    LayoutDashboard,
    MessageSquareMore,
    Network,
    Newspaper,
    Package,
    QrCode,
    ScanLine,
    Settings,
    ListTodo,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { index as funnelsIndex } from '@/actions/App/Http/Controllers/CrmFunnelController';
import {
    index as knowledgeBasesIndex,
    show as showKnowledgeBase,
} from '@/actions/App/Http/Controllers/KnowledgeBaseController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useLanguage } from '@/composables/useLanguage';
import { useSettingsNavigation } from '@/composables/useSettingsNavigation';
import { resolveMenuIcon } from '@/lib/menuIcons';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import { dashboard } from '@/routes';
import { index as chatsIndex } from '@/routes/chats';
import { index as companyStructureIndex } from '@/routes/company-structure';
import { index as contactsIndex } from '@/routes/contacts';
import { index as directoriesIndex } from '@/routes/directories';
import { index as documentationIndex } from '@/routes/documentation';
import { index as edoIndex } from '@/routes/edo';
import { index as equipmentIndex } from '@/routes/equipment';
import { index as formsIndex } from '@/routes/forms';
import { index as newsIndex } from '@/routes/news';
import { index as notificationsIndex } from '@/routes/notifications';
import {
    index as productionIndex,
    show as showProductionSection,
} from '@/routes/production';
import { index as projectsIndex } from '@/routes/projects';
import { index as qrIndex } from '@/routes/qr';
import { edit as editMenu } from '@/routes/settings/menu';
import { update as updateMenuOrder } from '@/routes/settings/menu/order';
import { index as tasksIndex } from '@/routes/tasks';
import { index as tsdIndex } from '@/routes/tsd';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { MenuCustomItem, MenuKnowledgeBaseItem, NavItem } from '@/types';

type MenuState = {
    order?: string[];
};

const page = usePage();
const { t } = useLanguage();
const settingsNavItems = useSettingsNavigation();
const savingMenuOrder = ref(false);
const optimisticMenuOrder = ref<string[]>([]);
let queuedMenuOrder: string[] | null = null;
let inFlightMenuOrder: string[] | null = null;
let confirmedMenuOrder: string[] = [];

const hiddenMenuItems = computed(() => {
    const hiddenItems = Array.isArray(page.props.menu?.hiddenItems)
        ? page.props.menu.hiddenItems
        : [];

    return new Set(hiddenItems);
});

const enabledModules = computed(() => {
    return new Set(page.props.portal?.enabledModules ?? []);
});

const knowledgeBaseMenuItems = computed<MenuKnowledgeBaseItem[]>(() => {
    return Array.isArray(page.props.menu?.knowledgeBases)
        ? page.props.menu.knowledgeBases
        : [];
});

const customMenuItems = computed<MenuCustomItem[]>(() => {
    return Array.isArray(page.props.menu?.customItems)
        ? page.props.menu.customItems
        : [];
});

const menuOrder = computed<string[]>(() => {
    return Array.isArray(page.props.menu?.order) ? page.props.menu.order : [];
});

const isMenuItemVisible = (key: string): boolean => {
    return !hiddenMenuItems.value.has(key);
};

const isModuleEnabled = (key: string): boolean => {
    return enabledModules.value.has(key);
};

const baseMainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        ...(page.props.auth.canAccessNews && isMenuItemVisible('news')
            ? [
                  {
                      key: 'news',
                      title: t.value.news.title,
                      href: newsIndex(),
                      icon: Newspaper,
                  },
              ]
            : []),
        ...(isMenuItemVisible('notifications')
            ? [
                  {
                      key: 'notifications',
                      title: t.value.notifications.panel_title,
                      href: notificationsIndex(),
                      icon: BellRing,
                  },
              ]
            : []),
        ...(isMenuItemVisible('dashboard')
            ? [
                  {
                      key: 'dashboard',
                      title: t.value.common.dashboard,
                      href: dashboard(),
                      icon: LayoutDashboard,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessCompanyStructure &&
        isMenuItemVisible('company-structure')
            ? [
                  {
                      key: 'company-structure',
                      title: t.value.company_structure.title,
                      href: companyStructureIndex(),
                      icon: Network,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessProjects && isMenuItemVisible('projects')
            ? [
                  {
                      key: 'projects',
                      title: t.value.projects.title,
                      href: projectsIndex(),
                      icon: ListTodo,
                      items: [
                          {
                              title: t.value.projects.tasks,
                              href: tasksIndex(),
                          },
                          {
                              title: t.value.projects.projects_label,
                              href: projectsIndex(),
                          },
                      ],
                  },
              ]
            : []),
        ...(page.props.auth.canAccessChats && isMenuItemVisible('chats')
            ? [
                  {
                      key: 'chats',
                      title: t.value.chat.title,
                      href: chatsIndex(),
                      icon: MessageSquareMore,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessKnowledgeBases &&
        isMenuItemVisible('knowledge-bases')
            ? [
                  {
                      key: 'knowledge-bases',
                      title: t.value.knowledge.title,
                      href: knowledgeBasesIndex(),
                      icon: BookOpenText,
                      items:
                          knowledgeBaseMenuItems.value.length > 0
                              ? knowledgeBaseMenuItems.value.map(
                                    (knowledgeBase) => ({
                                        title: knowledgeBase.title,
                                        href: showKnowledgeBase(
                                            knowledgeBase.id,
                                        ),
                                    }),
                                )
                              : undefined,
                  },
              ]
            : []),
        ...(isMenuItemVisible('funnels')
            ? [
                  {
                      key: 'funnels',
                      title: t.value.funnels.title,
                      href: funnelsIndex(),
                      icon: LayoutGrid,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessForms && isMenuItemVisible('forms')
            ? [
                  {
                      key: 'forms',
                      title: t.value.forms.title,
                      href: formsIndex(),
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessContacts && isMenuItemVisible('contacts')
            ? [
                  {
                      key: 'contacts',
                      title: t.value.contacts.title,
                      href: contactsIndex(),
                      icon: Users,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessDirectories &&
        isMenuItemVisible('directories')
            ? [
                  {
                      key: 'directories',
                      title: t.value.directories.title,
                      href: directoriesIndex(),
                      icon: BookOpenText,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessEdo && isMenuItemVisible('edo')
            ? [
                  {
                      key: 'edo',
                      title: t.value.edo.title,
                      href: edoIndex(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessProduction &&
        isMenuItemVisible('production')
            ? [
                  {
                      key: 'production',
                      title: t.value.production.title,
                      href: productionIndex(),
                      icon: Factory,
                      items: [
                          {
                              title: t.value.production.sections.overview.title,
                              href: productionIndex(),
                          },
                          {
                              title: t.value.production.sections.workshops
                                  .title,
                              href: showProductionSection('workshops'),
                          },
                          {
                              title: t.value.production.sections.machines.title,
                              href: showProductionSection('machines'),
                          },
                          {
                              title: t.value.production.sections[
                                  'raw-materials'
                              ].title,
                              href: showProductionSection('raw-materials'),
                          },
                          {
                              title: t.value.production.sections[
                                  'finished-products'
                              ].title,
                              href: showProductionSection('finished-products'),
                          },
                          {
                              title: t.value.production.sections[
                                  'production-orders'
                              ].title,
                              href: showProductionSection('production-orders'),
                          },
                          {
                              title: t.value.production.sections[
                                  'quality-control'
                              ].title,
                              href: showProductionSection('quality-control'),
                          },
                      ],
                  },
              ]
            : []),
        ...(page.props.auth.canAccessWarehouses &&
        isMenuItemVisible('warehouses')
            ? [
                  {
                      key: 'warehouses',
                      title: t.value.warehouses.title,
                      href: warehousesIndex(),
                      icon: Boxes,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessTsd && isMenuItemVisible('qr')
            ? [
                  {
                      key: 'qr',
                      title: t.value.tsd.quick_scan_title,
                      href: qrIndex(),
                      icon: ScanLine,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessTsd && isMenuItemVisible('tsd')
            ? [
                  {
                      key: 'tsd',
                      title: t.value.tsd.title,
                      href: tsdIndex(),
                      icon: QrCode,
                  },
              ]
            : []),
        ...(page.props.auth.canAccessEquipment &&
        isMenuItemVisible('equipment')
            ? [
                  {
                      key: 'equipment',
                      title: t.value.equipment.title,
                      href: equipmentIndex(),
                      icon: Package,
                  },
              ]
            : []),
        ...(isMenuItemVisible('documentation') &&
        (isModuleEnabled('api') ||
            (isModuleEnabled('webhooks') && page.props.auth.canManageWebhooks))
            ? [
                  {
                      key: 'documentation',
                      title: t.value.documentation.title,
                      href: documentationIndex.url(),
                      icon: BookText,
                      opensInNewTab: true,
                  },
              ]
            : []),
        ...customMenuItems.value.map((item) => ({
            key: `custom:${item.id}`,
            title: item.title,
            href: item.url,
            icon: resolveMenuIcon(item.icon),
            opensInNewTab: item.opensInNewTab,
        })),
    ];

    const settingsItems = settingsNavItems.value;

    if (settingsItems.length > 0) {
        items.push({
            key: 'settings',
            title: t.value.common.settings,
            href: settingsItems[0]?.href ?? editMenu(),
            icon: Settings,
            items: settingsItems,
        });
    }

    return items;
});

const mainNavItems = computed<NavItem[]>(() => {
    const orderMap = new Map(
        optimisticMenuOrder.value.map((key, index) => [key, index] as const),
    );

    return baseMainNavItems.value
        .map((item, index) => ({
            item,
            index,
            orderIndex:
                typeof item.key === 'string'
                    ? orderMap.get(item.key)
                    : undefined,
        }))
        .sort((first, second) => {
            if (
                typeof first.orderIndex === 'number' &&
                typeof second.orderIndex === 'number'
            ) {
                return first.orderIndex - second.orderIndex;
            }

            if (typeof first.orderIndex === 'number') {
                return -1;
            }

            if (typeof second.orderIndex === 'number') {
                return 1;
            }

            return first.index - second.index;
        })
        .map(({ item }) => item);
});

const normalizeMenuOrder = (keys: string[]): string[] => {
    const allowedKeys = new Set(
        baseMainNavItems.value
            .map((item) => item.key)
            .filter(
                (key): key is string => typeof key === 'string' && key !== '',
            ),
    );

    return keys.filter((key, index) => {
        return allowedKeys.has(key) && keys.indexOf(key) === index;
    });
};

const isSameOrder = (first: string[], second: string[]): boolean => {
    return (
        first.length === second.length &&
        first.every((value, index) => value === second[index])
    );
};

watch(
    menuOrder,
    (value) => {
        const normalizedOrder = normalizeMenuOrder(value);

        confirmedMenuOrder = [...normalizedOrder];

        if (queuedMenuOrder === null && inFlightMenuOrder === null) {
            optimisticMenuOrder.value = normalizedOrder;
        }
    },
    {
        immediate: true,
    },
);

const persistQueuedMenuOrder = async (): Promise<void> => {
    if (savingMenuOrder.value || queuedMenuOrder === null) {
        return;
    }

    const nextOrder = [...queuedMenuOrder];
    queuedMenuOrder = null;
    inFlightMenuOrder = nextOrder;
    savingMenuOrder.value = true;

    try {
        const response = await fetchSameOriginJson<{ order: string[] }>(
            updateMenuOrder.url(),
            {
                method: 'PATCH',
                body: JSON.stringify({
                    items: nextOrder,
                }),
            },
        );

        const resolvedOrder = normalizeMenuOrder(response.order);
        const menu = page.props.menu as MenuState | undefined;

        confirmedMenuOrder = [...resolvedOrder];

        if (menu) {
            menu.order = resolvedOrder;
        }

        if (queuedMenuOrder === null) {
            optimisticMenuOrder.value = resolvedOrder;
        }
    } catch (error) {
        console.error(error);

        if (queuedMenuOrder === null) {
            optimisticMenuOrder.value = [...confirmedMenuOrder];
        }
    } finally {
        inFlightMenuOrder = null;
        savingMenuOrder.value = false;

        if (queuedMenuOrder !== null) {
            void persistQueuedMenuOrder();
        }
    }
};

const persistMenuOrder = (keys: string[]): void => {
    const nextOrder = normalizeMenuOrder(keys);

    if (
        isSameOrder(nextOrder, optimisticMenuOrder.value) &&
        queuedMenuOrder === null &&
        inFlightMenuOrder === null
    ) {
        return;
    }

    optimisticMenuOrder.value = nextOrder;
    queuedMenuOrder = [...nextOrder];
    void persistQueuedMenuOrder();
};
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="overflow-x-hidden overflow-y-auto">
            <NavMain
                :items="mainNavItems"
                :reorderable="true"
                @reorder="persistMenuOrder"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
