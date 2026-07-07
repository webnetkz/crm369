<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useLanguage } from '@/composables/useLanguage';
import type { NavItem } from '@/types';

const props = withDefaults(
    defineProps<{
        items: NavItem[];
        reorderable?: boolean;
        reordering?: boolean;
    }>(),
    {
        reorderable: false,
        reordering: false,
    },
);

const emit = defineEmits<{
    (event: 'reorder', value: string[]): void;
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { t } = useLanguage();
const { isMobile, setOpenMobile, state } = useSidebar();
const visibleHandleKey = ref<string | null>(null);
const dragSourceKey = ref<string | null>(null);
const dropTarget = ref<{ key: string; position: 'before' | 'after' } | null>(
    null,
);
let hoverTimer: ReturnType<typeof setTimeout> | null = null;
let hoverTimerKey: string | null = null;
const useParentHrefWhenCollapsed = computed(() => {
    return state.value === 'collapsed' && !isMobile.value;
});

const isItemActive = (item: NavItem): boolean => {
    return (
        (!item.opensInNewTab && isCurrentOrParentUrl(item.href)) ||
        (item.items?.some((child) => isCurrentOrParentUrl(child.href)) ?? false)
    );
};

const shouldUseAnchor = (item: NavItem): boolean => {
    return (
        typeof item.href === 'string' &&
        (item.opensInNewTab === true || /^https?:\/\//i.test(item.href))
    );
};

const anchorHref = (item: NavItem): string => {
    return typeof item.href === 'string' ? item.href : '#';
};

const anchorTarget = (item: NavItem): string | undefined => {
    return item.opensInNewTab ? '_blank' : undefined;
};

const anchorRel = (item: NavItem): string | undefined => {
    return item.opensInNewTab ? 'noopener noreferrer' : undefined;
};

const handleMenuItemClick = (): void => {
    if (!isMobile.value) {
        return;
    }

    setOpenMobile(false);
};

const canReorderItem = (item: NavItem): item is NavItem & { key: string } => {
    return props.reorderable && typeof item.key === 'string' && item.key !== '';
};

const orderedKeys = (): string[] => {
    return props.items.flatMap((item) => {
        return typeof item.key === 'string' && item.key !== ''
            ? [item.key]
            : [];
    });
};

const clearHoverTimer = (): void => {
    if (!hoverTimer) {
        return;
    }

    clearTimeout(hoverTimer);
    hoverTimer = null;
    hoverTimerKey = null;
};

const revealHandleLater = (item: NavItem): void => {
    if (!canReorderItem(item) || dragSourceKey.value !== null) {
        return;
    }

    clearHoverTimer();
    hoverTimerKey = item.key;
    hoverTimer = setTimeout(() => {
        visibleHandleKey.value = item.key;
        hoverTimer = null;
        hoverTimerKey = null;
    }, 2000);
};

const hideHandle = (item: NavItem): void => {
    if (!canReorderItem(item)) {
        return;
    }

    if (hoverTimerKey === item.key) {
        clearHoverTimer();
    }

    if (dragSourceKey.value === item.key) {
        return;
    }

    if (visibleHandleKey.value === item.key) {
        visibleHandleKey.value = null;
    }

    if (dropTarget.value?.key === item.key) {
        dropTarget.value = null;
    }
};

const showHandle = (item: NavItem): void => {
    if (!canReorderItem(item)) {
        return;
    }

    clearHoverTimer();
    visibleHandleKey.value = item.key;
};

const isHandleVisible = (item: NavItem): boolean => {
    return canReorderItem(item) && visibleHandleKey.value === item.key;
};

const isDropBefore = (item: NavItem): boolean => {
    return (
        canReorderItem(item) &&
        dropTarget.value?.key === item.key &&
        dropTarget.value.position === 'before'
    );
};

const isDropAfter = (item: NavItem): boolean => {
    return (
        canReorderItem(item) &&
        dropTarget.value?.key === item.key &&
        dropTarget.value.position === 'after'
    );
};

const handleDragStart = (event: DragEvent, item: NavItem): void => {
    if (!canReorderItem(item) || props.reordering) {
        event.preventDefault();

        return;
    }

    dragSourceKey.value = item.key;
    visibleHandleKey.value = item.key;
    dropTarget.value = null;
    event.dataTransfer?.setData('text/plain', item.key);
    event.dataTransfer?.setDragImage(
        event.currentTarget as HTMLElement,
        10,
        10,
    );

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const handleDragOver = (event: DragEvent, item: NavItem): void => {
    if (
        !canReorderItem(item) ||
        !dragSourceKey.value ||
        dragSourceKey.value === item.key
    ) {
        return;
    }

    event.preventDefault();

    const targetElement = event.currentTarget as HTMLElement | null;

    if (!targetElement) {
        return;
    }

    const { top, height } = targetElement.getBoundingClientRect();
    const position = event.clientY - top > height / 2 ? 'after' : 'before';
    const currentDropTarget = dropTarget.value;

    if (
        currentDropTarget?.key === item.key &&
        currentDropTarget.position === position
    ) {
        return;
    }

    dropTarget.value = {
        key: item.key,
        position,
    };
};

const resetDragState = (): void => {
    dragSourceKey.value = null;
    dropTarget.value = null;
};

const handleDrop = (item: NavItem): void => {
    if (
        !canReorderItem(item) ||
        !dragSourceKey.value ||
        !dropTarget.value ||
        dropTarget.value.key !== item.key
    ) {
        resetDragState();

        return;
    }

    const keys = orderedKeys();
    const sourceIndex = keys.indexOf(dragSourceKey.value);
    const targetIndex = keys.indexOf(item.key);

    if (
        sourceIndex === -1 ||
        targetIndex === -1 ||
        sourceIndex === targetIndex
    ) {
        resetDragState();

        return;
    }

    const reorderedKeys = [...keys];
    const [movedKey] = reorderedKeys.splice(sourceIndex, 1);

    if (!movedKey) {
        resetDragState();

        return;
    }

    const insertionIndex =
        dropTarget.value.position === 'after'
            ? sourceIndex < targetIndex
                ? targetIndex
                : targetIndex + 1
            : sourceIndex < targetIndex
              ? targetIndex - 1
              : targetIndex;

    reorderedKeys.splice(insertionIndex, 0, movedKey);
    emit('reorder', reorderedKeys);
    visibleHandleKey.value = item.key;
    resetDragState();
};

onBeforeUnmount(() => {
    clearHoverTimer();
});
</script>

<template>
    <SidebarGroup v-if="props.items.length > 0" class="px-2 py-2">
        <SidebarMenu>
            <template v-for="item in props.items" :key="item.key ?? item.title">
                <SidebarMenuItem
                    v-if="item.items?.length && useParentHrefWhenCollapsed"
                    :class="[
                        isDropBefore(item) ? 'border-t-2 border-primary' : '',
                        isDropAfter(item) ? 'border-b-2 border-primary' : '',
                    ]"
                    @mouseenter="revealHandleLater(item)"
                    @mouseleave="hideHandle(item)"
                    @dragover="handleDragOver($event, item)"
                    @drop.prevent="handleDrop(item)"
                >
                    <SidebarMenuButton
                        as-child
                        class="pr-10"
                        :is-active="isItemActive(item)"
                        :tooltip="item.title"
                    >
                        <a
                            v-if="shouldUseAnchor(item)"
                            :href="anchorHref(item)"
                            :target="anchorTarget(item)"
                            :rel="anchorRel(item)"
                            @click="handleMenuItemClick"
                        >
                            <component :is="item.icon" v-if="item.icon" />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            @click="handleMenuItemClick"
                        >
                            <component :is="item.icon" v-if="item.icon" />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>
                        </Link>
                    </SidebarMenuButton>

                    <SidebarMenuAction
                        v-if="canReorderItem(item)"
                        as="button"
                        type="button"
                        :draggable="!props.reordering"
                        :class="
                            isHandleVisible(item)
                                ? 'pointer-events-auto opacity-100'
                                : 'pointer-events-none opacity-0'
                        "
                        :title="t.menu.reorder"
                        @click.stop
                        @focus="showHandle(item)"
                        @blur="hideHandle(item)"
                        @dragstart="handleDragStart($event, item)"
                        @dragend="resetDragState"
                    >
                        <span
                            class="grid grid-cols-2 gap-0.5 text-current"
                            aria-hidden="true"
                        >
                            <span
                                v-for="dot in 6"
                                :key="dot"
                                class="size-1 rounded-full bg-current"
                            />
                        </span>
                        <span class="sr-only">{{ t.menu.reorder }}</span>
                    </SidebarMenuAction>
                </SidebarMenuItem>

                <Collapsible
                    v-else-if="item.items?.length"
                    as-child
                    :default-open="isItemActive(item)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem
                        :class="[
                            isDropBefore(item)
                                ? 'border-t-2 border-primary'
                                : '',
                            isDropAfter(item)
                                ? 'border-b-2 border-primary'
                                : '',
                        ]"
                        @mouseenter="revealHandleLater(item)"
                        @mouseleave="hideHandle(item)"
                        @dragover="handleDragOver($event, item)"
                        @drop.prevent="handleDrop(item)"
                    >
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                class="pr-10"
                                :is-active="isItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span class="min-w-0 flex-1 truncate">{{
                                    item.title
                                }}</span>
                                <ChevronRight
                                    class="mr-5 ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>

                        <SidebarMenuAction
                            v-if="canReorderItem(item)"
                            as="button"
                            type="button"
                            :draggable="!props.reordering"
                            :class="
                                isHandleVisible(item)
                                    ? 'pointer-events-auto opacity-100'
                                    : 'pointer-events-none opacity-0'
                            "
                            :title="t.menu.reorder"
                            @click.stop
                            @focus="showHandle(item)"
                            @blur="hideHandle(item)"
                            @dragstart="handleDragStart($event, item)"
                            @dragend="resetDragState"
                        >
                            <span
                                class="grid grid-cols-2 gap-0.5 text-current"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="dot in 6"
                                    :key="dot"
                                    class="size-1 rounded-full bg-current"
                                />
                            </span>
                            <span class="sr-only">{{ t.menu.reorder }}</span>
                        </SidebarMenuAction>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.items"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="
                                            isCurrentOrParentUrl(child.href)
                                        "
                                    >
                                        <a
                                            v-if="shouldUseAnchor(child)"
                                            :href="anchorHref(child)"
                                            :target="anchorTarget(child)"
                                            :rel="anchorRel(child)"
                                            @click="handleMenuItemClick"
                                        >
                                            <component
                                                :is="child.icon"
                                                v-if="child.icon"
                                            />
                                            <span
                                                class="min-w-0 flex-1 truncate"
                                                >{{ child.title }}</span
                                            >
                                        </a>
                                        <Link
                                            v-else
                                            :href="child.href"
                                            @click="handleMenuItemClick"
                                        >
                                            <component
                                                :is="child.icon"
                                                v-if="child.icon"
                                            />
                                            <span
                                                class="min-w-0 flex-1 truncate"
                                                >{{ child.title }}</span
                                            >
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <SidebarMenuItem
                    v-else
                    :class="[
                        isDropBefore(item) ? 'border-t-2 border-primary' : '',
                        isDropAfter(item) ? 'border-b-2 border-primary' : '',
                    ]"
                    @mouseenter="revealHandleLater(item)"
                    @mouseleave="hideHandle(item)"
                    @dragover="handleDragOver($event, item)"
                    @drop.prevent="handleDrop(item)"
                >
                    <SidebarMenuButton
                        as-child
                        class="pr-10"
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <a
                            v-if="shouldUseAnchor(item)"
                            :href="anchorHref(item)"
                            :target="anchorTarget(item)"
                            :rel="anchorRel(item)"
                            @click="handleMenuItemClick"
                        >
                            <component :is="item.icon" v-if="item.icon" />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            @click="handleMenuItemClick"
                        >
                            <component :is="item.icon" v-if="item.icon" />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>
                        </Link>
                    </SidebarMenuButton>

                    <SidebarMenuAction
                        v-if="canReorderItem(item)"
                        as="button"
                        type="button"
                        :draggable="!props.reordering"
                        :class="
                            isHandleVisible(item)
                                ? 'pointer-events-auto opacity-100'
                                : 'pointer-events-none opacity-0'
                        "
                        :title="t.menu.reorder"
                        @click.stop
                        @focus="showHandle(item)"
                        @blur="hideHandle(item)"
                        @dragstart="handleDragStart($event, item)"
                        @dragend="resetDragState"
                    >
                        <span
                            class="grid grid-cols-2 gap-0.5 text-current"
                            aria-hidden="true"
                        >
                            <span
                                v-for="dot in 6"
                                :key="dot"
                                class="size-1 rounded-full bg-current"
                            />
                        </span>
                        <span class="sr-only">{{ t.menu.reorder }}</span>
                    </SidebarMenuAction>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
