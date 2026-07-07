<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { BookText } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { edit as editDocumentation } from '@/routes/settings/api/documentation';

type DocumentationEndpoint = {
    method: string;
    path: string;
    summary: string;
    permission: string;
    access: string;
    content_type: string;
    target_user: string;
};

type DocumentationSection = {
    title: string;
    description: string;
    notes: string[];
    endpoints: DocumentationEndpoint[];
};

const props = defineProps<{
    baseUrl: string;
    documentation: DocumentationSection[];
}>();

const { t } = useLanguage();
const documentationSectionId = 'api-documentation';
const documentationSectionAnchorId = (index: number): string =>
    `api-doc-section-${index + 1}`;
const sectionCardClass = (title: string): string => {
    return title === t.value.api.section_equipment
        ? 'scroll-mt-40 space-y-3 rounded-2xl border border-border p-4'
        : 'scroll-mt-32 space-y-3 rounded-2xl border border-border p-4';
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.settings.api_documentation,
                href: editDocumentation(),
            },
        ],
    });
});

const documentationNavigationSections = computed(() =>
    props.documentation.map((section, index) => ({
        id: documentationSectionAnchorId(index),
        title: section.title,
    })),
);
</script>

<template>
    <Head :title="t.api.documentation_title" />

    <h1 class="sr-only">{{ t.api.documentation_title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.api.documentation_title"
            :description="t.api.documentation_description"
        />

        <section class="space-y-4 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-2 text-base font-medium">
                <BookText class="size-5" />
                {{ t.api.overview_title }}
            </div>

            <p class="text-sm text-muted-foreground">
                {{ t.api.overview_description }}
            </p>

            <p class="rounded-xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                {{ t.api.target_user_overview }}
            </p>

            <div class="grid gap-2">
                <Label>{{ t.api.base_url }}</Label>
                <Input :model-value="props.baseUrl" readonly />
            </div>
        </section>

        <section
            :id="documentationSectionId"
            class="space-y-4 rounded-2xl border border-border p-5"
        >
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start xl:gap-6">
                <details
                    open
                    class="rounded-2xl border border-border bg-background/95 p-4 shadow-sm xl:hidden"
                >
                    <summary class="cursor-pointer list-none text-sm font-semibold">
                        {{ t.api.documentation_blocks }}
                    </summary>

                    <div class="mt-4 grid gap-2">
                        <a
                            v-for="section in documentationNavigationSections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                        >
                            {{ section.title }}
                        </a>
                    </div>
                </details>

                <div class="space-y-4">
                    <div
                        v-for="(section, index) in props.documentation"
                        :id="documentationSectionAnchorId(index)"
                        :key="section.title"
                        :class="sectionCardClass(section.title)"
                    >
                        <div>
                            <h2 class="text-base font-semibold">{{ section.title }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ section.description }}
                            </p>
                        </div>

                        <div
                            v-if="section.notes.length > 0"
                            class="grid gap-2 rounded-2xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                        >
                            <p
                                v-for="note in section.notes"
                                :key="note"
                            >
                                {{ note }}
                            </p>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-border">
                            <table class="w-full min-w-[1260px] text-sm">
                                <thead class="bg-muted/50 text-left">
                                    <tr>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.method }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.path }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.summary }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.permission }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.access }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.target_user }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.api.content_type }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="endpoint in section.endpoints"
                                        :key="`${section.title}-${endpoint.method}-${endpoint.path}`"
                                    >
                                        <td
                                            class="border-r border-border px-4 py-3 font-semibold"
                                        >
                                            {{ endpoint.method }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 font-mono text-xs text-muted-foreground"
                                        >
                                            {{ endpoint.path }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 text-muted-foreground"
                                        >
                                            {{ endpoint.summary }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 font-mono text-xs text-muted-foreground"
                                        >
                                            {{ endpoint.permission }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 text-muted-foreground"
                                        >
                                            {{ endpoint.access }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 text-muted-foreground"
                                        >
                                            {{ endpoint.target_user }}
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ endpoint.content_type }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <aside class="hidden xl:block xl:sticky xl:top-32">
                    <div class="rounded-2xl border border-border bg-background/95 p-4 shadow-sm supports-[backdrop-filter]:bg-background/80 supports-[backdrop-filter]:backdrop-blur">
                        <div class="text-sm font-semibold">
                            {{ t.api.documentation_blocks }}
                        </div>

                        <div class="mt-4 grid gap-2">
                            <a
                                v-for="section in documentationNavigationSections"
                                :key="section.id"
                                :href="`#${section.id}`"
                                class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                            >
                                {{ section.title }}
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
