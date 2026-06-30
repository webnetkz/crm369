<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, FolderOpen, Trash2 } from '@lucide/vue';
import { index as filesIndex } from '@/routes/files';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import type { FileTreeDirectory } from '@/types/ui';

const props = defineProps<{
    item: FileTreeDirectory;
    activeDirectoryId: number | null;
}>();

const emit = defineEmits<{
    (event: 'delete', directoryId: number): void;
}>();

const isActive = (directoryId: number): boolean => directoryId === props.activeDirectoryId;

const hasActiveDescendant = (directory: FileTreeDirectory): boolean => {
    return directory.children.some((child) => {
        return child.id === props.activeDirectoryId || hasActiveDescendant(child);
    });
};
</script>

<template>
    <Collapsible
        as-child
        :default-open="isActive(props.item.id) || hasActiveDescendant(props.item)"
        class="group/file-directory"
    >
        <div class="space-y-1.5">
            <div class="flex items-start gap-2 rounded-2xl border border-transparent px-2 py-2 transition hover:border-border hover:bg-background/80">
                <CollapsibleTrigger
                    v-if="props.item.children.length > 0"
                    class="mt-0.5 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                >
                    <ChevronRight class="size-4 transition-transform group-data-[state=open]/file-directory:rotate-90" />
                </CollapsibleTrigger>
                <div v-else class="w-6 shrink-0"></div>

                <Link
                    :href="filesIndex({ query: { directory: props.item.id } })"
                    class="min-w-0 flex-1 rounded-xl px-2 py-1.5 text-left transition"
                    :class="
                        isActive(props.item.id)
                            ? 'bg-primary/10 text-primary'
                            : 'hover:bg-muted/60'
                    "
                >
                    <div class="flex items-center gap-2">
                        <FolderOpen class="size-4 shrink-0" />
                        <div class="truncate text-sm font-medium">
                            {{ props.item.name }}
                        </div>
                    </div>
                    <div class="mt-1 truncate text-xs text-muted-foreground">
                        {{ props.item.files_count }} / {{ props.item.children_count }}
                    </div>
                </Link>

                <Button
                    v-if="props.item.can_edit"
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="mt-1"
                    @click.stop="emit('delete', props.item.id)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>

            <CollapsibleContent v-if="props.item.children.length > 0" class="ml-6 space-y-1 border-l border-border pl-3">
                <FileTreeItem
                    v-for="child in props.item.children"
                    :key="child.id"
                    :item="child"
                    :active-directory-id="props.activeDirectoryId"
                    @delete="emit('delete', $event)"
                />
            </CollapsibleContent>
        </div>
    </Collapsible>
</template>
