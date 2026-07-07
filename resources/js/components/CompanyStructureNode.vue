<script setup lang="ts">
import { Briefcase, UserRound, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import type { CompanyStructureNode as CompanyStructureNodeType } from '@/types/ui';

defineOptions({
    name: 'CompanyStructureNode',
});

const props = withDefaults(
    defineProps<{
        node: CompanyStructureNodeType;
        selectedId?: number | null;
    }>(),
    {
        selectedId: null,
    },
);

const emit = defineEmits<{
    (event: 'select', node: CompanyStructureNodeType): void;
}>();

const { getInitials } = useInitials();
const { t } = useLanguage();

const isSelected = computed(() => props.selectedId === props.node.id);

const avatarStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${props.node.avatar_scale ?? 1})`,
}));

const subordinatePreview = computed(() => props.node.subordinates.slice(0, 3));

const managerLabel = computed(() => {
    if (!props.node.manager) {
        return t.value.company_structure.no_manager;
    }

    return props.node.manager.position
        ? `${props.node.manager.full_name} · ${props.node.manager.position}`
        : props.node.manager.full_name;
});
</script>

<template>
    <li class="space-y-4">
        <button
            type="button"
            class="w-full rounded-[1.75rem] border p-5 text-left shadow-sm transition focus-visible:ring-2 focus-visible:ring-ring/70 focus-visible:outline-none"
            :class="
                isSelected
                    ? 'border-primary/60 bg-primary/6 shadow-primary/10'
                    : 'border-border bg-card hover:border-primary/30 hover:bg-accent/25'
            "
            @click="emit('select', node)"
        >
            <div class="flex items-start gap-4">
                <Avatar
                    class="size-16 overflow-hidden rounded-3xl border border-border shadow-sm"
                >
                    <AvatarImage
                        v-if="node.avatar"
                        :src="node.avatar"
                        :alt="node.full_name"
                        :style="avatarStyle"
                    />
                    <AvatarFallback
                        class="bg-muted text-sm font-semibold text-foreground"
                    >
                        {{ getInitials(node.full_name) }}
                    </AvatarFallback>
                </Avatar>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="truncate text-base font-semibold">
                            {{ node.full_name }}
                        </div>
                        <span
                            v-if="!node.is_active"
                            class="rounded-full bg-destructive/10 px-2.5 py-1 text-[11px] font-medium text-destructive"
                        >
                            {{ t.admin.inactive }}
                        </span>
                    </div>

                    <div class="text-sm text-muted-foreground">
                        {{
                            node.position ??
                            t.company_structure.no_position
                        }}
                    </div>
                </div>

                <div
                    class="shrink-0 rounded-2xl border border-border bg-muted/50 px-3 py-2 text-right"
                >
                    <div class="text-lg font-semibold leading-none">
                        {{ node.subordinates_count }}
                    </div>
                    <div class="mt-1 text-[11px] text-muted-foreground">
                        {{ t.company_structure.subordinates }}
                    </div>
                </div>
            </div>

            <dl class="mt-5 grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-border/70 bg-background/70 p-3">
                    <div
                        class="mb-1 flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        <UserRound class="size-3.5" />
                        {{ t.company_structure.manager }}
                    </div>
                    <div class="text-sm font-medium">
                        {{ managerLabel }}
                    </div>
                </div>

                <div class="rounded-2xl border border-border/70 bg-background/70 p-3">
                    <div
                        class="mb-1 flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        <Briefcase class="size-3.5" />
                        {{ t.company_structure.position }}
                    </div>
                    <div class="text-sm font-medium">
                        {{
                            node.position ??
                            t.company_structure.no_position
                        }}
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border/70 bg-background/70 p-3 lg:col-span-2"
                >
                    <div
                        class="mb-2 flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-muted-foreground"
                    >
                        <UsersRound class="size-3.5" />
                        {{ t.company_structure.subordinates }}
                    </div>

                    <div
                        v-if="subordinatePreview.length"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="subordinate in subordinatePreview"
                            :key="subordinate.id"
                            class="rounded-full border border-border bg-muted/60 px-3 py-1 text-xs font-medium text-foreground"
                        >
                            {{
                                subordinate.position
                                    ? `${subordinate.full_name} · ${subordinate.position}`
                                    : subordinate.full_name
                            }}
                        </span>
                        <span
                            v-if="node.subordinates_count > subordinatePreview.length"
                            class="rounded-full border border-dashed border-border px-3 py-1 text-xs text-muted-foreground"
                        >
                            +{{
                                node.subordinates_count -
                                subordinatePreview.length
                            }}
                        </span>
                    </div>

                    <div v-else class="text-sm text-muted-foreground">
                        {{ t.company_structure.no_subordinates }}
                    </div>
                </div>
            </dl>
        </button>

        <div
            v-if="node.children.length"
            class="ml-4 border-l border-dashed border-border pl-5"
        >
            <div
                class="mb-3 text-[11px] font-medium uppercase tracking-[0.22em] text-muted-foreground"
            >
                {{ t.company_structure.branch_title }}
            </div>

            <ul class="space-y-4">
                <CompanyStructureNode
                    v-for="child in node.children"
                    :key="child.id"
                    :node="child"
                    :selected-id="selectedId"
                    @select="emit('select', $event)"
                />
            </ul>
        </div>
    </li>
</template>
