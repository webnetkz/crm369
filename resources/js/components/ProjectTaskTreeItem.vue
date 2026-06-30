<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ClipboardList,
    FolderKanban,
    GitBranchPlus,
} from '@lucide/vue';
import { computed } from 'vue';
import { showWorkspaceTask } from '@/actions/App/Http/Controllers/ProjectController';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';
import type { ProjectTaskListItem, ProjectTaskOption } from '@/types/ui';

defineOptions({
    name: 'ProjectTaskTreeItem',
});

type Props = {
    task: ProjectTaskListItem;
    activeTaskId: number | null;
    taskOptions: {
        statuses: ProjectTaskOption[];
        importances: ProjectTaskOption[];
    };
    level?: number;
    canCreateSubtasks?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    level: 0,
    canCreateSubtasks: false,
});

const emit = defineEmits<{
    (event: 'create-subtask', task: ProjectTaskListItem): void;
}>();

const { t } = useLanguage();

const taskHref = computed(() => showWorkspaceTask.url(props.task.id));

const indentStyle = computed<Record<string, string>>(() => ({
    marginLeft: `${Math.min(props.level, 5) * 1.25}rem`,
}));

const statusClass = (status: string): string => {
    return {
        todo: 'bg-slate-500/10 text-slate-700 dark:text-slate-300',
        in_progress: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
        review: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        done: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    }[status] ?? 'bg-muted text-muted-foreground';
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
</script>

<template>
    <div class="space-y-3" :style="indentStyle">
        <div
            class="rounded-2xl border px-4 py-4 transition"
            :class="
                activeTaskId === task.id
                    ? 'border-primary/50 bg-background'
                    : 'border-border bg-card hover:border-primary/40 hover:bg-background'
            "
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex items-center gap-2">
                        <ClipboardList class="size-4 text-muted-foreground" />
                        <Link :href="taskHref" class="truncate font-medium hover:underline">
                            {{ task.title }}
                        </Link>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span
                            class="rounded-full px-2 py-1 font-medium"
                            :class="statusClass(task.status)"
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
                        @click="handleCreateSubtask"
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
                @create-subtask="emit('create-subtask', $event)"
            />
        </div>
    </div>
</template>
