<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    ClipboardList,
    Cpu,
    Factory,
    Package,
    PackageCheck,
    ShieldCheck,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { useLanguage } from '@/composables/useLanguage';
import {
    index as productionIndex,
    show as showProductionSection,
} from '@/routes/production';

type ProductionSectionKey =
    | 'overview'
    | 'workshops'
    | 'machines'
    | 'raw-materials'
    | 'finished-products'
    | 'production-orders'
    | 'quality-control';

type SectionMetric = {
    label: string;
    value: string;
    caption: string;
};

type SectionCard = {
    title: string;
    description: string;
};

type SectionRecord = {
    title: string;
    badge: string;
    description: string;
};

type SectionContent = {
    title: string;
    summary: string;
    hero_title: string;
    hero_description: string;
    metrics: SectionMetric[];
    cards: SectionCard[];
    records_title: string;
    records_description: string;
    records: SectionRecord[];
    focus_title: string;
    focus_points: string[];
    next_steps_title: string;
    next_steps: string[];
};

const props = defineProps<{
    activeSection: ProductionSectionKey;
    sections: ProductionSectionKey[];
}>();

const { t } = useLanguage();

const sectionIcons: Record<ProductionSectionKey, LucideIcon> = {
    overview: Factory,
    workshops: Building2,
    machines: Cpu,
    'raw-materials': Package,
    'finished-products': PackageCheck,
    'production-orders': ClipboardList,
    'quality-control': ShieldCheck,
};

const sectionHref = (section: ProductionSectionKey) => {
    return section === 'overview'
        ? productionIndex()
        : showProductionSection(section);
};

const sectionDefinitions = computed(() => {
    return props.sections.map((key) => ({
        key,
        title: t.value.production.sections[key].title,
        summary: t.value.production.sections[key].summary,
        href: sectionHref(key),
        icon: sectionIcons[key],
    }));
});

const activeSectionData = computed<SectionContent>(() => {
    return t.value.production.sections[props.activeSection];
});

const activeSectionIcon = computed(() => {
    return sectionIcons[props.activeSection];
});

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.production.title,
            href: productionIndex(),
        },
    ];

    if (props.activeSection !== 'overview') {
        breadcrumbs.push({
            title: activeSectionData.value.title,
            href: sectionHref(props.activeSection),
        });
    }

    setLayoutProps({
        breadcrumbs,
    });
});
</script>

<template>
    <Head :title="`${activeSectionData.title} | ${t.production.title}`" />

    <h1 class="sr-only">{{ t.production.title }}</h1>

    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-border bg-card">
            <div
                class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px]"
            >
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <component :is="activeSectionIcon" class="size-4" />
                        {{ t.production.eyebrow }}
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ activeSectionData.hero_title }}
                        </h2>
                        <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                            {{ activeSectionData.hero_description }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <div
                        v-for="metric in activeSectionData.metrics"
                        :key="metric.label"
                        class="rounded-2xl border border-border bg-background/80 p-4"
                    >
                        <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            {{ metric.label }}
                        </div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ metric.value }}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ metric.caption }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-4 px-6 py-5">
                <Heading
                    variant="small"
                    :title="t.production.navigation_title"
                    :description="t.production.navigation_description"
                />

                <div class="flex flex-wrap gap-3">
                    <Link
                        v-for="section in sectionDefinitions"
                        :key="section.key"
                        :href="section.href"
                        class="group flex min-w-[220px] flex-1 items-start gap-3 rounded-2xl border px-4 py-3 transition-colors md:flex-none md:basis-[calc(50%-0.5rem)] xl:basis-[calc(25%-0.75rem)]"
                        :class="
                            section.key === activeSection
                                ? 'border-primary/30 bg-primary/8 text-foreground'
                                : 'border-border bg-background/70 text-muted-foreground hover:border-primary/20 hover:bg-primary/5 hover:text-foreground'
                        "
                    >
                        <div
                            class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-2xl"
                            :class="
                                section.key === activeSection
                                    ? 'bg-primary/12 text-primary'
                                    : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'
                            "
                        >
                            <component :is="section.icon" class="size-4" />
                        </div>

                        <div class="min-w-0 space-y-1">
                            <div class="font-medium leading-5">
                                {{ section.title }}
                            </div>
                            <p class="text-sm leading-5 text-muted-foreground">
                                {{ section.summary }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.production.cards_title"
                        :description="activeSectionData.summary"
                    />

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <article
                            v-for="card in activeSectionData.cards"
                            :key="card.title"
                            class="rounded-2xl border border-border bg-background/70 p-5"
                        >
                            <div class="flex items-center gap-2 text-sm font-medium text-primary">
                                <BadgeCheck class="size-4" />
                                <span>{{ t.production.eyebrow }}</span>
                            </div>

                            <h3 class="mt-4 text-base font-semibold leading-6">
                                {{ card.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                                {{ card.description }}
                            </p>
                        </article>
                    </div>
                </div>

                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="activeSectionData.records_title"
                        :description="activeSectionData.records_description"
                    />

                    <div class="mt-5 space-y-4">
                        <article
                            v-for="record in activeSectionData.records"
                            :key="record.title"
                            class="rounded-2xl border border-border bg-background/70 p-5"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-2">
                                    <h3 class="text-base font-semibold leading-6">
                                        {{ record.title }}
                                    </h3>
                                    <p class="text-sm leading-6 text-muted-foreground">
                                        {{ record.description }}
                                    </p>
                                </div>

                                <span class="inline-flex w-fit rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                    {{ record.badge }}
                                </span>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-border bg-muted/20 p-6">
                    <Heading
                        variant="small"
                        :title="activeSectionData.focus_title"
                        :description="t.production.focus_label"
                    />

                    <ul class="mt-5 space-y-3 text-sm leading-6 text-muted-foreground">
                        <li
                            v-for="point in activeSectionData.focus_points"
                            :key="point"
                            class="rounded-2xl border border-border/70 bg-background/80 px-4 py-3"
                        >
                            {{ point }}
                        </li>
                    </ul>
                </div>

                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="activeSectionData.next_steps_title"
                        :description="t.production.next_steps_label"
                    />

                    <div class="mt-5 space-y-3">
                        <div
                            v-for="step in activeSectionData.next_steps"
                            :key="step"
                            class="flex items-start gap-3 rounded-2xl border border-border bg-background/70 px-4 py-3"
                        >
                            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <ArrowRight class="size-4" />
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ step }}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</template>
