<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    BookOpenText,
    ClipboardList,
    FolderOpen,
    LayoutGrid,
    LayoutDashboard,
    MessageSquareMore,
    Newspaper,
    Settings,
    ListTodo,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { index as funnelsIndex } from '@/actions/App/Http/Controllers/CrmFunnelController';
import {
    index as knowledgeBasesIndex,
    show as showKnowledgeBase,
} from '@/actions/App/Http/Controllers/KnowledgeBaseController';
import { index as projectsIndex } from '@/actions/App/Http/Controllers/ProjectController';
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
import { index as filesIndex } from '@/routes/files';
import { index as formsIndex } from '@/routes/forms';
import { index as newsIndex } from '@/routes/news';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as editMenu } from '@/routes/settings/menu';
import { update as updateMenuOrder } from '@/routes/settings/menu/order';
import type { Menu as MenuState, NavItem } from '@/types';

const page = usePage();
const { t } = useLanguage();
const settingsNavItems = useSettingsNavigation();
const savingMenuOrder = ref(false);
let menuOrderRequestId = 0;

const hiddenMenuItems = computed(() => new Set(page.props.menu.hiddenItems));
const menuOrder = computed<string[]>(() => {
    return Array.isArray(page.props.menu.order) ? page.props.menu.order : [];
});

const isMenuItemVisible = (key: string): boolean => {
    return !hiddenMenuItems.value.has(key);
};

const baseMainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
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
        ...(isMenuItemVisible('files')
            ? [
                  {
                      key: 'files',
                      title: t.value.files.title,
                      href: filesIndex(),
                      icon: FolderOpen,
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
                          page.props.menu.knowledgeBases.length > 0
                              ? page.props.menu.knowledgeBases.map(
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
        ...(isMenuItemVisible('projects')
            ? [
                  {
                      key: 'projects',
                      title: t.value.projects.title,
                      href: projectsIndex(),
                      icon: ListTodo,
                  },
              ]
            : []),
        ...page.props.menu.customItems.map((item) => ({
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
