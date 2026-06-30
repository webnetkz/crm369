<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Plus } from '@lucide/vue';
import { article as showKnowledgeBaseArticle } from '@/actions/App/Http/Controllers/KnowledgeBaseController';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { KnowledgeBaseTreeArticle } from '@/types/ui';

const props = defineProps<{
    article: KnowledgeBaseTreeArticle;
    baseId: number;
    activeArticleId: number | null;
    canManage: boolean;
}>();

const emit = defineEmits<{
    (event: 'create-child', parentId: number): void;
}>();

const isActive = (articleId: number): boolean =>
    articleId === props.activeArticleId;
</script>

<template>
    <Collapsible
        as-child
        :default-open="
            props.article.id === props.activeArticleId ||
            props.article.children.some(
                (child) => child.id === props.activeArticleId,
            )
        "
        class="group/article"
    >
        <div class="space-y-0.5">
            <div
                class="flex items-center gap-1.5 rounded-xl border border-transparent px-1.5 py-1 transition hover:border-border/70 hover:bg-background/80"
            >
                <CollapsibleTrigger
                    v-if="props.article.children.length > 0"
                    class="rounded-md p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                >
                    <ChevronRight
                        class="size-3.5 transition-transform group-data-[state=open]/article:rotate-90"
                    />
                </CollapsibleTrigger>
                <div v-else class="w-4 shrink-0"></div>

                <Link
                    :href="
                        showKnowledgeBaseArticle({
                            knowledgeBase: props.baseId,
                            knowledgeBaseArticle: props.article.id,
                        }).url
                    "
                    class="min-w-0 flex-1 rounded-lg px-2 py-1 text-left text-sm leading-5 transition"
                    :class="
                        isActive(props.article.id)
                            ? 'bg-primary/10 text-primary'
                            : 'hover:bg-muted/60'
                    "
                >
                    <div class="truncate font-medium">
                        {{ props.article.title }}
                    </div>
                </Link>

                <Button
                    v-if="props.canManage"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="size-7"
                    @click="emit('create-child', props.article.id)"
                >
                    <Plus class="size-3.5" />
                </Button>
            </div>

            <CollapsibleContent
                v-if="props.article.children.length > 0"
                class="ml-4 space-y-0.5 border-l border-border/70 pl-2"
            >
                <KnowledgeTreeItem
                    v-for="child in props.article.children"
                    :key="child.id"
                    :article="child"
                    :base-id="props.baseId"
                    :active-article-id="props.activeArticleId"
                    :can-manage="props.canManage"
                    @create-child="emit('create-child', $event)"
                />
            </CollapsibleContent>
        </div>
    </Collapsible>
</template>
