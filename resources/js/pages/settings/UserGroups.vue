<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index, store } from '@/routes/settings/groups';
import type { PaginatedCollection } from '@/types/ui';

type UserGroupRow = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
};

const props = defineProps<{
    groups: PaginatedCollection<UserGroupRow>;
    filters: {
        per_page: number;
    };
    perPageOptions: number[];
}>();

const { t } = useLanguage();
const form = useForm({
    name: '',
    description: '',
});
const visibleGroups = computed(() => props.groups.data);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.admin.groups_title,
                href: index(),
            },
        ],
    });
});

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const updatePerPage = (value: number): void => {
    router.get(index.url(), { per_page: value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="t.admin.groups_title" />

    <h1 class="sr-only">{{ t.admin.groups_title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.admin.groups_title"
            :description="t.admin.groups_description"
        />

        <form
            class="space-y-4 rounded-lg border border-border p-4"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t.admin.group_name }}</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    name="name"
                    :placeholder="t.admin.group_name_placeholder"
                    autocomplete="off"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">{{ t.admin.description }}</Label>
                <Input
                    id="description"
                    v-model="form.description"
                    name="description"
                    :placeholder="t.admin.description_placeholder"
                    autocomplete="off"
                />
                <InputError :message="form.errors.description" />
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ t.admin.create_group }}
            </Button>
        </form>

        <div class="overflow-hidden rounded-lg border border-border">
            <table v-if="visibleGroups.length" class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">
                            {{ t.admin.group_name }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t.admin.description }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t.admin.members }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="group in visibleGroups" :key="group.id">
                        <td class="px-4 py-3 font-medium">
                            {{ group.display_name }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ group.description || '-' }}
                        </td>
                        <td class="px-4 py-3">{{ group.users_count }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="p-6 text-sm text-muted-foreground">
                {{ t.admin.groups_empty }}
            </div>
        </div>

        <PaginationControls
            :pagination="groups"
            :per-page-options="perPageOptions"
            @update:per-page="updatePerPage"
        />
    </div>
</template>
