<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Building2, Network, UsersRound } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import CompanyStructureNode from '@/components/CompanyStructureNode.vue';
import Heading from '@/components/Heading.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/company-structure';
import type {
    CompanyStructureNode as CompanyStructureNodeType,
    CompanyStructureStats,
} from '@/types/ui';

const props = defineProps<{
    stats: CompanyStructureStats;
    roots: CompanyStructureNodeType[];
}>();

const { getInitials } = useInitials();
const { t } = useLanguage();
const selectedUserId = ref<number | null>(props.roots[0]?.id ?? null);

const flattenNodes = (
    nodes: CompanyStructureNodeType[],
): CompanyStructureNodeType[] => {
    return nodes.flatMap((node) => [node, ...flattenNodes(node.children)]);
};

const nodesById = computed(() => {
    return new Map(
        flattenNodes(props.roots).map((node) => [node.id, node] as const),
    );
});

const selectedUser = computed(() => {
    if (selectedUserId.value === null) {
        return null;
    }

    return nodesById.value.get(selectedUserId.value) ?? null;
});

const selectedAvatarStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${selectedUser.value?.avatar_scale ?? 1})`,
}));

const statCards = computed(() => [
    {
        key: 'total',
        title: t.value.company_structure.total_users,
        value: props.stats.total_users,
        icon: UsersRound,
    },
    {
        key: 'roots',
        title: t.value.company_structure.root_users,
        value: props.stats.root_users,
        icon: Building2,
    },
    {
        key: 'managers',
        title: t.value.company_structure.managers,
        value: props.stats.managers,
        icon: Network,
    },
]);

const selectUser = (node: CompanyStructureNodeType): void => {
    selectedUserId.value = node.id;
};

watchEffect(() => {
    if (!selectedUser.value && props.roots[0]) {
        selectedUserId.value = props.roots[0].id;
    }

    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.company_structure.title,
                href: index(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t.company_structure.title" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t.company_structure.title"
            :description="t.company_structure.page_description"
        />

        <section class="grid gap-4 md:grid-cols-3">
            <article
                v-for="card in statCards"
                :key="card.key"
                class="relative overflow-hidden rounded-[1.75rem] border border-border bg-card p-5 shadow-sm"
            >
                <div
                    class="absolute -top-8 right-0 h-24 w-24 rounded-full bg-primary/6 blur-2xl"
                />
                <div
                    class="relative flex items-center justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs uppercase tracking-[0.24em] text-muted-foreground"
                        >
                            {{ card.title }}
                        </div>
                        <div class="mt-3 text-3xl font-semibold">
                            {{ card.value }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-muted/60 p-3 text-foreground"
                    >
                        <component :is="card.icon" class="size-5" />
                    </div>
                </div>
            </article>
        </section>

        <section
            class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_360px]"
        >
            <div
                class="rounded-[2rem] border border-border bg-card p-5 shadow-sm"
            >
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold">
                            {{ t.company_structure.tree_title }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ t.company_structure.description }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="props.roots.length"
                    class="max-h-[72vh] overflow-y-auto pr-1"
                >
                    <ul class="space-y-5">
                        <CompanyStructureNode
                            v-for="root in props.roots"
                            :key="root.id"
                            :node="root"
                            :selected-id="selectedUserId"
                            @select="selectUser"
                        />
                    </ul>
                </div>

                <div
                    v-else
                    class="rounded-[1.5rem] border border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground"
                >
                    {{ t.company_structure.select_prompt }}
                </div>
            </div>

            <aside
                class="rounded-[2rem] border border-border bg-card p-5 shadow-sm xl:sticky xl:top-6 xl:h-fit"
            >
                <div class="mb-4">
                    <div class="text-lg font-semibold">
                        {{ t.company_structure.detail_title }}
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ t.company_structure.detail_description }}
                    </div>
                </div>

                <div
                    v-if="selectedUser"
                    class="space-y-4"
                >
                    <div class="flex items-start gap-4">
                        <Avatar
                            class="size-18 overflow-hidden rounded-3xl border border-border shadow-sm"
                        >
                            <AvatarImage
                                v-if="selectedUser.avatar"
                                :src="selectedUser.avatar"
                                :alt="selectedUser.full_name"
                                :style="selectedAvatarStyle"
                            />
                            <AvatarFallback
                                class="bg-muted text-lg font-semibold text-foreground"
                            >
                                {{ getInitials(selectedUser.full_name) }}
                            </AvatarFallback>
                        </Avatar>

                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="truncate text-xl font-semibold">
                                {{ selectedUser.full_name }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{
                                    selectedUser.position ??
                                    t.company_structure.no_position
                                }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ selectedUser.email }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.22em] text-muted-foreground">
                                {{ t.company_structure.manager }}
                            </div>
                            <div class="mt-2 text-sm font-medium">
                                {{
                                    selectedUser.manager
                                        ? selectedUser.manager.position
                                            ? `${selectedUser.manager.full_name} · ${selectedUser.manager.position}`
                                            : selectedUser.manager.full_name
                                        : t.company_structure.no_manager
                                }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.22em] text-muted-foreground">
                                {{ t.company_structure.subordinates }}
                            </div>
                            <div
                                v-if="selectedUser.subordinates.length"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="subordinate in selectedUser.subordinates"
                                    :key="subordinate.id"
                                    class="rounded-full border border-border bg-muted/60 px-3 py-1 text-xs font-medium text-foreground"
                                >
                                    {{
                                        subordinate.position
                                            ? `${subordinate.full_name} · ${subordinate.position}`
                                            : subordinate.full_name
                                    }}
                                </span>
                            </div>
                            <div
                                v-else
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                {{ t.company_structure.no_subordinates }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-[1.5rem] border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t.company_structure.select_prompt }}
                </div>
            </aside>
        </section>
    </div>
</template>
