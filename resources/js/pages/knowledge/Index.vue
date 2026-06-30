<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    BookOpenText,
    FilePlus2,
    LibraryBig,
    PencilLine,
    Plus,
    Save,
    Shield,
    Trash2,
} from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import {
    article as showKnowledgeBaseArticle,
    destroy as destroyKnowledgeBase,
    destroyArticle as destroyKnowledgeBaseArticle,
    index as knowledgeBaseIndex,
    show as showKnowledgeBase,
    store as storeKnowledgeBase,
    storeArticle,
    update as updateKnowledgeBase,
    updateArticle,
} from '@/actions/App/Http/Controllers/KnowledgeBaseController';
import InputError from '@/components/InputError.vue';
import KnowledgeArticleBlocksEditor from '@/components/knowledge/KnowledgeArticleBlocksEditor.vue';
import KnowledgeArticleBlocksRenderer from '@/components/knowledge/KnowledgeArticleBlocksRenderer.vue';
import KnowledgeTreeItem from '@/components/knowledge/KnowledgeTreeItem.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type {
    KnowledgeBaseActiveArticle,
    KnowledgeBaseActiveBase,
    KnowledgeBaseBlock,
    KnowledgeBaseGroup,
    KnowledgeBaseListItem,
    KnowledgeBaseTreeArticle,
} from '@/types/ui';

type Props = {
    bases: KnowledgeBaseListItem[];
    activeBase: KnowledgeBaseActiveBase | null;
    activeArticle: KnowledgeBaseActiveArticle | null;
    can: {
        manage: boolean;
    };
    groups: KnowledgeBaseGroup[];
};

const props = defineProps<Props>();
const page = usePage();
const { t } = useLanguage();

const cloneBlocks = (blocks: KnowledgeBaseBlock[]): KnowledgeBaseBlock[] => {
    return blocks.map((block) => ({
        ...block,
        items: block.items ? [...block.items] : [],
        image_file: null,
    }));
};

const defaultBlocks = (): KnowledgeBaseBlock[] => [
    {
        type: 'paragraph',
        content: '',
    },
];

const baseEditorMode = useForm({
    value: 'idle' as 'idle' | 'create' | 'edit',
});
const articleEditorMode = useForm({
    value: 'idle' as 'idle' | 'create' | 'edit',
});

const baseForm = useForm({
    title: '',
    slug: '',
    description: '',
    is_published: true,
    user_group_ids: [] as number[],
});

const articleForm = useForm({
    parent_id: null as number | null,
    title: '',
    slug: '',
    excerpt: '',
    sort_order: 0,
    is_published: true,
    blocks: defaultBlocks(),
});

const flattenedArticles = computed(() => {
    const flatten = (
        articles: KnowledgeBaseTreeArticle[],
        prefix = '',
    ): Array<{ id: number; title: string }> => {
        return articles.flatMap((article, index) => {
            const marker =
                prefix === '' ? `${index + 1}.` : `${prefix}${index + 1}.`;

            return [
                {
                    id: article.id,
                    title: `${marker} ${article.title}`,
                },
                ...flatten(article.children, `${marker}`),
            ];
        });
    };

    return props.activeBase ? flatten(props.activeBase.articles) : [];
});

const isKnowledgeBaseIndexPage = computed(() => {
    return page.url === knowledgeBaseIndex().url;
});

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.knowledge.title,
            href: knowledgeBaseIndex(),
        },
    ];

    if (props.activeBase) {
        breadcrumbs.push({
            title: props.activeBase.title,
            href: showKnowledgeBase(props.activeBase.id),
        });
    }

    if (props.activeArticle && props.activeBase) {
        breadcrumbs.push({
            title: props.activeArticle.title,
            href: showKnowledgeBaseArticle({
                knowledgeBase: props.activeBase.id,
                knowledgeBaseArticle: props.activeArticle.id,
            }),
        });
    }

    setLayoutProps({ breadcrumbs });
});

const resetBaseForm = (): void => {
    baseForm.reset();
    baseForm.is_published = true;
    baseForm.user_group_ids = [];
    baseForm.clearErrors();
    baseEditorMode.value = 'idle';
};

const openCreateBase = (): void => {
    resetBaseForm();
    baseEditorMode.value = 'create';
};

const openEditBase = (): void => {
    if (!props.activeBase) {
        return;
    }

    baseForm.title = props.activeBase.title;
    baseForm.slug = props.activeBase.slug;
    baseForm.description = props.activeBase.description ?? '';
    baseForm.is_published = props.activeBase.is_published;
    baseForm.user_group_ids = props.activeBase.groups.map((group) => group.id);
    baseForm.clearErrors();
    baseEditorMode.value = 'edit';
};

const toggleBaseGroup = (groupId: number, checked: boolean): void => {
    if (checked) {
        baseForm.user_group_ids = [
            ...new Set([...baseForm.user_group_ids, groupId]),
        ];

        return;
    }

    baseForm.user_group_ids = baseForm.user_group_ids.filter(
        (id) => id !== groupId,
    );
};

const submitBase = (): void => {
    if (baseEditorMode.value === 'edit' && props.activeBase) {
        baseForm.patch(updateKnowledgeBase.url(props.activeBase.id), {
            preserveScroll: true,
            onSuccess: () => {
                baseEditorMode.value = 'idle';
            },
        });

        return;
    }

    baseForm.post(storeKnowledgeBase.url(), {
        preserveScroll: true,
        onSuccess: () => {
            resetBaseForm();
        },
    });
};

const deleteBase = (): void => {
    if (
        !props.activeBase ||
        !window.confirm(t.value.knowledge.delete_base_confirm)
    ) {
        return;
    }

    router.delete(destroyKnowledgeBase.url(props.activeBase.id), {
        preserveScroll: true,
    });
};

const resetArticleForm = (): void => {
    articleForm.reset();
    articleForm.parent_id = null;
    articleForm.sort_order = 0;
    articleForm.is_published = true;
    articleForm.blocks = defaultBlocks();
    articleForm.clearErrors();
    articleEditorMode.value = 'idle';
};

const openCreateArticle = (parentId: number | null = null): void => {
    resetArticleForm();
    articleForm.parent_id = parentId;
    articleEditorMode.value = 'create';
};

const openEditArticle = (): void => {
    if (!props.activeArticle) {
        return;
    }

    articleForm.parent_id = props.activeArticle.parent_id;
    articleForm.title = props.activeArticle.title;
    articleForm.slug = props.activeArticle.slug;
    articleForm.excerpt = props.activeArticle.excerpt ?? '';
    articleForm.sort_order = props.activeArticle.sort_order;
    articleForm.is_published = props.activeArticle.is_published;
    articleForm.blocks = cloneBlocks(props.activeArticle.blocks);
    articleForm.clearErrors();
    articleEditorMode.value = 'edit';
};

const submitArticle = (): void => {
    if (!props.activeBase) {
        return;
    }

    if (articleEditorMode.value === 'edit' && props.activeArticle) {
        articleForm.post(
            updateArticle.form.patch({
                knowledgeBase: props.activeBase.id,
                knowledgeBaseArticle: props.activeArticle.id,
            }).action,
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    articleEditorMode.value = 'idle';
                },
            },
        );

        return;
    }

    articleForm.post(storeArticle.url(props.activeBase.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            articleEditorMode.value = 'idle';
        },
    });
};

const deleteArticle = (): void => {
    if (
        !props.activeBase ||
        !props.activeArticle ||
        !window.confirm(t.value.knowledge.delete_article_confirm)
    ) {
        return;
    }

    router.delete(
        destroyKnowledgeBaseArticle.url({
            knowledgeBase: props.activeBase.id,
            knowledgeBaseArticle: props.activeArticle.id,
        }),
        {
            preserveScroll: true,
        },
    );
};

const activeTitle = computed(() => {
    return (
        props.activeArticle?.title ??
        props.activeBase?.title ??
        t.value.knowledge.title
    );
});
</script>

<template>
    <Head :title="activeTitle" />

    <div class="min-h-full px-4 py-6 md:px-6 lg:px-8">
        <div
            class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
        >
            <div class="space-y-1">
                <div
                    class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold tracking-[0.18em] text-primary uppercase"
                >
                    <LibraryBig class="size-3.5" />
                    {{ t.knowledge.title }}
                </div>
                <h1
                    class="text-3xl font-semibold tracking-tight text-foreground"
                >
                    {{ t.knowledge.title }}
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    {{ t.knowledge.description }}
                </p>
            </div>

            <div v-if="props.can.manage" class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" @click="openCreateBase">
                    <Plus class="size-4" />
                    {{ t.knowledge.create_base }}
                </Button>
                <Button
                    v-if="props.activeBase"
                    type="button"
                    @click="openCreateArticle()"
                >
                    <FilePlus2 class="size-4" />
                    {{ t.knowledge.create_article }}
                </Button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="space-y-4">
                <aside
                    v-if="
                        isKnowledgeBaseIndexPage ||
                        (props.can.manage && baseEditorMode.value !== 'idle')
                    "
                    class="space-y-4"
                >
                    <section
                        v-if="isKnowledgeBaseIndexPage"
                        class="rounded-[2rem] border border-border bg-card p-4 shadow-sm"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="text-sm font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    {{ t.knowledge.title }}
                                </h2>
                            </div>
                        </div>

                        <div
                            v-if="props.bases.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            {{ t.knowledge.empty_title }}
                        </div>

                        <div v-else class="space-y-2">
                            <Link
                                v-for="base in props.bases"
                                :key="base.id"
                                :href="showKnowledgeBase(base.id)"
                                class="block rounded-3xl border px-4 py-3 transition hover:border-primary/30 hover:bg-background"
                                :class="
                                    props.activeBase?.id === base.id
                                        ? 'border-primary/35 bg-primary/5'
                                        : 'border-transparent'
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="truncate text-sm font-semibold text-foreground"
                                        >
                                            {{ base.title }}
                                        </div>
                                        <div
                                            class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                                        >
                                            {{ base.description }}
                                        </div>
                                    </div>
                                    <span
                                        class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                    >
                                        {{ base.article_count }}
                                    </span>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section
                        v-if="
                            props.can.manage && baseEditorMode.value !== 'idle'
                        "
                        class="rounded-[2rem] border border-border bg-card p-5 shadow-sm"
                    >
                        <div class="mb-4 flex items-center gap-2">
                            <PencilLine class="size-4 text-primary" />
                            <h2 class="font-semibold text-foreground">
                                {{
                                    baseEditorMode.value === 'edit'
                                        ? t.knowledge.edit_base
                                        : t.knowledge.create_base
                                }}
                            </h2>
                        </div>

                        <form class="space-y-4" @submit.prevent="submitBase">
                            <div class="grid gap-2">
                                <Label for="knowledge_base_title">{{
                                    t.knowledge.base_title
                                }}</Label>
                                <Input
                                    id="knowledge_base_title"
                                    v-model="baseForm.title"
                                />
                                <InputError :message="baseForm.errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="knowledge_base_slug">{{
                                    t.knowledge.base_slug
                                }}</Label>
                                <Input
                                    id="knowledge_base_slug"
                                    v-model="baseForm.slug"
                                />
                                <InputError :message="baseForm.errors.slug" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="knowledge_base_description">{{
                                    t.knowledge.base_description_label
                                }}</Label>
                                <textarea
                                    id="knowledge_base_description"
                                    v-model="baseForm.description"
                                    class="min-h-28 w-full rounded-2xl border border-input bg-transparent px-4 py-3 text-sm transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                ></textarea>
                                <InputError
                                    :message="baseForm.errors.description"
                                />
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="baseForm.is_published"
                                    type="checkbox"
                                    class="size-4 rounded border-input"
                                />
                                {{ t.knowledge.published }}
                            </label>

                            <div class="space-y-3">
                                <div
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ t.knowledge.visible_groups }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ t.knowledge.visible_groups_help }}
                                </p>
                                <div class="grid gap-2">
                                    <label
                                        v-for="group in props.groups"
                                        :key="group.id"
                                        class="flex items-center gap-2 rounded-2xl border border-border px-3 py-2 text-sm"
                                    >
                                        <input
                                            :checked="
                                                baseForm.user_group_ids.includes(
                                                    group.id,
                                                )
                                            "
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                            @change="
                                                toggleBaseGroup(
                                                    group.id,
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                )
                                            "
                                        />
                                        {{ group.display_name }}
                                    </label>
                                </div>
                                <InputError
                                    :message="baseForm.errors.user_group_ids"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    :disabled="baseForm.processing"
                                >
                                    <Save class="size-4" />
                                    {{ t.knowledge.save_base }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="resetBaseForm"
                                >
                                    {{ t.common.cancel }}
                                </Button>
                            </div>
                        </form>
                    </section>
                </aside>

                <aside class="space-y-4">
                    <section
                        class="rounded-[2rem] border border-border bg-card p-4 shadow-sm"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <div>
                                <h2
                                    class="text-sm font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    {{ t.knowledge.articles }}
                                </h2>
                                <p
                                    v-if="props.activeBase"
                                    class="text-sm text-foreground"
                                >
                                    {{ props.activeBase.title }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="!props.activeBase"
                            class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            {{ t.knowledge.empty_description }}
                        </div>

                        <div
                            v-else-if="props.activeBase.articles.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            {{ t.knowledge.empty_articles }}
                        </div>

                        <div v-else class="space-y-2">
                            <KnowledgeTreeItem
                                v-for="articleItem in props.activeBase.articles"
                                :key="articleItem.id"
                                :article="articleItem"
                                :base-id="props.activeBase.id"
                                :active-article-id="
                                    props.activeArticle?.id ?? null
                                "
                                :can-manage="props.can.manage"
                                @create-child="openCreateArticle"
                            />
                        </div>
                    </section>

                    <section
                        v-if="!props.can.manage"
                        class="rounded-[2rem] border border-border bg-card p-5 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <Shield class="mt-0.5 size-5 text-primary" />
                            <p class="text-sm text-muted-foreground">
                                {{ t.knowledge.manage_hint }}
                            </p>
                        </div>
                    </section>
                </aside>
            </div>

            <section class="space-y-4">
                <article
                    class="rounded-[2rem] border border-border bg-card p-6 shadow-sm"
                >
                    <div
                        class="mb-6 flex flex-col gap-4 border-b border-border pb-6 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-2">
                            <div
                                class="inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                            >
                                <BookOpenText class="size-3.5" />
                                {{
                                    props.activeArticle
                                        ? t.knowledge.articles
                                        : t.knowledge.title
                                }}
                            </div>
                            <h2
                                class="text-2xl font-semibold tracking-tight text-foreground"
                            >
                                {{ activeTitle }}
                            </h2>
                            <p
                                v-if="props.activeArticle?.excerpt"
                                class="max-w-3xl text-sm text-muted-foreground"
                            >
                                {{ props.activeArticle.excerpt }}
                            </p>
                            <p
                                v-else-if="
                                    props.activeBase?.description &&
                                    !props.activeArticle
                                "
                                class="max-w-3xl text-sm text-muted-foreground"
                            >
                                {{ props.activeBase.description }}
                            </p>
                        </div>

                        <div
                            v-if="props.can.manage && props.activeBase"
                            class="flex flex-wrap gap-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                @click="openEditBase"
                            >
                                <PencilLine class="size-4" />
                                {{ t.knowledge.edit_base }}
                            </Button>
                            <Button
                                v-if="props.activeArticle"
                                type="button"
                                variant="outline"
                                @click="openEditArticle"
                            >
                                <PencilLine class="size-4" />
                                {{ t.knowledge.edit_article }}
                            </Button>
                            <Button
                                v-if="props.activeArticle"
                                type="button"
                                variant="outline"
                                @click="deleteArticle"
                            >
                                <Trash2 class="size-4" />
                                {{ t.knowledge.delete_article }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="deleteBase"
                            >
                                <Trash2 class="size-4" />
                                {{ t.knowledge.delete_base }}
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="articleEditorMode.value !== 'idle'"
                        class="space-y-6"
                    >
                        <form class="space-y-6" @submit.prevent="submitArticle">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="knowledge_article_title">{{
                                        t.knowledge.article_title
                                    }}</Label>
                                    <Input
                                        id="knowledge_article_title"
                                        v-model="articleForm.title"
                                    />
                                    <InputError
                                        :message="articleForm.errors.title"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="knowledge_article_slug">{{
                                        t.knowledge.article_slug
                                    }}</Label>
                                    <Input
                                        id="knowledge_article_slug"
                                        v-model="articleForm.slug"
                                    />
                                    <InputError
                                        :message="articleForm.errors.slug"
                                    />
                                </div>
                            </div>

                            <div
                                class="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px_200px]"
                            >
                                <div class="grid gap-2">
                                    <Label for="knowledge_article_parent">{{
                                        t.knowledge.article_parent
                                    }}</Label>
                                    <select
                                        id="knowledge_article_parent"
                                        v-model="articleForm.parent_id"
                                        class="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option :value="null">
                                            {{ t.knowledge.article_root }}
                                        </option>
                                        <option
                                            v-for="articleItem in flattenedArticles"
                                            :key="articleItem.id"
                                            :value="articleItem.id"
                                        >
                                            {{ articleItem.title }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="articleForm.errors.parent_id"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="knowledge_article_sort_order">{{
                                        t.knowledge.sort_order
                                    }}</Label>
                                    <Input
                                        id="knowledge_article_sort_order"
                                        v-model="articleForm.sort_order"
                                        type="number"
                                        min="0"
                                    />
                                    <InputError
                                        :message="articleForm.errors.sort_order"
                                    />
                                </div>

                                <label
                                    class="flex items-center gap-2 pt-8 text-sm"
                                >
                                    <input
                                        v-model="articleForm.is_published"
                                        type="checkbox"
                                        class="size-4 rounded border-input"
                                    />
                                    {{ t.knowledge.published }}
                                </label>
                            </div>

                            <div class="grid gap-2">
                                <Label for="knowledge_article_excerpt">{{
                                    t.knowledge.article_excerpt
                                }}</Label>
                                <textarea
                                    id="knowledge_article_excerpt"
                                    v-model="articleForm.excerpt"
                                    class="min-h-24 w-full rounded-2xl border border-input bg-transparent px-4 py-3 text-sm transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                ></textarea>
                                <InputError
                                    :message="articleForm.errors.excerpt"
                                />
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="text-sm font-semibold text-foreground"
                                >
                                    {{ t.knowledge.content_blocks }}
                                </div>
                                <KnowledgeArticleBlocksEditor
                                    v-model="articleForm.blocks"
                                    :errors="articleForm.errors"
                                />
                                <InputError
                                    :message="articleForm.errors.blocks"
                                />
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    :disabled="articleForm.processing"
                                >
                                    <Save class="size-4" />
                                    {{ t.knowledge.save_article }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="resetArticleForm"
                                >
                                    {{ t.common.cancel }}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div v-else-if="props.activeArticle" class="space-y-8">
                        <KnowledgeArticleBlocksRenderer
                            :blocks="props.activeArticle.blocks"
                        />
                    </div>

                    <div
                        v-else
                        class="rounded-[2rem] border border-dashed border-border bg-muted/20 p-8 text-center"
                    >
                        <div
                            class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-background shadow-sm"
                        >
                            <BookOpenText
                                class="size-6 text-muted-foreground"
                            />
                        </div>
                        <h3 class="text-lg font-semibold text-foreground">
                            {{ t.knowledge.empty_article_title }}
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ t.knowledge.empty_article_description }}
                        </p>
                    </div>
                </article>
            </section>
        </div>
    </div>
</template>
