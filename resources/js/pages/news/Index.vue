<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { Newspaper, Rocket, Workflow, Wrench } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/news';

const { language, t } = useLanguage();

const formattedDate = computed(() => {
    return new Intl.DateTimeFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        dateStyle: 'long',
    }).format(new Date());
});

const newsItems = computed(() => {
    return [
        {
            key: 'workspace',
            icon: Newspaper,
            badge: t.value.news.items.workspace.badge,
            title: t.value.news.items.workspace.title,
            description: t.value.news.items.workspace.description,
        },
        {
            key: 'process',
            icon: Workflow,
            badge: t.value.news.items.process.badge,
            title: t.value.news.items.process.title,
            description: t.value.news.items.process.description,
        },
        {
            key: 'roadmap',
            icon: Rocket,
            badge: t.value.news.items.roadmap.badge,
            title: t.value.news.items.roadmap.title,
            description: t.value.news.items.roadmap.description,
        },
    ];
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.news.title,
                href: index(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t.news.title" />

    <h1 class="sr-only">{{ t.news.title }}</h1>

    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-border bg-card">
            <div
                class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_240px]"
            >
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <Newspaper class="size-4" />
                        {{ t.news.hero_eyebrow }}
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ t.news.hero_title }}
                        </h2>
                        <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                            {{ t.news.hero_description }}
                        </p>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-background/80 p-5">
                    <div class="text-sm text-muted-foreground">
                        {{ t.news.title }}
                    </div>
                    <div class="mt-2 text-lg font-semibold">
                        {{ formattedDate }}
                    </div>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        {{ t.news.description }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-6 lg:grid-cols-3">
                <article
                    v-for="item in newsItems"
                    :key="item.key"
                    class="rounded-2xl border border-border bg-background/70 p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                            {{ item.badge }}
                        </span>
                        <component :is="item.icon" class="size-5 text-primary" />
                    </div>

                    <h3 class="mt-4 text-base font-semibold leading-6">
                        {{ item.title }}
                    </h3>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        {{ item.description }}
                    </p>
                </article>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-3xl border border-border p-6">
                <Heading
                    variant="small"
                    :title="t.news.latest_updates"
                    :description="t.news.latest_updates_description"
                />

                <div class="mt-5 space-y-4">
                    <div
                        v-for="item in newsItems"
                        :key="`${item.key}-timeline`"
                        class="flex gap-4 rounded-2xl border border-border/70 p-4"
                    >
                        <div class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <component :is="item.icon" class="size-4" />
                        </div>

                        <div class="min-w-0 space-y-1">
                            <div class="text-sm font-medium">{{ item.title }}</div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl border border-border bg-muted/20 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <Wrench class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold">
                            {{ t.news.watch_title }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t.news.watch_description }}
                        </p>
                    </div>
                </div>

                <ul class="mt-5 space-y-3 text-sm leading-6 text-muted-foreground">
                    <li
                        v-for="point in t.news.watch_points"
                        :key="point"
                        class="rounded-2xl border border-border/70 bg-background/80 px-4 py-3"
                    >
                        {{ point }}
                    </li>
                </ul>
            </aside>
        </section>
    </div>
</template>
