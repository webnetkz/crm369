<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    BookOpenText,
    ClipboardList,
    FileText,
    Factory,
    LayoutGrid,
    LayoutDashboard,
    MessageSquareMore,
    Newspaper,
    Settings,
    ListTodo,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { index as contactsIndex } from '@/routes/contacts';
import { index as edoIndex } from '@/routes/edo';
import { index as formsIndex } from '@/routes/forms';
import { index as newsIndex } from '@/routes/news';
import { index as notificationsIndex } from '@/routes/notifications';
import {
    index as productionIndex,
    show as showProductionSection,
} from '@/routes/production';
import { index as projectsIndex } from '@/routes/projects';
import { edit as editMenu } from '@/routes/settings/menu';
import { update as updateMenuOrder } from '@/routes/settings/menu/order';
import { index as tasksIndex } from '@/routes/tasks';
import type { MenuCustomItem, MenuKnowledgeBaseItem, NavItem } from '@/types';

const page = usePage();
const { t } = useLanguage();
const settingsNavItems = useSettingsNavigation();
const savingMenuOrder = ref(false);
let menuOrderRequestId = 0;

const hiddenMenuItems = computed(() => {
    const hiddenItems = Array.isArray(page.props.menu?.hiddenItems)
        ? page.props.menu.hiddenItems
        : [];

    return new Set(hiddenItems);
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

const baseMainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        ...(isMenuItemVisible('news')
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
        ...(isMenuItemVisible('projects')
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
        ...(isMenuItemVisible('chats')
            ? [
                  {
                      key: 'chats',
                      title: t.value.chat.title,
                      href: chatsIndex(),
                      icon: MessageSquareMore,
                  },
              ]
            : []),
        ...(isMenuItemVisible('knowledge-bases')
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
        ...(isMenuItemVisible('forms')
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
        ...(isMenuItemVisible('edo')
            ? [
                  {
                      key: 'edo',
                      title: t.value.edo.title,
                      href: edoIndex(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(isMenuItemVisible('production')
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
                              title: t.value.production.sections.warehouses
                                  .title,
                              href: showProductionSection('warehouses'),
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
        menuOrder.value.map((key, index) => [key, index] as const),
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

const persistMenuOrder = async (keys: string[]): Promise<void> => {
    const nextOrder = normalizeMenuOrder(keys);
    const currentOrder = [...menuOrder.value];
    const menu = page.props.menu as MenuState;
    const requestId = ++menuOrderRequestId;

    menu.order = nextOrder;
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

        if (requestId === menuOrderRequestId) {
            menu.order = response.order;
        }
    } catch (error) {
        console.error(error);

        if (requestId === menuOrderRequestId) {
            menu.order = currentOrder;
        }
    } finally {
        if (requestId === menuOrderRequestId) {
            savingMenuOrder.value = false;
        }
    }
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
                :reordering="savingMenuOrder"
                @reorder="persistMenuOrder"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
