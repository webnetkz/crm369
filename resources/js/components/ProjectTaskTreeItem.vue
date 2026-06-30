<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    ClipboardList,
    FolderKanban,
    GitBranchPlus,
} from '@lucide/vue';
import { computed } from 'vue';
import { showWorkspaceTask } from '@/actions/App/Http/Controllers/ProjectController';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';
import type {
    ProjectTaskListItem,
    ProjectTaskOption,
    ProjectTaskStageOption,
} from '@/types/ui';

defineOptions({
    name: 'ProjectTaskTreeItem',
});

type Props = {
    task: ProjectTaskListItem;
    activeTaskId: number | null;
    taskOptions: {
        statuses: ProjectTaskStageOption[];
        importances: ProjectTaskOption[];
    };
    level?: number;
    canCreateSubtasks?: boolean;
    taskHrefResolver?: (
        task: ProjectTaskListItem,
    ) => NonNullable<InertiaLinkProps['href']>;
};

const props = withDefaults(defineProps<Props>(), {
    level: 0,
    canCreateSubtasks: false,
});

const emit = defineEmits<{
    (event: 'create-subtask', task: ProjectTaskListItem): void;
}>();

const { t } = useLanguage();

const taskHref = computed(() => {
    return props.taskHrefResolver
        ? props.taskHrefResolver(props.task)
        : showWorkspaceTask(props.task.id);
});

const indentStyle = computed<Record<string, string>>(() => ({
    marginLeft: `${Math.min(props.level, 5) * 1.25}rem`,
}));

const stageBadgeStyle = (status: string): Record<string, string> => {
    const color = props.taskOptions.statuses.find((option) => option.value === status)?.color;

    if (!color) {
        return {};
    }

    return {
        backgroundColor: `${color}1A`,
        borderColor: `${color}33`,
        color,
    };
};

const importanceClass = (importance: string): string => {
    return {
        low: 'bg-muted text-muted-foreground',
        normal: 'bg-primary/10 text-primary',
        high: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
        critical: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    }[importance] ?? 'bg-muted text-muted-foreground';
};

const optionLabel = (options: ProjectTaskOption[], value: string): string => {
    return options.find((option) => option.value === value)?.label ?? value;
};

const handleCreateSubtask = (): void => {
    emit('create-subtask', props.task);
};

const visitTask = (): void => {
    router.visit(taskHref.value, {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <div class="space-y-3" :style="indentStyle">
        <div
            class="group cursor-pointer rounded-2xl border px-4 py-4 transition"
            :class="
                activeTaskId === task.id
                    ? 'border-primary/50 bg-background'
                    : 'border-border bg-card hover:border-primary/40 hover:bg-background'
            "
            role="link"
            tabindex="0"
            @click="visitTask"
            @keydown.enter.prevent="visitTask"
            @keydown.space.prevent="visitTask"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="size-4 text-muted-foreground" />
                        <div class="truncate font-medium group-hover:underline">
                            {{ task.title }}
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-full border px-2 py-1 font-medium"
                            :style="stageBadgeStyle(task.status)"
                        >
                            {{ optionLabel(taskOptions.statuses, task.status) }}
                        </span>
                        <span
                            class="rounded-full px-2 py-1 font-medium"
                            :class="importanceClass(task.importance)"
                        >
                            {{ optionLabel(taskOptions.importances, task.importance) }}
                        </span>
                        <span class="rounded-full bg-background px-2 py-1 text-muted-foreground">
                            {{ t.projects.complexity }}: {{ task.complexity }}
                        </span>
                        <span
                            v-if="task.subtasks_count > 0"
                            class="rounded-full bg-background px-2 py-1 text-muted-foreground"
                        >
                            {{ t.projects.subtasks }}: {{ task.subtasks_count }}
                        </span>
                        <span
                            v-if="task.parent_task_title"
                            class="rounded-full bg-background px-2 py-1 text-muted-foreground"
                        >
                            {{ t.projects.parent_task }}: {{ task.parent_task_title }}
                        </span>
                        <span
                            v-if="task.project_name"
                            class="rounded-full bg-background px-2 py-1 text-muted-foreground"
                        >
                            <FolderKanban class="mr-1 inline size-3" />
                            {{ task.project_name }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-end gap-2 text-right text-xs text-muted-foreground">
                    <div>{{ t.projects.assignee }}: {{ task.assignee?.name ?? t.projects.unassigned }}</div>

                    <Button
                        v-if="canCreateSubtasks"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 px-2 text-xs"
                        @click.stop="handleCreateSubtask"
                        @keydown.enter.stop
                        @keydown.space.stop
                    >
                        <GitBranchPlus class="size-3.5" />
                        {{ t.projects.create_subtask }}
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="task.subtasks.length > 0" class="space-y-3 border-l border-border/70 pl-3">
            <ProjectTaskTreeItem
                v-for="subtask in task.subtasks"
                :key="subtask.id"
                :task="subtask"
                :active-task-id="activeTaskId"
                :task-options="taskOptions"
                :level="level + 1"
                :can-create-subtasks="canCreateSubtasks"
                :task-href-resolver="taskHrefResolver"
                @create-subtask="emit('create-subtask', $event)"
            />
        </div>
    </div>
</template>
