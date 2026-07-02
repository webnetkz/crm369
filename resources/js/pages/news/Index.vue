<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    FilePlus2,
    Newspaper,
    PencilLine,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import {
    destroy,
    index as newsIndex,
    show as showNews,
    store,
    update,
} from '@/actions/App/Http/Controllers/NewsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';
import type { NewsActiveItem, NewsListItem } from '@/types/ui';

type Props = {
    newsItems: NewsListItem[];
    activeNews: NewsActiveItem | null;
    can: {
        manage: boolean;
    };
    editor: {
        shouldOpenCreate: boolean;
    };
};

const props = defineProps<Props>();
const { language, t } = useLanguage();
const editorOpen = ref(false);
const editorMode = ref<'create' | 'edit'>('create');

const form = useForm({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    is_published: true,
    image_file: null as File | null,
    remove_image: false,
});

const pageTitle = computed(() => {
    return props.activeNews?.title ?? t.value.news.title;
});

const feedHasItems = computed(() => {
    return props.newsItems.length > 0;
});

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.news.title,
            href: newsIndex(),
        },
    ];

    if (props.activeNews) {
        breadcrumbs.push({
            title: props.activeNews.title,
            href: showNews(props.activeNews.slug),
        });
    }

    setLayoutProps({
        breadcrumbs,
    });
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'long',
        },
    ).format(new Date(value));
};

const resetForm = (): void => {
    form.reset();
    form.clearErrors();
    form.title = '';
    form.slug = '';
    form.excerpt = '';
    form.content = '';
    form.is_published = true;
    form.image_file = null;
    form.remove_image = false;
};

const openCreate = (): void => {
    resetForm();
    editorMode.value = 'create';
    editorOpen.value = true;
};

const openEdit = (): void => {
    if (!props.activeNews) {
        return;
    }

    resetForm();
    form.title = props.activeNews.title;
    form.slug = props.activeNews.slug;
    form.excerpt = props.activeNews.excerpt ?? '';
    form.content = props.activeNews.content;
    form.is_published = props.activeNews.is_published;
    form.remove_image = false;
    editorMode.value = 'edit';
    editorOpen.value = true;
};

watch(
    () => props.editor.shouldOpenCreate,
    (shouldOpenCreate) => {
        if (shouldOpenCreate && props.can.manage) {
            openCreate();
        }
    },
    { immediate: true },
);

const submit = (): void => {
    if (editorMode.value === 'edit' && props.activeNews) {
        form.post(update.form.patch(props.activeNews.slug).action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                editorOpen.value = false;
            },
        });

        return;
    }

    form.post(store.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
        },
    });
};

const deleteCurrentNews = (): void => {
    if (!props.activeNews || !window.confirm(t.value.news.delete_confirm)) {
        return;
    }

    router.delete(destroy.url(props.activeNews.slug), {
        preserveScroll: true,
    });
};

const editorTitle = computed(() => {
    return editorMode.value === 'edit'
        ? t.value.news.editor_edit_title
        : t.value.news.editor_create_title;
});

const activeAuthor = computed(() => {
    const author = props.activeNews?.author;

    if (!author) {
        return null;
    }

    return [author.name, author.last_name].filter(Boolean).join(' ');
});
</script>

<template>
    <Head :title="pageTitle" />

    <Sheet v-model:open="editorOpen">
        <SheetContent class="w-full overflow-y-auto sm:max-w-2xl">
            <SheetHeader>
                <SheetTitle>{{ editorTitle }}</SheetTitle>
                <SheetDescription>{{
                    t.news.editor_description
                }}</SheetDescription>
            </SheetHeader>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label for="news-title">{{ t.news.form_title }}</Label>
                    <Input id="news-title" v-model="form.title" />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="space-y-2">
                    <Label for="news-slug">{{ t.news.form_slug }}</Label>
                    <Input id="news-slug" v-model="form.slug" />
                    <InputError :message="form.errors.slug" />
                </div>

                <div class="space-y-2">
                    <Label for="news-excerpt">{{ t.news.form_excerpt }}</Label>
                    <textarea
                        id="news-excerpt"
                        v-model="form.excerpt"
                        class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    ></textarea>
                    <InputError :message="form.errors.excerpt" />
                </div>

                <div class="space-y-2">
                    <Label for="news-content">{{ t.news.form_content }}</Label>
                    <textarea
                        id="news-content"
                        v-model="form.content"
                        class="flex min-h-56 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    ></textarea>
                    <InputError :message="form.errors.content" />
                </div>

                <div class="space-y-2">
                    <Label for="news-image">{{ t.news.form_image }}</Label>
                    <input
                        id="news-image"
                        type="file"
                        accept="image/*"
                        class="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-foreground"
                        @change="
                            form.image_file =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                    />
                    <InputError :message="form.errors.image_file" />
                </div>

                <label
                    class="flex items-center gap-3 rounded-2xl border border-border px-4 py-3 text-sm"
                >
                    <input
                        v-model="form.remove_image"
                        type="checkbox"
                        class="size-4"
                    />
                    <span>{{ t.news.form_remove_image }}</span>
                </label>

                <label
                    class="flex items-center gap-3 rounded-2xl border border-border px-4 py-3 text-sm"
                >
                    <input
                        v-model="form.is_published"
                        type="checkbox"
                        class="size-4"
                    />
                    <span>{{ t.news.form_publish }}</span>
                </label>

                <div class="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="editorOpen = false"
                    >
                        {{ t.common.cancel }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t.common.save }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <div class="space-y-6">
        <section class="space-y-6">
            <div class="rounded-3xl border border-border bg-card p-6">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="space-y-3">
                        <div
                            class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                        >
                            <CalendarDays class="size-4" />
                            {{ t.news.hero_eyebrow }}
                        </div>
                        <div class="space-y-2">
                            <h1 class="text-2xl font-semibold tracking-tight">
                                {{
                                    props.activeNews
                                        ? props.activeNews.title
                                        : t.news.hero_title
                                }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    props.activeNews?.excerpt ??
                                    t.news.hero_description
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="props.can.manage && !props.activeNews"
                            @click="openCreate"
                        >
                            <FilePlus2 class="mr-2 size-4" />
                            {{ t.news.create_button }}
                        </Button>
                        <template v-if="props.activeNews">
                            <span
                                class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                            >
                                {{
                                    props.activeNews.is_published
                                        ? t.news.published_label
                                        : t.news.draft_label
                                }}
                            </span>
                            <Button as-child variant="outline">
                                <Link :href="newsIndex()">
                                    {{ t.news.back_to_feed }}
                                </Link>
                            </Button>
                            <Button
                                v-if="props.can.manage"
                                variant="outline"
                                @click="openEdit"
                            >
                                <PencilLine class="mr-2 size-4" />
                                {{ t.news.edit_button }}
                            </Button>
                            <Button
                                v-if="props.can.manage"
                                variant="destructive"
                                @click="deleteCurrentNews"
                            >
                                <Trash2 class="mr-2 size-4" />
                                {{ t.news.delete_button }}
                            </Button>
                        </template>
                    </div>
                </div>
            </div>

            <div
                v-if="props.activeNews"
                class="overflow-hidden rounded-3xl border border-border bg-card"
            >
                <img
                    v-if="props.activeNews.image_url"
                    :src="props.activeNews.image_url"
                    :alt="props.activeNews.title"
                    class="h-72 w-full object-cover"
                />
                <div
                    v-else
                    class="flex h-72 items-center justify-center bg-linear-to-br from-primary/15 via-muted to-background text-primary"
                >
                    <Newspaper class="size-12" />
                </div>

                <div class="space-y-6 p-6">
                    <div
                        class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground"
                    >
                        <span>{{
                            formatDate(
                                props.activeNews.published_at ??
                                    props.activeNews.updated_at,
                            )
                        }}</span>
                        <span v-if="activeAuthor">
                            {{ t.news.author_prefix }}: {{ activeAuthor }}
                        </span>
                    </div>

                    <div
                        v-if="props.activeNews.excerpt"
                        class="rounded-2xl border border-border bg-muted/30 p-4 text-sm leading-6 text-muted-foreground"
                    >
                        {{ props.activeNews.excerpt }}
                    </div>

                    <div class="space-y-4 text-sm leading-7 text-foreground/90">
                        <p
                            v-for="(
                                paragraph, index
                            ) in props.activeNews.content
                                .split(/\n{2,}/)
                                .filter((item) => item.trim() !== '')"
                            :key="`${props.activeNews.slug}-${index}`"
                            class="whitespace-pre-line"
                        >
                            {{ paragraph }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-else-if="feedHasItems" class="space-y-4">
                <Heading
                    variant="small"
                    :title="t.news.latest_updates"
                    :description="t.news.latest_updates_description"
                />

                <div class="grid gap-5 md:grid-cols-2">
                    <Link
                        v-for="item in props.newsItems"
                        :key="item.slug"
                        :href="showNews(item.slug)"
                        class="overflow-hidden rounded-3xl border border-border bg-card transition hover:-translate-y-0.5 hover:border-primary/50"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.title"
                            class="h-48 w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-48 items-center justify-center bg-linear-to-br from-primary/15 via-muted to-background text-primary"
                        >
                            <Newspaper class="size-10" />
                        </div>

                        <div class="space-y-3 p-5">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        formatDate(
                                            item.published_at ??
                                                item.updated_at,
                                        )
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                >
                                    {{
                                        item.is_published
                                            ? t.news.published_label
                                            : t.news.draft_label
                                    }}
                                </span>
                            </div>

                            <h2 class="text-lg leading-6 font-semibold">
                                {{ item.title }}
                            </h2>
                            <p
                                class="line-clamp-3 text-sm leading-6 text-muted-foreground"
                            >
                                {{ item.excerpt }}
                            </p>
                            <div class="text-sm font-medium text-primary">
                                {{ t.news.open_article }}
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <div
                v-else
                class="rounded-3xl border border-dashed border-border bg-card px-6 py-12 text-center"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <Newspaper class="size-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold">
                    {{ t.news.empty_title }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                >
                    {{ t.news.empty_description }}
                </p>
                <Button
                    v-if="props.can.manage"
                    class="mt-6"
                    @click="openCreate"
                >
                    {{ t.news.create_button }}
                </Button>
            </div>
        </section>
    </div>
</template>
