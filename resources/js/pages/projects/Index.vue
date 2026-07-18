<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    CalendarClock,
    ClipboardList,
    Download,
    FolderKanban,
    GitBranchPlus,
    GripVertical,
    Layers3,
    PencilLine,
    Plus,
    Save,
    Settings2,
    Shield,
    Trash2,
    Upload,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import {
    downloadProjectTasksTemplate,
    downloadStandaloneTasksTemplate,
    destroy as destroyProject,
    destroyWorkspaceTask,
    exportProjectTasks,
    exportStandaloneTasks,
    importProjectTasks,
    importStandaloneTasks,
    moveWorkspaceTask,
    moveTaskStages,
    show,
    showWorkspaceTask,
    store,
    storeWorkspaceTask,
    storeTaskStage,
    update,
    updateTaskStage,
    updateWorkspaceTask,
} from '@/actions/App/Http/Controllers/ProjectController';
import CsvExchangeSheet from '@/components/CsvExchangeSheet.vue';
import InputError from '@/components/InputError.vue';
import ProjectTaskConversationPanel from '@/components/ProjectTaskConversationPanel.vue';
import ProjectTaskTreeItem from '@/components/ProjectTaskTreeItem.vue';
import TaskUserPicker from '@/components/TaskUserPicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';
import { index as projectsIndex } from '@/routes/projects';
import { index as tasksIndex, show as tasksShow } from '@/routes/tasks';
import type {
    ProjectActiveProject,
    ProjectActiveTask,
    ProjectPageMode,
    ProjectTaskDisplayMode,
    ProjectListItem,
    ProjectOption,
    ProjectTaskGroup,
    ProjectTaskListItem,
    ProjectTaskOption,
    ProjectTaskStageOption,
    ProjectUserSummary,
} from '@/types/ui';

type ParentTaskOption = {
    id: number;
    label: string;
};

type FlattenedTaskItem = ProjectTaskListItem & {
    level: number;
};

type TaskFormData = {
    project_id: number | string;
    parent_task_id: number | string;
    title: string;
    description: string;
    status: string;
    importance: string;
    complexity: number;
    due_at: string;
    sort_order: number;
    assignee_user_id: number | string;
    co_assignee_user_ids: number[];
};

type TaskSaveState = 'idle' | 'saving' | 'saved' | 'error';
type TaskCsvImportContext = number | 'standalone' | null;
type CsvPanelMode = 'import' | 'export';

type Props = {
    pageMode: ProjectPageMode;
    taskDisplayMode: ProjectTaskDisplayMode;
    projects: ProjectListItem[];
    taskGroups: ProjectTaskGroup[];
    activeProject: ProjectActiveProject | null;
    activeTask: ProjectActiveTask | null;
    availableUsers: ProjectUserSummary[];
    availableProjects: ProjectOption[];
    can: {
        createProject: boolean;
        createTask: boolean;
        manageTaskStages: boolean;
        manageProject: boolean;
        manageTask: boolean;
        workOnActiveProject: boolean;
    };
    taskOptions: {
        statuses: ProjectTaskStageOption[];
        importances: ProjectTaskOption[];
        complexity: number[];
    };
    workspaceSummary: {
        standalone_tasks_count: number;
        standalone_open_tasks_count: number;
        standalone_completed_tasks_count: number;
    };
};

const props = defineProps<Props>();
const { language, t } = useLanguage();
const isTasksPage = computed(() => props.pageMode === 'tasks');

const taskIndexRoute = (
    view: ProjectTaskDisplayMode = props.taskDisplayMode,
) => {
    return tasksIndex({
        query: {
            view,
        },
    });
};

const taskRoute = (
    taskId: number,
    view: ProjectTaskDisplayMode = props.taskDisplayMode,
): NonNullable<InertiaLinkProps['href']> => {
    return tasksShow(taskId, {
        query: {
            view,
        },
    });
};

const taskHrefResolver = (
    task: ProjectTaskListItem,
): NonNullable<InertiaLinkProps['href']> => {
    return isTasksPage.value ? taskRoute(task.id) : showWorkspaceTask(task.id);
};

const projectEditorMode = ref<'idle' | 'create' | 'edit'>('idle');
const taskEditorMode = ref<'idle' | 'create' | 'edit'>('idle');
const taskStageSheetMode = ref<'list' | 'create' | 'edit'>('list');
const taskStageSheetOpen = ref(false);
const draggedTaskId = ref<number | null>(null);
const draggedStageId = ref<number | null>(null);
const dragOverTaskStatus = ref<string | null>(null);
const dragOverStageId = ref<number | null>(null);
const movingTaskId = ref<number | null>(null);
const editingTaskStageId = ref<number | null>(null);
const activeTaskSaveState = ref<TaskSaveState>('idle');
const isSyncingActiveTask = ref(false);
const savedTaskStateResetDelay = 1400;
let activeTaskSaveTimeout: ReturnType<typeof setTimeout> | null = null;

const projectForm = useForm({
    name: '',
    slug: '',
    description: '',
    is_archived: false,
    member_user_ids: [] as number[],
});

const defaultTaskForm = (): TaskFormData => ({
    project_id: '',
    parent_task_id: '',
    title: '',
    description: '',
    status: 'todo',
    importance: 'normal',
    complexity: 5,
    due_at: '',
    sort_order: 0,
    assignee_user_id: '',
    co_assignee_user_ids: [],
});

const taskForm = useForm<TaskFormData>(defaultTaskForm());
const activeTaskForm = useForm<TaskFormData>(defaultTaskForm());

const taskStageForm = useForm({
    name: '',
    color: '#64748B',
});
const taskCsvImportForm = useForm({
    delimiter: ';',
    file: null as File | null,
});

const activeProjectOwnerId = computed(
    () => props.activeProject?.owner?.id ?? null,
);
const taskCsvImportContext = ref<TaskCsvImportContext>(null);
const taskCsvPanelMode = ref<CsvPanelMode | null>(null);

watchEffect(() => {
    const breadcrumbs: Array<{
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
    }> = [
        {
            title: isTasksPage.value
                ? t.value.projects.tasks
                : t.value.projects.title,
            href: isTasksPage.value ? taskIndexRoute() : projectsIndex(),
        },
    ];

    if (props.activeProject) {
        breadcrumbs.push({
            title: props.activeProject.name,
            href: show(props.activeProject.id),
        });
    }

    if (props.activeTask) {
        breadcrumbs.push({
            title: props.activeTask.title,
            href: isTasksPage.value
                ? taskRoute(props.activeTask.id)
                : showWorkspaceTask(props.activeTask.id),
        });
    }

    setLayoutProps({ breadcrumbs });
});

const fullName = (user: ProjectUserSummary | null): string => {
    if (!user) {
        return t.value.common.not_specified;
    }

    return [user.name, user.last_name].filter(Boolean).join(' ');
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.common.not_specified;
    }

    const locale = language.value === 'ru' ? 'ru-RU' : 'en-US';

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const toDateTimeLocalValue = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
        date.getDate(),
    ).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(
        date.getMinutes(),
    ).padStart(2, '0')}`;
};

const normalizeDueAtForSubmission = (value: string): string | null => {
    const normalizedValue = value.trim();

    if (normalizedValue === '') {
        return null;
    }

    return new Date(normalizedValue).toISOString();
};

const projectStatusClass = (project: ProjectListItem): string => {
    return project.is_archived
        ? 'bg-muted text-muted-foreground'
        : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};

const findTaskStage = (status: string): ProjectTaskStageOption | null => {
    return (
        props.taskOptions.statuses.find((option) => option.value === status) ??
        null
    );
};

const taskStageBadgeStyle = (status: string): Record<string, string> => {
    const color = findTaskStage(status)?.color;

    if (!color) {
        return {};
    }

    return {
        backgroundColor: `${color}1A`,
        borderColor: `${color}33`,
        color,
    };
};

const taskStageBarStyle = (status: string): Record<string, string> => {
    const color = findTaskStage(status)?.color;

    if (!color) {
        return {};
    }

    return {
        backgroundColor: color,
        color: '#FFFFFF',
    };
};

const taskStageColumnStyle = (status: string): Record<string, string> => {
    const color = findTaskStage(status)?.color;

    if (!color) {
        return {};
    }

    return {
        borderTopColor: color,
        borderTopWidth: '5px',
    };
};

const importanceClass = (importance: string): string => {
    return (
        {
            low: 'bg-muted text-muted-foreground',
            normal: 'bg-primary/10 text-primary',
            high: 'bg-orange-500/10 text-orange-700 dark:text-orange-300',
            critical: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
        }[importance] ?? 'bg-muted text-muted-foreground'
    );
};

const optionLabel = (options: ProjectTaskOption[], value: string): string => {
    return options.find((option) => option.value === value)?.label ?? value;
};

const defaultTaskStatus = (): string => {
    return (
        props.taskOptions.statuses.find((option) => !option.is_completed)
            ?.value ??
        props.taskOptions.statuses[0]?.value ??
        'todo'
    );
};

const selectedTaskProjectId = computed<number | null>(() => {
    return taskForm.project_id === '' ? null : Number(taskForm.project_id);
});

const selectedActiveTaskProjectId = computed<number | null>(() => {
    return activeTaskForm.project_id === ''
        ? null
        : Number(activeTaskForm.project_id);
});

const taskSheetOpen = computed<boolean>(() => {
    return taskEditorMode.value !== 'idle' || props.activeTask !== null;
});

const taskSheetBaseRoute = computed(() => {
    if (isTasksPage.value) {
        return taskIndexRoute();
    }

    return props.activeProject ? show(props.activeProject.id) : projectsIndex();
});

const standaloneTaskGroup = computed(() => {
    return (
        props.taskGroups.find((group) => group.kind === 'standalone') ?? null
    );
});

const taskStages = computed(() => props.taskOptions.statuses);
const flattenedStandaloneTasks = computed<FlattenedTaskItem[]>(() => {
    return flattenTaskTree(standaloneTaskGroup.value?.tasks ?? []);
});

const taskViewOptions = computed(() => {
    return [
        {
            value: 'list' as const,
            label: t.value.projects.view_list,
            icon: ClipboardList,
        },
        {
            value: 'kanban' as const,
            label: t.value.projects.view_kanban,
            icon: FolderKanban,
        },
        {
            value: 'gantt' as const,
            label: t.value.projects.view_gantt,
            icon: CalendarClock,
        },
    ];
});

const kanbanColumns = computed(() => {
    return props.taskOptions.statuses.map((statusOption) => ({
        ...statusOption,
        tasks: flattenedStandaloneTasks.value.filter(
            (task) => task.status === statusOption.value,
        ),
    }));
});

const taskTreeForProject = (
    projectId: number | null,
): ProjectTaskListItem[] => {
    if (projectId === null) {
        return standaloneTaskGroup.value?.tasks ?? [];
    }

    return (
        props.taskGroups.find((group) => group.project?.id === projectId)
            ?.tasks ?? []
    );
};

const selectedParentTaskTree = computed<ProjectTaskListItem[]>(() => {
    return taskTreeForProject(selectedTaskProjectId.value);
});

const selectedActiveParentTaskTree = computed<ProjectTaskListItem[]>(() => {
    return taskTreeForProject(selectedActiveTaskProjectId.value);
});

const projectOptionFor = (projectId: number | null): ProjectOption | null => {
    if (projectId === null) {
        return null;
    }

    return (
        props.availableProjects.find((project) => project.id === projectId) ??
        null
    );
};

const taskMemberOptionsFor = (
    projectId: number | null,
): ProjectUserSummary[] => {
    return projectOptionFor(projectId)?.members ?? props.availableUsers;
};

const taskMemberOptions = computed(() => {
    return taskMemberOptionsFor(selectedTaskProjectId.value);
});

const activeTaskMemberOptions = computed(() => {
    return taskMemberOptionsFor(selectedActiveTaskProjectId.value);
});

const flattenTaskTree = (
    tasks: ProjectTaskListItem[],
    level = 0,
): Array<ProjectTaskListItem & { level: number }> => {
    return tasks.flatMap((task) => [
        { ...task, level },
        ...flattenTaskTree(task.subtasks, level + 1),
    ]);
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return t.value.common.not_specified;
    }

    const locale = language.value === 'ru' ? 'ru-RU' : 'en-US';

    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const startOfDay = (value: string): Date => {
    const date = new Date(value);

    date.setHours(0, 0, 0, 0);

    return date;
};

const DAY_IN_MILLISECONDS = 24 * 60 * 60 * 1000;

const ganttTasks = computed(() => {
    return flattenedStandaloneTasks.value
        .filter((task) => task.due_at !== null)
        .map((task) => {
            const fallbackStart =
                task.created_at ?? task.updated_at ?? task.due_at!;
            const start = startOfDay(fallbackStart);
            const dueAt = startOfDay(task.due_at ?? fallbackStart);
            const end = dueAt.getTime() < start.getTime() ? start : dueAt;

            return {
                ...task,
                start,
                end,
            };
        })
        .sort(
            (first, second) => first.start.getTime() - second.start.getTime(),
        );
});

const ganttTasksWithoutDueDate = computed(() => {
    return flattenedStandaloneTasks.value.filter(
        (task) => task.due_at === null,
    );
});

const ganttStartDate = computed<Date | null>(() => {
    if (ganttTasks.value.length === 0) {
        return null;
    }

    return new Date(
        Math.min(...ganttTasks.value.map((task) => task.start.getTime())),
    );
});

const ganttEndDate = computed<Date | null>(() => {
    if (ganttTasks.value.length === 0) {
        return null;
    }

    const endTime = Math.max(
        ...ganttTasks.value.map((task) => task.end.getTime()),
    );

    return new Date(endTime + 2 * DAY_IN_MILLISECONDS);
});

const ganttDates = computed<Date[]>(() => {
    if (!ganttStartDate.value || !ganttEndDate.value) {
        return [];
    }

    const dates: Date[] = [];
    const cursor = new Date(ganttStartDate.value);

    while (cursor.getTime() <= ganttEndDate.value.getTime()) {
        dates.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }

    return dates;
});

const ganttGridTemplateColumns = computed(() => {
    return `minmax(16rem, 20rem) repeat(${Math.max(ganttDates.value.length, 1)}, minmax(3.25rem, 1fr))`;
});

const ganttRows = computed(() => {
    if (!ganttStartDate.value) {
        return [];
    }

    return ganttTasks.value.map((task) => {
        const offset = Math.floor(
            (task.start.getTime() - ganttStartDate.value!.getTime()) /
                DAY_IN_MILLISECONDS,
        );
        const span = Math.max(
            1,
            Math.floor(
                (task.end.getTime() - task.start.getTime()) /
                    DAY_IN_MILLISECONDS,
            ) + 1,
        );

        return {
            ...task,
            offset,
            span,
        };
    });
});

const taskMutationQuery = computed<Record<string, string> | undefined>(() => {
    if (!isTasksPage.value) {
        return undefined;
    }

    return {
        mode: props.pageMode,
        view: props.taskDisplayMode,
    };
});

const findTaskInTree = (
    tasks: ProjectTaskListItem[],
    taskId: number,
): ProjectTaskListItem | null => {
    for (const task of tasks) {
        if (task.id === taskId) {
            return task;
        }

        const nestedMatch = findTaskInTree(task.subtasks, taskId);

        if (nestedMatch) {
            return nestedMatch;
        }
    }

    return null;
};

const collectDescendantIds = (task: ProjectTaskListItem): number[] => {
    return task.subtasks.flatMap((subtask) => [
        subtask.id,
        ...collectDescendantIds(subtask),
    ]);
};

const buildParentTaskOptions = (
    tasks: ProjectTaskListItem[],
    excludeTaskId: number | null = null,
): ParentTaskOption[] => {
    const excludedIds = new Set<number>();

    if (excludeTaskId !== null) {
        excludedIds.add(excludeTaskId);

        const currentTaskTree = findTaskInTree(tasks, excludeTaskId);

        if (currentTaskTree) {
            collectDescendantIds(currentTaskTree).forEach((taskId) =>
                excludedIds.add(taskId),
            );
        }
    }

    return flattenTaskTree(tasks)
        .filter((task) => !excludedIds.has(task.id))
        .map((task) => ({
            id: task.id,
            label: `${'— '.repeat(task.level)}${task.title}`,
        }));
};

const parentTaskOptions = computed<ParentTaskOption[]>(() => {
    return buildParentTaskOptions(selectedParentTaskTree.value);
});

const activeParentTaskOptions = computed<ParentTaskOption[]>(() => {
    return buildParentTaskOptions(
        selectedActiveParentTaskTree.value,
        props.activeTask?.id ?? null,
    );
});

watch(parentTaskOptions, (options) => {
    if (taskForm.parent_task_id === '') {
        return;
    }

    const hasCurrentParent = options.some(
        (option) => option.id === Number(taskForm.parent_task_id),
    );

    if (!hasCurrentParent) {
        taskForm.parent_task_id = '';
    }
});

watch(activeParentTaskOptions, (options) => {
    if (activeTaskForm.parent_task_id === '') {
        return;
    }

    const hasCurrentParent = options.some(
        (option) => option.id === Number(activeTaskForm.parent_task_id),
    );

    if (!hasCurrentParent) {
        activeTaskForm.parent_task_id = '';
    }
});

const resetProjectForm = (): void => {
    projectForm.reset();
    projectForm.is_archived = false;
    projectForm.member_user_ids = [];
    projectForm.clearErrors();
    projectEditorMode.value = 'idle';
};

const openCreateProject = (): void => {
    resetProjectForm();
    projectEditorMode.value = 'create';
};

const openEditProject = (): void => {
    if (!props.activeProject) {
        return;
    }

    projectForm.name = props.activeProject.name;
    projectForm.slug = props.activeProject.slug;
    projectForm.description = props.activeProject.description ?? '';
    projectForm.is_archived = props.activeProject.is_archived;
    projectForm.member_user_ids = props.activeProject.members.map(
        (user) => user.id,
    );
    projectForm.clearErrors();
    projectEditorMode.value = 'edit';
};

const toggleProjectMember = (
    userId: number,
    checked: boolean | 'indeterminate' | null | undefined,
): void => {
    if (checked === true) {
        projectForm.member_user_ids = [
            ...new Set([...projectForm.member_user_ids, userId]),
        ];

        return;
    }

    projectForm.member_user_ids = projectForm.member_user_ids.filter(
        (value) => value !== userId,
    );
};

const setProjectArchived = (
    checked: boolean | 'indeterminate' | null | undefined,
): void => {
    projectForm.is_archived = checked === true;
};

const projectMemberHandler = (userId: number) => {
    return (checked: boolean | 'indeterminate' | null | undefined): void => {
        toggleProjectMember(userId, checked);
    };
};

const submitProject = (): void => {
    if (projectEditorMode.value === 'edit' && props.activeProject) {
        projectForm.patch(update.url(props.activeProject.id), {
            preserveScroll: true,
            onSuccess: () => {
                projectEditorMode.value = 'idle';
            },
        });

        return;
    }

    projectForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: resetProjectForm,
    });
};

const downloadTaskCsv = (projectId: number | null): void => {
    closeTaskCsvPanel();

    window.location.assign(
        projectId === null
            ? exportStandaloneTasks.url({
                  query: {
                      delimiter: taskCsvImportForm.delimiter,
                  },
              })
            : exportProjectTasks.url(projectId, {
                  query: {
                      delimiter: taskCsvImportForm.delimiter,
                  },
              }),
    );
};

const downloadTaskCsvTemplate = (projectId: number | null): void => {
    window.location.assign(
        projectId === null
            ? downloadStandaloneTasksTemplate.url({
                  query: {
                      delimiter: taskCsvImportForm.delimiter,
                  },
              })
            : downloadProjectTasksTemplate.url(projectId, {
                  query: {
                      delimiter: taskCsvImportForm.delimiter,
                  },
              }),
    );
};

const openTaskCsvPanel = (
    mode: CsvPanelMode,
    projectId: number | null,
): void => {
    taskCsvImportContext.value = projectId === null ? 'standalone' : projectId;
    taskCsvImportForm.clearErrors();
    taskCsvImportForm.file = null;
    taskCsvPanelMode.value = mode;
};

const closeTaskCsvPanel = (): void => {
    taskCsvPanelMode.value = null;
    taskCsvImportContext.value = null;
    taskCsvImportForm.clearErrors();
};

const taskCsvProjectId = (): number | null => {
    return taskCsvImportContext.value === 'standalone'
        ? null
        : taskCsvImportContext.value;
};

const downloadSelectedTaskCsv = (): void => {
    if (taskCsvImportContext.value === null) {
        return;
    }

    downloadTaskCsv(taskCsvProjectId());
};

const downloadSelectedTaskCsvTemplate = (): void => {
    if (taskCsvImportContext.value === null) {
        return;
    }

    downloadTaskCsvTemplate(taskCsvProjectId());
};

const submitTaskCsvImport = (): void => {
    if (
        taskCsvImportForm.file === null ||
        taskCsvImportContext.value === null
    ) {
        return;
    }

    const projectId = taskCsvProjectId();

    taskCsvImportForm.post(
        projectId === null
            ? importStandaloneTasks.url()
            : importProjectTasks.url(projectId),
        {
            preserveScroll: true,
            onSuccess: () => {
                taskCsvImportForm.reset();
                closeTaskCsvPanel();
            },
        },
    );
};

const selectTaskCsvFile = (file: File | null): void => {
    taskCsvImportForm.file = file;
    taskCsvImportForm.clearErrors('file');
};

const deleteCurrentProject = (): void => {
    if (
        !props.activeProject ||
        !window.confirm(t.value.projects.delete_project_confirm)
    ) {
        return;
    }

    router.delete(destroyProject.url(props.activeProject.id), {
        preserveScroll: true,
    });
};

const resetTaskStageForm = (): void => {
    taskStageForm.reset();
    taskStageForm.clearErrors();
    taskStageForm.name = '';
    taskStageForm.color = '#64748B';
    editingTaskStageId.value = null;
};

const openTaskStageManager = (): void => {
    resetTaskStageForm();
    taskStageSheetMode.value = 'list';
    taskStageSheetOpen.value = true;
};

const openCreateTaskStage = (): void => {
    resetTaskStageForm();
    taskStageForm.color =
        taskStages.value.find((option) => !option.is_completed)?.color ??
        '#64748B';
    taskStageSheetMode.value = 'create';
    taskStageSheetOpen.value = true;
};

const openEditTaskStage = (stage: ProjectTaskStageOption): void => {
    resetTaskStageForm();
    taskStageForm.name = stage.label;
    taskStageForm.color = stage.color;
    editingTaskStageId.value = stage.id;
    taskStageSheetMode.value = 'edit';
    taskStageSheetOpen.value = true;
};

const submitTaskStage = (): void => {
    if (taskStageSheetMode.value === 'create') {
        taskStageForm.post(storeTaskStage.url(), {
            preserveScroll: true,
            onSuccess: () => {
                taskStageSheetMode.value = 'list';
                taskStageSheetOpen.value = false;
                resetTaskStageForm();
            },
        });

        return;
    }

    if (!editingTaskStageId.value) {
        return;
    }

    taskStageForm.patch(updateTaskStage.url(editingTaskStageId.value), {
        preserveScroll: true,
        onSuccess: () => {
            taskStageSheetMode.value = 'list';
            taskStageSheetOpen.value = false;
            resetTaskStageForm();
        },
    });
};

const resetKanbanStageDragState = (): void => {
    draggedStageId.value = null;
    dragOverStageId.value = null;
};

const resetTaskForm = (): void => {
    taskForm.reset();
    taskForm.project_id = isTasksPage.value
        ? ''
        : (props.activeProject?.id ?? '');
    taskForm.parent_task_id = '';
    taskForm.status = defaultTaskStatus();
    taskForm.importance = 'normal';
    taskForm.complexity = 5;
    taskForm.due_at = '';
    taskForm.sort_order = 0;
    taskForm.assignee_user_id = '';
    taskForm.co_assignee_user_ids = [];
    taskForm.clearErrors();
    taskEditorMode.value = 'idle';
};

const taskPayload = (form: TaskFormData): TaskFormData => ({
    project_id: form.project_id,
    parent_task_id: form.parent_task_id,
    title: form.title,
    description: form.description,
    status: form.status,
    importance: form.importance,
    complexity: form.complexity,
    due_at: form.due_at,
    sort_order: form.sort_order,
    assignee_user_id: form.assignee_user_id,
    co_assignee_user_ids: [...form.co_assignee_user_ids],
});

const taskPayloadFromActiveTask = (
    task: ProjectActiveTask | null,
): TaskFormData => ({
    project_id: task?.project_id ?? '',
    parent_task_id: task?.parent_task_id ?? '',
    title: task?.title ?? '',
    description: task?.description ?? '',
    status: task?.status ?? defaultTaskStatus(),
    importance: task?.importance ?? 'normal',
    complexity: task?.complexity ?? 5,
    due_at: toDateTimeLocalValue(task?.due_at ?? null),
    sort_order: task?.sort_order ?? 0,
    assignee_user_id: task?.assignee?.id ?? '',
    co_assignee_user_ids: task?.co_assignees.map((user) => user.id) ?? [],
});

const clearActiveTaskSaveTimeout = (): void => {
    if (activeTaskSaveTimeout !== null) {
        clearTimeout(activeTaskSaveTimeout);
        activeTaskSaveTimeout = null;
    }
};

const syncActiveTaskForm = (task: ProjectActiveTask | null): void => {
    isSyncingActiveTask.value = true;
    clearActiveTaskSaveTimeout();
    activeTaskForm.clearErrors();

    const payload = taskPayloadFromActiveTask(task);

    activeTaskForm.project_id = payload.project_id;
    activeTaskForm.parent_task_id = payload.parent_task_id;
    activeTaskForm.title = payload.title;
    activeTaskForm.description = payload.description;
    activeTaskForm.status = payload.status;
    activeTaskForm.importance = payload.importance;
    activeTaskForm.complexity = payload.complexity;
    activeTaskForm.due_at = payload.due_at;
    activeTaskForm.sort_order = payload.sort_order;
    activeTaskForm.assignee_user_id = payload.assignee_user_id;
    activeTaskForm.co_assignee_user_ids = payload.co_assignee_user_ids;
    activeTaskSaveState.value = 'idle';

    isSyncingActiveTask.value = false;
};

const scheduleActiveTaskSave = (delay = 500): void => {
    clearActiveTaskSaveTimeout();

    activeTaskSaveTimeout = setTimeout(() => {
        submitActiveTaskUpdate();
    }, delay);
};

const handleActiveTaskFieldChange = (): void => {
    if (
        !props.activeTask ||
        !props.can.manageTask ||
        isSyncingActiveTask.value
    ) {
        return;
    }

    scheduleActiveTaskSave();
};

const openCreateTask = (
    projectId: number | null = isTasksPage.value
        ? null
        : (props.activeProject?.id ?? null),
): void => {
    resetTaskForm();
    taskForm.project_id = isTasksPage.value ? '' : (projectId ?? '');
    taskEditorMode.value = 'create';
};

const openCreateSubtask = (task: ProjectTaskListItem): void => {
    openCreateTask(task.project_id);
    taskForm.parent_task_id = task.id;
};

const openCreateSubtaskFromActiveTask = (): void => {
    if (!props.activeTask) {
        return;
    }

    openCreateTask(props.activeTask.project_id);
    taskForm.parent_task_id = props.activeTask.id;
};

const submitTask = (): void => {
    taskForm
        .transform((data) => ({
            ...data,
            due_at: normalizeDueAtForSubmission(data.due_at),
        }))
        .post(
            storeWorkspaceTask.url(
                taskMutationQuery.value
                    ? {
                          query: taskMutationQuery.value,
                      }
                    : undefined,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    taskEditorMode.value = 'idle';
                },
            },
        );
};

const handleTaskAssigneeChange = (): void => {
    if (taskForm.assignee_user_id === '') {
        return;
    }

    taskForm.co_assignee_user_ids = taskForm.co_assignee_user_ids.filter(
        (userId) => userId !== Number(taskForm.assignee_user_id),
    );
};

const handleActiveTaskCoAssigneeChange = (): void => {
    handleActiveTaskFieldChange();
};

const submitActiveTaskUpdate = (): void => {
    if (!props.activeTask || !props.can.manageTask) {
        return;
    }

    if (
        JSON.stringify(taskPayload(activeTaskForm)) ===
        JSON.stringify(taskPayloadFromActiveTask(props.activeTask))
    ) {
        activeTaskSaveState.value = 'idle';

        return;
    }

    if (activeTaskForm.processing) {
        scheduleActiveTaskSave(250);

        return;
    }

    activeTaskSaveState.value = 'saving';

    activeTaskForm
        .transform((data) => ({
            ...data,
            due_at: normalizeDueAtForSubmission(data.due_at),
        }))
        .patch(
            updateWorkspaceTask.url(
                props.activeTask.id,
                taskMutationQuery.value
                    ? {
                          query: taskMutationQuery.value,
                      }
                    : undefined,
            ),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onSuccess: () => {
                    activeTaskSaveState.value = 'saved';

                    window.setTimeout(() => {
                        if (activeTaskSaveState.value === 'saved') {
                            activeTaskSaveState.value = 'idle';
                        }
                    }, savedTaskStateResetDelay);
                },
                onError: () => {
                    activeTaskSaveState.value = 'error';
                },
            },
        );
};

watch(selectedTaskProjectId, () => {
    const allowedMemberIds = new Set(
        taskMemberOptions.value.map((member) => member.id),
    );

    if (
        taskForm.assignee_user_id !== '' &&
        !allowedMemberIds.has(Number(taskForm.assignee_user_id))
    ) {
        taskForm.assignee_user_id = '';
    }

    taskForm.co_assignee_user_ids = taskForm.co_assignee_user_ids.filter(
        (userId) => allowedMemberIds.has(userId),
    );
});

watch(
    () => taskForm.assignee_user_id,
    () => {
        handleTaskAssigneeChange();
    },
);

watch(
    () =>
        props.activeTask
            ? `${props.activeTask.id}:${props.activeTask.updated_at}`
            : null,
    () => {
        syncActiveTaskForm(props.activeTask);
    },
    {
        immediate: true,
    },
);

watch(selectedActiveTaskProjectId, () => {
    const allowedMemberIds = new Set(
        activeTaskMemberOptions.value.map((member) => member.id),
    );

    if (
        activeTaskForm.assignee_user_id !== '' &&
        !allowedMemberIds.has(Number(activeTaskForm.assignee_user_id))
    ) {
        activeTaskForm.assignee_user_id = '';
    }

    activeTaskForm.co_assignee_user_ids =
        activeTaskForm.co_assignee_user_ids.filter((userId) => {
            return allowedMemberIds.has(userId);
        });
});

watch(
    () => activeTaskForm.assignee_user_id,
    (value) => {
        if (value === '') {
            return;
        }

        activeTaskForm.co_assignee_user_ids =
            activeTaskForm.co_assignee_user_ids.filter((userId) => {
                return userId !== Number(value);
            });
    },
);

onBeforeUnmount(() => {
    clearActiveTaskSaveTimeout();
});

const deleteCurrentTask = (): void => {
    if (
        !props.activeTask ||
        !window.confirm(t.value.projects.delete_task_confirm)
    ) {
        return;
    }

    router.delete(
        destroyWorkspaceTask.url(
            props.activeTask.id,
            taskMutationQuery.value
                ? {
                      query: taskMutationQuery.value,
                  }
                : undefined,
        ),
        {
            preserveScroll: true,
        },
    );
};

const resetKanbanDragState = (): void => {
    draggedTaskId.value = null;
    dragOverTaskStatus.value = null;
    movingTaskId.value = null;
};

const startDraggingStage = (stageId: number, event: DragEvent): void => {
    draggedStageId.value = stageId;
    dragOverTaskStatus.value = null;
    event.dataTransfer?.setData('text/task-stage-id', String(stageId));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const startDraggingTask = (taskId: number, event: DragEvent): void => {
    draggedTaskId.value = taskId;
    dragOverStageId.value = null;
    event.dataTransfer?.setData('text/plain', String(taskId));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const markTaskColumnAsDropTarget = (status: string): void => {
    if (draggedTaskId.value === null) {
        return;
    }

    dragOverTaskStatus.value = status;
};

const markStageColumnAsDropTarget = (stageId: number | null): void => {
    if (draggedStageId.value === null || stageId === null) {
        return;
    }

    dragOverStageId.value = stageId;
};

const reorderDraggedStage = (targetStageId: number | null): void => {
    if (draggedStageId.value === null || targetStageId === null) {
        resetKanbanStageDragState();

        return;
    }

    const orderedStageIds = taskStages.value
        .map((stage) => stage.id)
        .filter((stageId): stageId is number => stageId !== null);
    const sourceIndex = orderedStageIds.indexOf(draggedStageId.value);
    const targetIndex = orderedStageIds.indexOf(targetStageId);

    if (
        sourceIndex === -1 ||
        targetIndex === -1 ||
        sourceIndex === targetIndex
    ) {
        resetKanbanStageDragState();

        return;
    }

    const reorderedStageIds = [...orderedStageIds];
    const [draggedStage] = reorderedStageIds.splice(sourceIndex, 1);

    reorderedStageIds.splice(targetIndex, 0, draggedStage);

    router.patch(
        moveTaskStages.url(),
        {
            stage_ids: reorderedStageIds,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: resetKanbanStageDragState,
        },
    );
};

const handleKanbanColumnDragOver = (column: ProjectTaskStageOption): void => {
    if (draggedStageId.value !== null) {
        markStageColumnAsDropTarget(column.id);

        return;
    }

    markTaskColumnAsDropTarget(column.value);
};

const handleKanbanColumnDrop = (column: ProjectTaskStageOption): void => {
    if (draggedStageId.value !== null) {
        reorderDraggedStage(column.id);

        return;
    }

    moveDraggedTask(column.value);
};

const moveDraggedTask = (status: string): void => {
    if (draggedTaskId.value === null || movingTaskId.value !== null) {
        return;
    }

    const task = flattenedStandaloneTasks.value.find(
        (item) => item.id === draggedTaskId.value,
    );

    if (!task || task.status === status) {
        resetKanbanDragState();

        return;
    }

    movingTaskId.value = task.id;

    router.patch(
        moveWorkspaceTask.url(
            task.id,
            taskMutationQuery.value
                ? {
                      query: taskMutationQuery.value,
                  }
                : undefined,
        ),
        { status },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: resetKanbanDragState,
        },
    );
};

const closeTaskSheet = (): void => {
    resetTaskForm();

    if (!props.activeTask) {
        return;
    }

    router.get(
        taskSheetBaseRoute.value.url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const handleTaskSheetOpenChange = (open: boolean): void => {
    if (open) {
        return;
    }

    closeTaskSheet();
};

const handleTaskStageSheetOpenChange = (open: boolean): void => {
    taskStageSheetOpen.value = open;

    if (open) {
        return;
    }

    taskStageSheetMode.value = 'list';
    resetTaskStageForm();
};
</script>

<template>
    <Head :title="isTasksPage ? t.projects.tasks : t.projects.title" />

    <CsvExchangeSheet
        :open="taskCsvPanelMode !== null"
        :mode="taskCsvPanelMode ?? 'export'"
        :title="
            taskCsvPanelMode === 'import'
                ? t.projects.import_tasks_csv
                : t.projects.export_tasks_csv
        "
        :description="t.projects.csv_import_help"
        :delimiter="taskCsvImportForm.delimiter"
        :delimiter-label="t.projects.csv_delimiter"
        :delimiter-placeholder="t.projects.csv_delimiter_placeholder"
        :delimiter-hint="t.projects.csv_delimiter_hint"
        :file-label="t.projects.csv_file"
        :export-label="t.projects.export_tasks_csv"
        :import-label="t.projects.import_tasks_csv"
        :template-label="t.projects.download_tasks_csv_template"
        :selected-file="taskCsvImportForm.file"
        :processing="taskCsvImportForm.processing"
        :progress="taskCsvImportForm.progress?.percentage ?? null"
        :delimiter-error="taskCsvImportForm.errors.delimiter"
        :file-error="taskCsvImportForm.errors.file"
        @update:open="(isOpen) => !isOpen && closeTaskCsvPanel()"
        @update:delimiter="taskCsvImportForm.delimiter = $event"
        @file-selected="selectTaskCsvFile"
        @download-template="downloadSelectedTaskCsvTemplate"
        @import="submitTaskCsvImport"
        @export="downloadSelectedTaskCsv"
    />

    <div
        class="min-w-0 gap-6"
        :class="
            isTasksPage ? 'grid' : 'grid xl:grid-cols-[320px_minmax(0,1fr)]'
        "
    >
        <section v-if="!isTasksPage" class="min-w-0 space-y-4">
            <div class="rounded-3xl border border-border bg-card p-5 shadow-sm">
                <div class="space-y-4">
                    <div>
                        <h1 class="text-lg font-semibold">
                            {{ t.projects.title }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ t.projects.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            @click="openCreateTask()"
                        >
                            <Plus class="size-4" />
                            {{ t.projects.create_task }}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openCreateProject"
                        >
                            <FolderKanban class="size-4" />
                            {{ t.projects.create_project }}
                        </Button>
                    </div>
                </div>
            </div>

            <Link
                :href="projectsIndex()"
                class="block rounded-3xl border px-4 py-4 transition hover:border-primary/40 hover:bg-background"
                :class="
                    !props.activeProject
                        ? 'border-primary/50 bg-background'
                        : 'border-border bg-card'
                "
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Layers3 class="size-4 text-muted-foreground" />
                            <span class="font-medium">{{
                                t.projects.workspace_overview
                            }}</span>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.projects.workspace_overview_description }}
                        </p>
                    </div>

                    <span
                        class="rounded-full bg-background px-2 py-1 text-xs text-muted-foreground"
                    >
                        {{ props.workspaceSummary.standalone_tasks_count }}
                    </span>
                </div>

                <div
                    class="mt-4 grid grid-cols-3 gap-2 text-xs text-muted-foreground"
                >
                    <div>
                        <div class="font-medium text-foreground">
                            {{ props.workspaceSummary.standalone_tasks_count }}
                        </div>
                        <div>{{ t.projects.standalone_tasks }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-foreground">
                            {{
                                props.workspaceSummary
                                    .standalone_open_tasks_count
                            }}
                        </div>
                        <div>{{ t.projects.open_tasks }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-foreground">
                            {{
                                props.workspaceSummary
                                    .standalone_completed_tasks_count
                            }}
                        </div>
                        <div>{{ t.projects.done_tasks }}</div>
                    </div>
                </div>
            </Link>

            <div class="space-y-3">
                <Link
                    v-for="project in props.projects"
                    :key="project.id"
                    :href="show(project.id)"
                    class="block rounded-3xl border px-4 py-4 transition hover:border-primary/40 hover:bg-background"
                    :class="
                        props.activeProject?.id === project.id
                            ? 'border-primary/50 bg-background'
                            : 'border-border bg-card'
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-2">
                            <div class="flex items-center gap-2">
                                <FolderKanban
                                    class="size-4 text-muted-foreground"
                                />
                                <span class="truncate font-medium">{{
                                    project.name
                                }}</span>
                            </div>
                            <p
                                v-if="project.description"
                                class="line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ project.description }}
                            </p>
                        </div>

                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium"
                            :class="projectStatusClass(project)"
                        >
                            {{
                                project.is_archived
                                    ? t.projects.archived
                                    : t.projects.active
                            }}
                        </span>
                    </div>

                    <div
                        class="mt-4 grid grid-cols-3 gap-2 text-xs text-muted-foreground"
                    >
                        <div>
                            <div class="font-medium text-foreground">
                                {{ project.members_count }}
                            </div>
                            <div>{{ t.projects.members }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-foreground">
                                {{ project.open_tasks_count }}
                            </div>
                            <div>{{ t.projects.open_tasks }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-foreground">
                                {{ project.completed_tasks_count }}
                            </div>
                            <div>{{ t.projects.done_tasks }}</div>
                        </div>
                    </div>
                </Link>
            </div>

            <div
                v-if="projectEditorMode !== 'idle'"
                class="rounded-3xl border border-border bg-card p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">
                        {{
                            projectEditorMode === 'create'
                                ? t.projects.create_project
                                : t.projects.edit_project
                        }}
                    </h2>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="resetProjectForm"
                    >
                        {{ t.common.cancel }}
                    </Button>
                </div>

                <form class="space-y-4" @submit.prevent="submitProject">
                    <div class="space-y-2">
                        <Label for="project-name">{{
                            t.projects.project_name
                        }}</Label>
                        <Input id="project-name" v-model="projectForm.name" />
                        <InputError :message="projectForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="project-slug">{{
                            t.projects.project_slug
                        }}</Label>
                        <Input id="project-slug" v-model="projectForm.slug" />
                        <InputError :message="projectForm.errors.slug" />
                    </div>

                    <div class="space-y-2">
                        <Label for="project-description">{{
                            t.projects.description_label
                        }}</Label>
                        <textarea
                            id="project-description"
                            v-model="projectForm.description"
                            rows="4"
                            class="min-h-28 w-full rounded-2xl border border-input bg-background px-3 py-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <InputError :message="projectForm.errors.description" />
                    </div>

                    <label
                        class="flex items-start gap-3 rounded-2xl border border-border bg-background/70 p-3"
                    >
                        <Checkbox
                            :checked="projectForm.is_archived"
                            @update:checked="setProjectArchived"
                        />
                        <div>
                            <div class="text-sm font-medium">
                                {{ t.projects.archive_project }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.projects.archive_project_help }}
                            </p>
                        </div>
                    </label>

                    <div class="space-y-3">
                        <div>
                            <div class="text-sm font-medium">
                                {{ t.projects.members }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.projects.project_members_help }}
                            </p>
                        </div>

                        <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                            <label
                                v-for="user in props.availableUsers"
                                :key="user.id"
                                class="flex items-start gap-3 rounded-2xl border border-border bg-background/70 p-3"
                            >
                                <Checkbox
                                    :checked="
                                        projectForm.member_user_ids.includes(
                                            user.id,
                                        )
                                    "
                                    :disabled="
                                        projectEditorMode === 'edit' &&
                                        activeProjectOwnerId === user.id
                                    "
                                    @update:checked="
                                        projectMemberHandler(user.id)
                                    "
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">
                                        {{ fullName(user) }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ user.email }}
                                    </div>
                                </div>
                            </label>
                        </div>
                        <InputError
                            :message="projectForm.errors.member_user_ids"
                        />
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="projectForm.processing"
                        >
                            <Save class="size-4" />
                            {{ t.common.save }}
                        </Button>
                    </div>
                </form>
            </div>
        </section>

        <section class="min-w-0 space-y-4">
            <template v-if="isTasksPage">
                <div
                    class="min-w-0 rounded-3xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <ClipboardList
                                    class="size-5 text-muted-foreground"
                                />
                                <h1 class="text-xl font-semibold">
                                    {{ t.projects.tasks }}
                                </h1>
                            </div>

                            <p class="max-w-3xl text-sm text-muted-foreground">
                                {{ t.projects.tasks_page_description }}
                            </p>

                            <p class="max-w-3xl text-xs text-muted-foreground">
                                {{ t.projects.csv_import_help }}
                            </p>

                            <div
                                class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                            >
                                <span
                                    class="rounded-full bg-background px-2 py-1"
                                >
                                    {{ t.projects.tasks }}:
                                    {{
                                        props.workspaceSummary
                                            .standalone_tasks_count
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-background px-2 py-1"
                                >
                                    {{ t.projects.open_tasks }}:
                                    {{
                                        props.workspaceSummary
                                            .standalone_open_tasks_count
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-background px-2 py-1"
                                >
                                    {{ t.projects.done_tasks }}:
                                    {{
                                        props.workspaceSummary
                                            .standalone_completed_tasks_count
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="taskCsvImportForm.processing"
                                @click="openTaskCsvPanel('export', null)"
                            >
                                <Download class="size-4" />
                                {{ t.projects.export_tasks_csv }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="taskCsvImportForm.processing"
                                @click="openTaskCsvPanel('import', null)"
                            >
                                <Upload class="size-4" />
                                {{ t.projects.import_tasks_csv }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                @click="openCreateTask(null)"
                            >
                                <Plus class="size-4" />
                                {{ t.projects.create_task }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <div class="text-sm font-medium">
                                {{ t.projects.view_mode }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.projects.view_mode_description }}
                            </p>
                        </div>

                        <div
                            class="inline-flex flex-wrap gap-1 rounded-xl bg-muted/50 p-1"
                        >
                            <Link
                                v-for="option in taskViewOptions"
                                :key="option.value"
                                :href="
                                    props.activeTask
                                        ? taskRoute(
                                              props.activeTask.id,
                                              option.value,
                                          )
                                        : taskIndexRoute(option.value)
                                "
                                preserve-scroll
                                replace
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition"
                                :class="
                                    props.taskDisplayMode === option.value
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'
                                "
                            >
                                <component :is="option.icon" class="size-4" />
                                <span>{{ option.label }}</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <Layers3 class="size-5 text-muted-foreground" />
                                <h2 class="text-base font-semibold">
                                    {{ standaloneTaskGroup?.title }}
                                </h2>
                            </div>

                            <p
                                v-if="standaloneTaskGroup?.description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ standaloneTaskGroup.description }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="openCreateTask(null)"
                            >
                                <GitBranchPlus class="size-4" />
                                {{ t.projects.create_task }}
                            </Button>
                            <template
                                v-if="
                                    props.taskDisplayMode === 'kanban' &&
                                    props.can.manageTaskStages
                                "
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="openCreateTaskStage"
                                >
                                    <Plus class="size-4" />
                                    {{ t.projects.create_stage }}
                                </Button>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="outline"
                                    @click="openTaskStageManager"
                                >
                                    <Settings2 class="size-4" />
                                    <span class="sr-only">{{
                                        t.projects.manage_stages
                                    }}</span>
                                </Button>
                            </template>
                        </div>
                    </div>

                    <div
                        v-if="(standaloneTaskGroup?.tasks.length ?? 0) === 0"
                        class="rounded-2xl border border-dashed border-border bg-background/70 p-6 text-sm text-muted-foreground"
                    >
                        {{ t.projects.no_standalone_tasks_description }}
                    </div>

                    <template v-else-if="props.taskDisplayMode === 'list'">
                        <div class="space-y-3">
                            <ProjectTaskTreeItem
                                v-for="taskItem in standaloneTaskGroup?.tasks ??
                                []"
                                :key="taskItem.id"
                                :task="taskItem"
                                :active-task-id="props.activeTask?.id ?? null"
                                :task-options="props.taskOptions"
                                :can-create-subtasks="props.can.createTask"
                                :task-href-resolver="taskHrefResolver"
                                @create-subtask="openCreateSubtask"
                            />
                        </div>
                    </template>

                    <template v-else-if="props.taskDisplayMode === 'kanban'">
                        <div
                            class="scrollbar-x-visible max-w-full overflow-x-scroll pb-2"
                        >
                            <div class="flex min-w-max gap-4">
                                <div
                                    v-for="column in kanbanColumns"
                                    :key="column.value"
                                    class="flex w-[320px] shrink-0 flex-col rounded-2xl border border-border bg-background/60 p-4 transition-colors"
                                    :class="
                                        dragOverTaskStatus === column.value ||
                                        dragOverStageId === column.id
                                            ? 'border-primary/50 bg-primary/5'
                                            : ''
                                    "
                                    :style="taskStageColumnStyle(column.value)"
                                    @dragover.prevent="
                                        handleKanbanColumnDragOver(column)
                                    "
                                    @dragenter.prevent="
                                        handleKanbanColumnDragOver(column)
                                    "
                                    @drop.prevent="
                                        handleKanbanColumnDrop(column)
                                    "
                                >
                                    <div
                                        class="mb-4 flex items-center justify-between gap-3"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-sm font-medium"
                                        >
                                            <button
                                                v-if="
                                                    props.can
                                                        .manageTaskStages &&
                                                    column.id !== null
                                                "
                                                type="button"
                                                draggable="true"
                                                class="inline-flex cursor-grab items-center justify-center rounded-md p-1 text-muted-foreground transition hover:bg-background hover:text-foreground active:cursor-grabbing"
                                                :title="
                                                    t.projects
                                                        .stage_reorder_hint
                                                "
                                                @dragstart="
                                                    startDraggingStage(
                                                        column.id,
                                                        $event,
                                                    )
                                                "
                                                @dragend="
                                                    resetKanbanStageDragState
                                                "
                                            >
                                                <GripVertical class="size-4" />
                                            </button>
                                            <span
                                                class="size-2.5 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        column.color,
                                                }"
                                            />
                                            {{ column.label }}
                                        </div>
                                        <span
                                            class="rounded-full bg-background px-2 py-1 text-xs text-muted-foreground"
                                        >
                                            {{ column.tasks.length }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="column.tasks.length === 0"
                                        class="rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                                    >
                                        {{ t.projects.empty_status_column }}
                                    </div>

                                    <div v-else class="space-y-3">
                                        <Link
                                            v-for="task in column.tasks"
                                            :key="task.id"
                                            :href="taskHrefResolver(task)"
                                            draggable="true"
                                            class="block rounded-2xl border border-border bg-card px-4 py-4 transition hover:border-primary/40 hover:bg-background"
                                            :class="[
                                                props.activeTask?.id === task.id
                                                    ? 'border-primary/50 bg-background'
                                                    : '',
                                                movingTaskId === task.id
                                                    ? 'opacity-60'
                                                    : 'cursor-grab active:cursor-grabbing',
                                            ]"
                                            @dragstart="
                                                startDraggingTask(
                                                    task.id,
                                                    $event,
                                                )
                                            "
                                            @dragend="resetKanbanDragState"
                                        >
                                            <div class="space-y-3">
                                                <div
                                                    class="flex items-start justify-between gap-3"
                                                >
                                                    <div class="min-w-0">
                                                        <div
                                                            class="truncate text-sm font-medium"
                                                        >
                                                            {{ task.title }}
                                                        </div>
                                                        <div
                                                            v-if="
                                                                task.parent_task_title
                                                            "
                                                            class="mt-1 truncate text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                t.projects
                                                                    .parent_task
                                                            }}:
                                                            {{
                                                                task.parent_task_title
                                                            }}
                                                        </div>
                                                    </div>
                                                    <span
                                                        class="rounded-full bg-background px-2 py-1 text-xs text-muted-foreground"
                                                    >
                                                        {{ task.level + 1 }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                                                >
                                                    <span
                                                        class="rounded-full bg-background px-2 py-1"
                                                    >
                                                        {{
                                                            t.projects.assignee
                                                        }}:
                                                        {{
                                                            fullName(
                                                                task.assignee,
                                                            )
                                                        }}
                                                    </span>
                                                    <span
                                                        class="rounded-full bg-background px-2 py-1"
                                                    >
                                                        {{
                                                            t.projects.due_date
                                                        }}:
                                                        {{
                                                            formatDate(
                                                                task.due_at,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            v-if="ganttRows.length > 0"
                            class="space-y-4 overflow-x-auto"
                        >
                            <div
                                class="grid min-w-[900px] items-stretch rounded-2xl border border-border bg-background/70"
                                :style="{
                                    gridTemplateColumns:
                                        ganttGridTemplateColumns,
                                }"
                            >
                                <div
                                    class="border-b border-border px-4 py-3 text-sm font-medium"
                                >
                                    {{ t.projects.gantt_task }}
                                </div>
                                <div
                                    v-for="date in ganttDates"
                                    :key="date.toISOString()"
                                    class="border-b border-l border-border px-2 py-3 text-center text-xs text-muted-foreground"
                                >
                                    {{
                                        new Intl.DateTimeFormat(
                                            language === 'ru'
                                                ? 'ru-RU'
                                                : 'en-US',
                                            { month: 'short', day: 'numeric' },
                                        ).format(date)
                                    }}
                                </div>

                                <template
                                    v-for="row in ganttRows"
                                    :key="row.id"
                                >
                                    <Link
                                        :href="taskHrefResolver(row)"
                                        class="border-t border-border px-4 py-4 transition hover:bg-background"
                                    >
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ row.title }}
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ formatDate(row.created_at) }} -
                                            {{ formatDate(row.due_at) }}
                                        </div>
                                    </Link>

                                    <div
                                        v-for="date in ganttDates"
                                        :key="`${row.id}-${date.toISOString()}`"
                                        class="border-t border-l border-border/70 px-1 py-4"
                                    ></div>

                                    <div
                                        class="pointer-events-none flex items-center px-1 py-4"
                                        :style="{
                                            gridColumn: `${row.offset + 2} / span ${row.span}`,
                                        }"
                                    >
                                        <div
                                            class="flex h-8 w-full items-center rounded-full px-3 text-xs font-medium text-white"
                                            :style="
                                                taskStageBarStyle(row.status)
                                            "
                                        >
                                            <span class="truncate">{{
                                                row.title
                                            }}</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-border bg-background/70 p-6 text-sm text-muted-foreground"
                        >
                            {{ t.projects.gantt_empty }}
                        </div>

                        <div
                            v-if="ganttTasksWithoutDueDate.length > 0"
                            class="mt-4 rounded-2xl border border-border bg-background/60 p-4"
                        >
                            <div class="mb-3 text-sm font-medium">
                                {{ t.projects.gantt_without_due_date }}
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-for="task in ganttTasksWithoutDueDate"
                                    :key="task.id"
                                    :href="taskHrefResolver(task)"
                                    class="rounded-full bg-background px-3 py-2 text-sm text-muted-foreground transition hover:text-foreground"
                                >
                                    {{ task.title }}
                                </Link>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template v-else>
                <div
                    v-if="props.activeProject"
                    class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <BriefcaseBusiness
                                    class="size-5 text-muted-foreground"
                                />
                                <h2 class="text-xl font-semibold">
                                    {{ props.activeProject.name }}
                                </h2>
                            </div>

                            <p
                                v-if="props.activeProject.description"
                                class="max-w-2xl text-sm text-muted-foreground"
                            >
                                {{ props.activeProject.description }}
                            </p>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        props.activeProject.is_archived
                                            ? 'bg-muted text-muted-foreground'
                                            : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                    "
                                >
                                    {{
                                        props.activeProject.is_archived
                                            ? t.projects.archived
                                            : t.projects.active
                                    }}
                                </span>
                                <span
                                    class="rounded-full bg-background px-2.5 py-1 text-xs text-muted-foreground"
                                >
                                    {{ t.projects.owner }}:
                                    {{ fullName(props.activeProject.owner) }}
                                </span>
                                <span
                                    class="rounded-full bg-background px-2.5 py-1 text-xs text-muted-foreground"
                                >
                                    {{ t.projects.members }}:
                                    {{ props.activeProject.members.length }}
                                </span>
                            </div>

                            <p class="max-w-2xl text-xs text-muted-foreground">
                                {{ t.projects.csv_import_help }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="props.can.workOnActiveProject"
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="taskCsvImportForm.processing"
                                @click="
                                    openTaskCsvPanel(
                                        'export',
                                        props.activeProject.id,
                                    )
                                "
                            >
                                <Download class="size-4" />
                                {{ t.projects.export_tasks_csv }}
                            </Button>
                            <Button
                                v-if="props.can.workOnActiveProject"
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="taskCsvImportForm.processing"
                                @click="
                                    openTaskCsvPanel(
                                        'import',
                                        props.activeProject.id,
                                    )
                                "
                            >
                                <Upload class="size-4" />
                                {{ t.projects.import_tasks_csv }}
                            </Button>
                            <Button
                                v-if="props.can.workOnActiveProject"
                                type="button"
                                size="sm"
                                @click="openCreateTask(props.activeProject.id)"
                            >
                                <Plus class="size-4" />
                                {{ t.projects.create_task }}
                            </Button>
                            <Button
                                v-if="props.can.manageProject"
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="openEditProject"
                            >
                                <PencilLine class="size-4" />
                                {{ t.projects.edit_project }}
                            </Button>
                            <Button
                                v-if="props.can.manageProject"
                                type="button"
                                size="sm"
                                variant="destructive"
                                @click="deleteCurrentProject"
                            >
                                <Trash2 class="size-4" />
                                {{ t.projects.delete_project }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <Layers3 class="size-5 text-muted-foreground" />
                                <h2 class="text-xl font-semibold">
                                    {{ t.projects.workspace_overview }}
                                </h2>
                            </div>

                            <p class="max-w-2xl text-sm text-muted-foreground">
                                {{ t.projects.workspace_overview_description }}
                            </p>
                        </div>

                        <Button
                            type="button"
                            size="sm"
                            @click="openCreateTask()"
                        >
                            <Plus class="size-4" />
                            {{ t.projects.create_task }}
                        </Button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="group in props.taskGroups"
                        :key="group.key"
                        class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                    >
                        <div
                            class="mb-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <FolderKanban
                                        class="size-5 text-muted-foreground"
                                    />
                                    <template v-if="group.project">
                                        <Link
                                            :href="show(group.project.id)"
                                            class="text-base font-semibold hover:underline"
                                        >
                                            {{ group.title }}
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <h3 class="text-base font-semibold">
                                            {{ group.title }}
                                        </h3>
                                    </template>
                                </div>

                                <p
                                    v-if="group.description"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ group.description }}
                                </p>

                                <div
                                    class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="rounded-full bg-background px-2 py-1"
                                    >
                                        {{ t.projects.tasks }}:
                                        {{ group.tasks_count }}
                                    </span>
                                    <span
                                        class="rounded-full bg-background px-2 py-1"
                                    >
                                        {{ t.projects.open_tasks }}:
                                        {{ group.open_tasks_count }}
                                    </span>
                                    <span
                                        class="rounded-full bg-background px-2 py-1"
                                    >
                                        {{ t.projects.done_tasks }}:
                                        {{ group.completed_tasks_count }}
                                    </span>
                                </div>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="
                                    openCreateTask(group.project?.id ?? null)
                                "
                            >
                                <GitBranchPlus class="size-4" />
                                {{ t.projects.create_task }}
                            </Button>
                        </div>

                        <div
                            v-if="group.tasks.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-background/70 p-6 text-sm text-muted-foreground"
                        >
                            {{
                                group.project
                                    ? t.projects.no_tasks_description
                                    : t.projects.no_standalone_tasks_description
                            }}
                        </div>

                        <div v-else class="space-y-3">
                            <ProjectTaskTreeItem
                                v-for="taskItem in group.tasks"
                                :key="taskItem.id"
                                :task="taskItem"
                                :active-task-id="props.activeTask?.id ?? null"
                                :task-options="props.taskOptions"
                                :can-create-subtasks="props.can.createTask"
                                :task-href-resolver="taskHrefResolver"
                                @create-subtask="openCreateSubtask"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </section>
    </div>

    <Sheet
        :open="taskStageSheetOpen"
        @update:open="handleTaskStageSheetOpenChange"
    >
        <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-xl">
            <div
                v-if="taskStageSheetMode === 'list'"
                class="space-y-5 p-5 sm:p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">
                            {{ t.projects.manage_stages }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t.projects.manage_stages_description }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ t.projects.stage_reorder_hint }}
                        </p>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        @click="openCreateTaskStage"
                    >
                        <Plus class="size-4" />
                        {{ t.projects.create_stage }}
                    </Button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="stage in taskStages"
                        :key="stage.id"
                        class="rounded-2xl border border-border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        :style="{
                                            backgroundColor: stage.color,
                                        }"
                                    />
                                    <span class="font-medium">{{
                                        stage.label
                                    }}</span>
                                    <span
                                        v-if="stage.is_completed"
                                        class="rounded-full bg-emerald-500/10 px-2 py-1 text-xs text-emerald-700 dark:text-emerald-300"
                                    >
                                        {{ t.projects.done_tasks }}
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ t.projects.stage_order }}:
                                    {{ stage.sort_order }}
                                </div>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="openEditTaskStage(stage)"
                            >
                                <PencilLine class="size-4" />
                                {{ t.projects.edit_stage }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-5 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">
                            {{
                                taskStageSheetMode === 'create'
                                    ? t.projects.create_stage
                                    : t.projects.edit_stage
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t.projects.stage_form_description }}
                        </p>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="taskStageSheetMode = 'list'"
                    >
                        {{ t.common.cancel }}
                    </Button>
                </div>

                <form class="space-y-4" @submit.prevent="submitTaskStage">
                    <div class="space-y-2">
                        <Label for="task-stage-name">{{
                            t.projects.stage_name
                        }}</Label>
                        <Input
                            id="task-stage-name"
                            v-model="taskStageForm.name"
                        />
                        <InputError :message="taskStageForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="task-stage-color">{{
                            t.projects.stage_color
                        }}</Label>
                        <Input
                            id="task-stage-color"
                            v-model="taskStageForm.color"
                            type="color"
                        />
                        <InputError :message="taskStageForm.errors.color" />
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-background/70 p-4"
                    >
                        <div class="text-sm font-medium">
                            {{ t.projects.stage_preview }}
                        </div>
                        <div
                            class="mt-3 rounded-2xl border border-border bg-card p-4"
                            :style="{
                                borderTopColor: taskStageForm.color,
                                borderTopWidth: '5px',
                            }"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2.5 rounded-full"
                                    :style="{
                                        backgroundColor: taskStageForm.color,
                                    }"
                                />
                                <span class="font-medium">
                                    {{
                                        taskStageForm.name.trim() ||
                                        t.projects.stage_preview_placeholder
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="taskStageForm.processing"
                        >
                            <Save class="size-4" />
                            {{ t.common.save }}
                        </Button>
                    </div>
                </form>
            </div>
        </SheetContent>
    </Sheet>

    <Sheet :open="taskSheetOpen" @update:open="handleTaskSheetOpenChange">
        <SheetContent
            side="right"
            class="w-full gap-0 p-0 sm:w-[80vw] sm:max-w-[80vw]"
        >
            <div
                v-if="taskEditorMode !== 'idle'"
                class="h-full min-h-0 overflow-y-auto bg-background"
            >
                <div class="mx-auto w-full max-w-4xl p-5 sm:p-8">
                    <div
                        class="rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-6"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <h2 class="text-base font-semibold">
                                {{
                                    taskEditorMode === 'create'
                                        ? t.projects.create_task
                                        : t.projects.edit_task
                                }}
                            </h2>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="resetTaskForm"
                            >
                                {{ t.common.cancel }}
                            </Button>
                        </div>

                        <form class="space-y-4" @submit.prevent="submitTask">
                            <div v-if="!isTasksPage" class="space-y-2">
                                <Label for="task-project">{{
                                    t.projects.task_location
                                }}</Label>
                                <select
                                    id="task-project"
                                    v-model="taskForm.project_id"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        {{ t.projects.standalone_task }}
                                    </option>
                                    <option
                                        v-for="project in props.availableProjects"
                                        :key="project.id"
                                        :value="project.id"
                                    >
                                        {{ project.name }}
                                    </option>
                                </select>
                                <InputError
                                    :message="taskForm.errors.project_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="task-parent">{{
                                    t.projects.parent_task
                                }}</Label>
                                <select
                                    id="task-parent"
                                    v-model="taskForm.parent_task_id"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="">
                                        {{ t.projects.no_parent_task }}
                                    </option>
                                    <option
                                        v-for="taskOption in parentTaskOptions"
                                        :key="taskOption.id"
                                        :value="taskOption.id"
                                    >
                                        {{ taskOption.label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="taskForm.errors.parent_task_id"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="task-title">{{
                                    t.projects.task_title
                                }}</Label>
                                <Input
                                    id="task-title"
                                    v-model="taskForm.title"
                                />
                                <InputError :message="taskForm.errors.title" />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="task-status">{{
                                        t.projects.status
                                    }}</Label>
                                    <select
                                        id="task-status"
                                        v-model="taskForm.status"
                                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option
                                            v-for="statusOption in props
                                                .taskOptions.statuses"
                                            :key="statusOption.value"
                                            :value="statusOption.value"
                                        >
                                            {{ statusOption.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="taskForm.errors.status"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="task-importance">{{
                                        t.projects.importance
                                    }}</Label>
                                    <select
                                        id="task-importance"
                                        v-model="taskForm.importance"
                                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option
                                            v-for="importanceOption in props
                                                .taskOptions.importances"
                                            :key="importanceOption.value"
                                            :value="importanceOption.value"
                                        >
                                            {{ importanceOption.label }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="taskForm.errors.importance"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="task-due">{{
                                        t.projects.due_date
                                    }}</Label>
                                    <Input
                                        id="task-due"
                                        v-model="taskForm.due_at"
                                        type="datetime-local"
                                        step="60"
                                    />
                                    <InputError
                                        :message="taskForm.errors.due_at"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="task-sort-order">{{
                                        t.projects.sort_order
                                    }}</Label>
                                    <Input
                                        id="task-sort-order"
                                        v-model.number="taskForm.sort_order"
                                        type="number"
                                        min="0"
                                    />
                                    <InputError
                                        :message="taskForm.errors.sort_order"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label for="task-complexity">{{
                                        t.projects.complexity
                                    }}</Label>
                                    <span
                                        class="rounded-full bg-background px-2 py-1 text-xs text-muted-foreground"
                                    >
                                        {{ taskForm.complexity }}/10
                                    </span>
                                </div>
                                <input
                                    id="task-complexity"
                                    v-model.number="taskForm.complexity"
                                    type="range"
                                    min="1"
                                    max="10"
                                    class="w-full accent-primary"
                                />
                                <InputError
                                    :message="taskForm.errors.complexity"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="task-description">{{
                                    t.projects.description_label
                                }}</Label>
                                <textarea
                                    id="task-description"
                                    v-model="taskForm.description"
                                    rows="6"
                                    class="min-h-32 w-full rounded-2xl border border-input bg-background px-3 py-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                ></textarea>
                                <InputError
                                    :message="taskForm.errors.description"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="task-assignee">{{
                                    t.projects.assignee
                                }}</Label>
                                <TaskUserPicker
                                    id="task-assignee"
                                    v-model="taskForm.assignee_user_id"
                                    :options="taskMemberOptions"
                                />
                                <InputError
                                    :message="taskForm.errors.assignee_user_id"
                                />
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm font-medium">
                                        {{ t.projects.co_assignees }}
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t.projects.co_assignees_help }}
                                    </p>
                                </div>

                                <TaskUserPicker
                                    v-model="taskForm.co_assignee_user_ids"
                                    multiple
                                    :options="taskMemberOptions"
                                    :exclude-user-ids="
                                        taskForm.assignee_user_id === ''
                                            ? []
                                            : [
                                                  Number(
                                                      taskForm.assignee_user_id,
                                                  ),
                                              ]
                                    "
                                />
                                <InputError
                                    :message="
                                        taskForm.errors.co_assignee_user_ids
                                    "
                                />
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="taskForm.processing"
                                >
                                    <Save class="size-4" />
                                    {{ t.common.save }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div
                v-else-if="props.activeTask"
                class="grid h-full min-h-0 bg-background xl:grid-cols-[minmax(0,1fr)_24rem] 2xl:grid-cols-[minmax(0,1fr)_28rem]"
            >
                <div class="min-h-0 overflow-y-auto p-5 sm:p-8">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-xs text-muted-foreground">
                                <span
                                    v-if="
                                        props.can.manageTask &&
                                        activeTaskSaveState === 'saving'
                                    "
                                >
                                    {{ t.projects.task_autosave_saving }}
                                </span>
                                <span
                                    v-else-if="
                                        props.can.manageTask &&
                                        activeTaskSaveState === 'saved'
                                    "
                                    class="text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ t.projects.task_autosave_saved }}
                                </span>
                            </div>

                            <Button
                                v-if="props.can.manageTask"
                                type="button"
                                size="sm"
                                variant="destructive"
                                @click="deleteCurrentTask"
                            >
                                <Trash2 class="size-4" />
                                {{ t.projects.delete_task }}
                            </Button>
                        </div>

                        <div
                            class="rounded-3xl border border-border bg-card p-5 shadow-sm"
                        >
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <ClipboardList
                                        class="size-5 text-muted-foreground"
                                    />
                                    <Input
                                        id="active-task-title"
                                        v-model="activeTaskForm.title"
                                        class="h-12 border-0 px-0 text-lg font-semibold shadow-none focus-visible:ring-0"
                                        :disabled="!props.can.manageTask"
                                        @change="handleActiveTaskFieldChange"
                                    />
                                </div>
                                <InputError
                                    :message="activeTaskForm.errors.title"
                                />

                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span
                                        class="rounded-full border px-2 py-1 font-medium"
                                        :style="
                                            taskStageBadgeStyle(
                                                activeTaskForm.status,
                                            )
                                        "
                                    >
                                        {{
                                            optionLabel(
                                                props.taskOptions.statuses,
                                                activeTaskForm.status,
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="rounded-full px-2 py-1 font-medium"
                                        :class="
                                            importanceClass(
                                                activeTaskForm.importance,
                                            )
                                        "
                                    >
                                        {{
                                            optionLabel(
                                                props.taskOptions.importances,
                                                activeTaskForm.importance,
                                            )
                                        }}
                                    </span>
                                    <span
                                        class="rounded-full bg-background px-2 py-1 text-muted-foreground"
                                    >
                                        {{ t.projects.complexity }}:
                                        {{ activeTaskForm.complexity }}/10
                                    </span>
                                    <span
                                        class="rounded-full bg-background px-2 py-1 text-muted-foreground"
                                    >
                                        {{
                                            projectOptionFor(
                                                selectedActiveTaskProjectId,
                                            )?.name ??
                                            t.projects.standalone_task
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-project"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.task_location }}
                                </Label>
                                <select
                                    id="active-task-project"
                                    v-model="activeTaskForm.project_id"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                >
                                    <option value="">
                                        {{ t.projects.standalone_task }}
                                    </option>
                                    <option
                                        v-for="project in props.availableProjects"
                                        :key="project.id"
                                        :value="project.id"
                                    >
                                        {{ project.name }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="activeTaskForm.errors.project_id"
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-parent"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.parent_task }}
                                </Label>
                                <select
                                    id="active-task-parent"
                                    v-model="activeTaskForm.parent_task_id"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                >
                                    <option value="">
                                        {{ t.projects.no_parent_task }}
                                    </option>
                                    <option
                                        v-for="taskOption in activeParentTaskOptions"
                                        :key="taskOption.id"
                                        :value="taskOption.id"
                                    >
                                        {{ taskOption.label }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="
                                        activeTaskForm.errors.parent_task_id
                                    "
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-status"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.status }}
                                </Label>
                                <select
                                    id="active-task-status"
                                    v-model="activeTaskForm.status"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                >
                                    <option
                                        v-for="statusOption in props.taskOptions
                                            .statuses"
                                        :key="statusOption.value"
                                        :value="statusOption.value"
                                    >
                                        {{ statusOption.label }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="activeTaskForm.errors.status"
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-importance"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.importance }}
                                </Label>
                                <select
                                    id="active-task-importance"
                                    v-model="activeTaskForm.importance"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                >
                                    <option
                                        v-for="importanceOption in props
                                            .taskOptions.importances"
                                        :key="importanceOption.value"
                                        :value="importanceOption.value"
                                    >
                                        {{ importanceOption.label }}
                                    </option>
                                </select>
                                <InputError
                                    class="mt-2"
                                    :message="activeTaskForm.errors.importance"
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-due"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.due_date }}
                                </Label>
                                <Input
                                    id="active-task-due"
                                    v-model="activeTaskForm.due_at"
                                    type="datetime-local"
                                    step="60"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                />
                                <div class="mt-2 text-xs text-muted-foreground">
                                    {{
                                        formatDateTime(props.activeTask.due_at)
                                    }}
                                </div>
                                <InputError
                                    class="mt-2"
                                    :message="activeTaskForm.errors.due_at"
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <Label
                                    for="active-task-sort-order"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    {{ t.projects.sort_order }}
                                </Label>
                                <Input
                                    id="active-task-sort-order"
                                    v-model.number="activeTaskForm.sort_order"
                                    type="number"
                                    min="0"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="activeTaskForm.errors.sort_order"
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <div
                                    class="mb-1 flex items-center gap-2 text-sm font-medium"
                                >
                                    <UserRound
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ t.projects.assignee }}
                                </div>
                                <TaskUserPicker
                                    id="active-task-assignee"
                                    v-model="activeTaskForm.assignee_user_id"
                                    class="mt-2"
                                    :options="activeTaskMemberOptions"
                                    :disabled="!props.can.manageTask"
                                    @change="handleActiveTaskFieldChange"
                                />
                                <InputError
                                    class="mt-2"
                                    :message="
                                        activeTaskForm.errors.assignee_user_id
                                    "
                                />
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-muted/15 p-4"
                            >
                                <div
                                    class="mb-1 flex items-center gap-2 text-sm font-medium"
                                >
                                    <Shield
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ t.projects.creator }}
                                </div>
                                <div class="mt-2 text-sm text-muted-foreground">
                                    {{ fullName(props.activeTask.creator) }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-muted/15 p-4"
                        >
                            <div
                                class="mb-2 flex items-center justify-between gap-3"
                            >
                                <Label
                                    for="active-task-complexity"
                                    class="text-sm font-medium"
                                >
                                    {{ t.projects.complexity }}
                                </Label>
                                <span
                                    class="rounded-full bg-background px-2 py-1 text-xs text-muted-foreground"
                                >
                                    {{ activeTaskForm.complexity }}/10
                                </span>
                            </div>
                            <input
                                id="active-task-complexity"
                                v-model.number="activeTaskForm.complexity"
                                type="range"
                                min="1"
                                max="10"
                                class="w-full accent-primary"
                                :disabled="!props.can.manageTask"
                                @change="handleActiveTaskFieldChange"
                            />
                            <InputError
                                class="mt-2"
                                :message="activeTaskForm.errors.complexity"
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-muted/15 p-4"
                        >
                            <Label
                                for="active-task-description"
                                class="mb-2 block text-sm font-medium"
                            >
                                {{ t.projects.description_label }}
                            </Label>
                            <textarea
                                id="active-task-description"
                                v-model="activeTaskForm.description"
                                rows="6"
                                class="min-h-32 w-full rounded-2xl border border-input bg-background px-3 py-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :disabled="!props.can.manageTask"
                                @change="handleActiveTaskFieldChange"
                            ></textarea>
                            <InputError
                                class="mt-2"
                                :message="activeTaskForm.errors.description"
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-muted/15 p-4"
                        >
                            <div
                                class="mb-3 flex items-center gap-2 text-sm font-medium"
                            >
                                <UsersRound
                                    class="size-4 text-muted-foreground"
                                />
                                {{ t.projects.co_assignees }}
                            </div>
                            <p class="mb-3 text-sm text-muted-foreground">
                                {{ t.projects.co_assignees_help }}
                            </p>

                            <TaskUserPicker
                                v-model="activeTaskForm.co_assignee_user_ids"
                                multiple
                                :options="activeTaskMemberOptions"
                                :disabled="!props.can.manageTask"
                                :exclude-user-ids="
                                    activeTaskForm.assignee_user_id === ''
                                        ? []
                                        : [
                                              Number(
                                                  activeTaskForm.assignee_user_id,
                                              ),
                                          ]
                                "
                                @change="handleActiveTaskCoAssigneeChange"
                            />
                            <InputError
                                class="mt-2"
                                :message="
                                    activeTaskForm.errors.co_assignee_user_ids
                                "
                            />
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-muted/15 p-4"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-3"
                            >
                                <div class="text-sm font-medium">
                                    {{ t.projects.subtasks }}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="openCreateSubtaskFromActiveTask"
                                >
                                    <GitBranchPlus class="size-4" />
                                    {{ t.projects.create_subtask }}
                                </Button>
                            </div>

                            <div
                                v-if="props.activeTask.subtasks.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t.projects.no_subtasks_description }}
                            </div>

                            <div v-else class="space-y-2">
                                <Link
                                    v-for="subtask in props.activeTask.subtasks"
                                    :key="subtask.id"
                                    :href="taskHrefResolver(subtask)"
                                    class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card px-3 py-3 transition hover:border-primary/40 hover:bg-background"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ subtask.title }}
                                        </div>
                                        <div
                                            class="mt-1 flex flex-wrap gap-2 text-xs"
                                        >
                                            <span
                                                class="rounded-full border px-2 py-1"
                                                :style="
                                                    taskStageBadgeStyle(
                                                        subtask.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    optionLabel(
                                                        props.taskOptions
                                                            .statuses,
                                                        subtask.status,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="rounded-full px-2 py-1"
                                                :class="
                                                    importanceClass(
                                                        subtask.importance,
                                                    )
                                                "
                                            >
                                                {{
                                                    optionLabel(
                                                        props.taskOptions
                                                            .importances,
                                                        subtask.importance,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <span class="text-xs text-muted-foreground">
                                        {{ fullName(subtask.assignee) }}
                                    </span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <ProjectTaskConversationPanel
                    :active="taskSheetOpen"
                    :task-id="props.activeTask.id"
                />
            </div>
        </SheetContent>
    </Sheet>
</template>
