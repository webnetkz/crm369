<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type { PaginatedCollection } from '@/types/ui';

const props = defineProps<{
    pagination: PaginatedCollection<unknown>;
    perPageOptions: number[];
}>();

const emit = defineEmits<{
    (event: 'update:perPage', value: number): void;
}>();

const { t } = useLanguage();

const pageLinks = computed(() => {
    return props.pagination.links.filter((link) => {
        return ! ['&laquo; Previous', 'Next &raquo;'].includes(link.label);
    });
});

const previousPageUrl = computed(() => {
    return props.pagination.links[0]?.url ?? undefined;
});

const nextPageUrl = computed(() => {
    return props.pagination.links[props.pagination.links.length - 1]?.url ?? undefined;
});

const handlePerPageChange = (event: Event): void => {
    const target = event.target as HTMLSelectElement;
    emit('update:perPage', Number(target.value));
};
</script>

<template>
    <div class="flex flex-col gap-4 rounded-2xl border border-border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2 sm:space-y-1">
            <div class="text-sm font-medium text-foreground">
                {{ t.common.showing_results }}
            </div>
            <div class="text-sm text-muted-foreground">
                {{ pagination.meta.from ?? 0 }}-{{ pagination.meta.to ?? 0 }} / {{ pagination.meta.total }}
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <div class="flex items-center gap-2">
                <Label for="per_page" class="text-sm">{{ t.common.per_page }}</Label>
                <select
                    id="per_page"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    :value="pagination.meta.per_page"
                    @change="handlePerPageChange"
                >
                    <option v-for="option in perPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>

            <div v-if="pagination.meta.has_pages" class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="previousPageUrl"
                    as-child
                    type="button"
                    variant="outline"
                    size="sm"
                >
                    <Link :href="previousPageUrl">{{ t.common.previous }}</Link>
                </Button>
                <Button v-else type="button" variant="outline" size="sm" disabled>
                    {{ t.common.previous }}
                </Button>

                <div class="hidden items-center gap-2 md:flex">
                    <template v-for="link in pageLinks" :key="`${link.label}-${link.url}`">
                        <Button
                            v-if="link.url"
                            as-child
                            type="button"
                            :variant="link.active ? 'default' : 'outline'"
                            size="sm"
                        >
                            <Link :href="link.url">{{ link.label }}</Link>
                        </Button>
                        <Button v-else type="button" variant="outline" size="sm" disabled>
                            {{ link.label }}
                        </Button>
                    </template>
                </div>

                <Button
                    v-if="nextPageUrl"
                    as-child
                    type="button"
                    variant="outline"
                    size="sm"
                >
                    <Link :href="nextPageUrl">{{ t.common.next }}</Link>
                </Button>
                <Button v-else type="button" variant="outline" size="sm" disabled>
                    {{ t.common.next }}
                </Button>
            </div>
        </div>
    </div>
</template>
