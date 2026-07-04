<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Blocks,
    Bot,
    Braces,
    CalendarClock,
    Copy,
    GitBranchPlus,
    Layers3,
    PlayCircle,
    Plus,
    Save,
    Sparkles,
    Trash2,
    Workflow,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/settings/business-processes';

type ProcessLane = {
    id: string;
    title: string;
    color: string;
};

type ProcessNodeConfig = {
    code: string | null;
    action_key: string | null;
    condition_expression: string | null;
    notes: string | null;
    input_mapping: string | null;
    output_mapping: string | null;
    retry_limit: number;
    timeout_seconds: number;
};

type ProcessNode = {
    id: string;
    type: string;
    lane_id: string;
    label: string;
    description: string | null;
    x: number;
    y: number;
    config: ProcessNodeConfig;
};

type ProcessEdge = {
    id: string;
    source: string;
    target: string;
    label: string | null;
    condition: string | null;
};

type ProcessDefinition = {
    viewport: {
        width: number;
        height: number;
    };
    lanes: ProcessLane[];
    nodes: ProcessNode[];
    edges: ProcessEdge[];
};

type ProcessSummary = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    trigger_type: string;
    trigger_event: string;
    is_active: boolean;
    version: number;
    updated_at: string | null;
    last_published_at: string | null;
    nodes_count: number;
    edges_count: number;
};

type ActiveProcess = ProcessSummary & {
    definition: ProcessDefinition;
    creator: {
        id: number;
        name: string;
        email: string;
    } | null;
    updater: {
        id: number;
        name: string;
        email: string;
    } | null;
};

type TriggerTypeOption = {
    key: string;
    title: string;
    description: string;
};

type TriggerEventOption = {
    key: string;
    type: string;
    category: string;
    title: string;
    description: string;
};

type NodeTypeOption = {
    key: string;
    title: string;
    description: string;
    shape: string;
    accent: string;
    supports_code: boolean;
    supports_action: boolean;
    supports_condition: boolean;
};

type ApiActionOption = {
    key: string;
    category: string;
    method: string;
    path: string;
    title: string;
    description: string;
    permission: string;
};

type ProcessTemplate = {
    name: string;
    description: string;
    trigger_type: string;
    trigger_event: string;
    definition: ProcessDefinition;
};

const props = defineProps<{
    summary: {
        total: number;
        active: number;
        automated: number;
        codeNodes: number;
    };
    processes: ProcessSummary[];
    activeProcess: ActiveProcess | null;
    catalog: {
        triggerTypes: TriggerTypeOption[];
        triggerEvents: TriggerEventOption[];
        nodeTypes: NodeTypeOption[];
        apiActions: ApiActionOption[];
        templates: ProcessTemplate[];
    };
    defaults: {
        trigger_type: string;
        trigger_event: string;
        definition: ProcessDefinition;
    };
}>();

const { language, t } = useLanguage();

const deepClone = <T>(value: T): T => {
    return JSON.parse(JSON.stringify(value)) as T;
};

const blankConfig = (): ProcessNodeConfig => ({
    code: null,
    action_key: null,
    condition_expression: null,
    notes: null,
    input_mapping: null,
    output_mapping: null,
    retry_limit: 0,
    timeout_seconds: 30,
});

const blankProcess = (): {
    name: string;
    description: string;
    trigger_type: string;
    trigger_event: string;
    is_active: boolean;
    definition: ProcessDefinition;
} => ({
    name: '',
    description: '',
    trigger_type: props.defaults.trigger_type,
    trigger_event: props.defaults.trigger_event,
    is_active: true,
    definition: deepClone(props.defaults.definition),
});

const processDefaults = (
    activeProcess: ActiveProcess | null,
): {
    name: string;
    description: string;
    trigger_type: string;
    trigger_event: string;
    is_active: boolean;
    definition: ProcessDefinition;
} => {
    if (!activeProcess) {
        return blankProcess();
    }

    return {
        name: activeProcess.name,
        description: activeProcess.description ?? '',
        trigger_type: activeProcess.trigger_type,
        trigger_event: activeProcess.trigger_event,
        is_active: activeProcess.is_active,
        definition: deepClone(activeProcess.definition),
    };
};

const editorMode = ref<'create' | 'edit'>(props.activeProcess ? 'edit' : 'create');
const selectedNodeId = ref<string | null>(null);
const connectTargetId = ref<string | null>(null);
const form = useForm(processDefaults(props.activeProcess));

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.business_processes.title,
                href: index(),
            },
        ],
    });
});

watch(
    () => props.activeProcess,
    (activeProcess) => {
        editorMode.value = activeProcess ? 'edit' : 'create';
        form.defaults(processDefaults(activeProcess));
        form.reset();
        form.clearErrors();

        selectedNodeId.value = activeProcess?.definition.nodes[0]?.id ?? null;
        connectTargetId.value = null;
    },
    { immediate: true },
);

const summaryCards = computed(() => {
    return [
        {
            key: 'total',
            icon: Layers3,
            label: t.value.business_processes.summary_total,
            value: props.summary.total,
        },
        {
            key: 'active',
            icon: PlayCircle,
            label: t.value.business_processes.summary_active,
            value: props.summary.active,
        },
        {
            key: 'automated',
            icon: Bot,
            label: t.value.business_processes.summary_automated,
            value: props.summary.automated,
        },
        {
            key: 'code',
            icon: Braces,
            label: t.value.business_processes.summary_code_nodes,
            value: props.summary.codeNodes,
        },
    ];
});

const processTemplates = computed(() => props.catalog.templates);

const triggerEventsForSelectedType = computed(() => {
    return props.catalog.triggerEvents.filter(
        (event) => event.type === form.trigger_type,
    );
});

const selectedNode = computed<ProcessNode | null>(() => {
    return (
        form.definition.nodes.find((node) => node.id === selectedNodeId.value) ??
        null
    );
});

const selectedNodeType = computed<NodeTypeOption | null>(() => {
    if (!selectedNode.value) {
        return null;
    }

    return (
        props.catalog.nodeTypes.find(
            (nodeType) => nodeType.key === selectedNode.value?.type,
        ) ?? null
    );
});

const selectedAction = computed<ApiActionOption | null>(() => {
    if (!selectedNode.value) {
        return null;
    }

    return (
        props.catalog.apiActions.find(
            (action) =>
                action.key === selectedNode.value?.config.action_key,
        ) ?? null
    );
});

const laneOptions = computed(() => form.definition.lanes);

const nodeOptions = computed(() => form.definition.nodes);

const canvasLanes = computed(() => {
    const lanesCount = Math.max(form.definition.lanes.length, 1);
    const laneHeight = Math.floor(form.definition.viewport.height / lanesCount);

    return form.definition.lanes.map((lane, index) => ({
        ...lane,
        top: index * laneHeight,
        height:
            index === lanesCount - 1
                ? form.definition.viewport.height - index * laneHeight
                : laneHeight,
    }));
});

const nodeDimensions = (type: string): { width: number; height: number } => {
    if (type === 'startEvent' || type === 'endEvent') {
        return { width: 96, height: 96 };
    }

    if (type === 'conditionGateway') {
        return { width: 120, height: 120 };
    }

    return { width: 210, height: 92 };
};

const canvasNodes = computed(() => {
    return form.definition.nodes.map((node) => {
        const nodeType = props.catalog.nodeTypes.find(
            (item) => item.key === node.type,
        );
        const dimensions = nodeDimensions(node.type);

        return {
            ...node,
            width: dimensions.width,
            height: dimensions.height,
            accent: nodeType?.accent ?? '#94A3B8',
            shape: nodeType?.shape ?? 'rounded',
            title: nodeType?.title ?? node.type,
            typeDescription: nodeType?.description ?? '',
        };
    });
});

const canvasEdges = computed(() => {
    return form.definition.edges
        .map((edge) => {
            const source = canvasNodes.value.find(
                (node) => node.id === edge.source,
            );
            const target = canvasNodes.value.find(
                (node) => node.id === edge.target,
            );

            if (!source || !target) {
                return null;
            }

            return {
                ...edge,
                x1: source.x + source.width,
                y1: source.y + source.height / 2,
                x2: target.x,
                y2: target.y + target.height / 2,
                labelX:
                    source.x +
                    source.width +
                    (target.x - (source.x + source.width)) / 2,
                labelY:
                    source.y +
                    source.height / 2 +
                    (target.y + target.height / 2 - (source.y + source.height / 2)) /
                        2 -
                    8,
            };
        })
        .filter((edge): edge is NonNullable<typeof edge> => edge !== null);
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.business_processes.not_published;
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const nextNodeId = (type: string): string => {
    return `${type}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
};

const nextEdgeId = (): string => {
    return `edge_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
};

const laneIndex = (laneId: string): number => {
    return Math.max(
        form.definition.lanes.findIndex((lane) => lane.id === laneId),
        0,
    );
};

const defaultNodeLabel = (type: string): string => {
    return (
        props.catalog.nodeTypes.find((nodeType) => nodeType.key === type)
            ?.title ?? type
    );
};

const buildNode = (type: string, laneId?: string): ProcessNode => {
    const resolvedLaneId = laneId ?? form.definition.lanes[0]?.id ?? 'processing';
    const indexInLane = form.definition.nodes.filter(
        (node) => node.lane_id === resolvedLaneId,
    ).length;

    return {
        id: nextNodeId(type),
        type,
        lane_id: resolvedLaneId,
        label: defaultNodeLabel(type),
        description: null,
        x: 80 + (indexInLane % 3) * 250,
        y: 60 + laneIndex(resolvedLaneId) * 210 + Math.floor(indexInLane / 3) * 120,
        config: blankConfig(),
    };
};

const selectProcess = (processId: number): void => {
    router.get(
        index.url({
            query: {
                process: processId,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const openCreateProcess = (): void => {
    editorMode.value = 'create';
    form.defaults(blankProcess());
    form.reset();
    form.clearErrors();
    selectedNodeId.value = form.definition.nodes[0]?.id ?? null;
    connectTargetId.value = null;
};

const applyTemplate = (template: ProcessTemplate): void => {
    editorMode.value = 'create';
    form.defaults({
        name: template.name,
        description: template.description,
        trigger_type: template.trigger_type,
        trigger_event: template.trigger_event,
        is_active: true,
        definition: deepClone(template.definition),
    });
    form.reset();
    form.clearErrors();
    selectedNodeId.value = form.definition.nodes[0]?.id ?? null;
    connectTargetId.value = null;
};

const addLane = (): void => {
    const nextNumber = form.definition.lanes.length + 1;

    form.definition.lanes.push({
        id: `lane_${nextNumber}_${Math.random().toString(36).slice(2, 5)}`,
        title: t.value.business_processes.lane_title.replace(
            ':number',
            String(nextNumber),
        ),
        color: ['#E5F8EA', '#DDEAFE', '#FFF1D6', '#FCE7F3'][
            (nextNumber - 1) % 4
        ],
    });
    form.definition.viewport.height = Math.max(
        680,
        form.definition.lanes.length * 200,
    );
};

const removeLane = (laneId: string): void => {
    if (form.definition.lanes.length <= 1) {
        return;
    }

    const fallbackLaneId =
        form.definition.lanes.find((lane) => lane.id !== laneId)?.id ??
        form.definition.lanes[0].id;

    form.definition.lanes = form.definition.lanes.filter(
        (lane) => lane.id !== laneId,
    );
    form.definition.nodes = form.definition.nodes.map((node) => ({
        ...node,
        lane_id: node.lane_id === laneId ? fallbackLaneId : node.lane_id,
    }));
};

const addNode = (type: string): void => {
    const baseLaneId =
        selectedNode.value?.lane_id ?? form.definition.lanes[0]?.id ?? 'processing';
    const node = buildNode(type, baseLaneId);

    if (type === 'apiAction') {
        node.config.action_key = props.catalog.apiActions[0]?.key ?? null;
    }

    if (type === 'conditionGateway') {
        node.config.condition_expression = 'payload.ok === true';
    }

    if (type === 'codeTask') {
        node.config.code = "return ['ok' => true];";
    }

    form.definition.nodes.push(node);
    selectedNodeId.value = node.id;
    connectTargetId.value = null;
};

const duplicateSelectedNode = (): void => {
    if (!selectedNode.value) {
        return;
    }

    const clone = deepClone(selectedNode.value);
    clone.id = nextNodeId(clone.type);
    clone.label = `${clone.label} ${t.value.business_processes.copy_suffix}`;
    clone.x += 40;
    clone.y += 40;

    form.definition.nodes.push(clone);
    selectedNodeId.value = clone.id;
};

const removeSelectedNode = (): void => {
    if (!selectedNode.value) {
        return;
    }

    const nodeId = selectedNode.value.id;

    form.definition.nodes = form.definition.nodes.filter(
        (node) => node.id !== nodeId,
    );
    form.definition.edges = form.definition.edges.filter(
        (edge) => edge.source !== nodeId && edge.target !== nodeId,
    );
    selectedNodeId.value = form.definition.nodes[0]?.id ?? null;
    connectTargetId.value = null;
};

const updateSelectedNode = (
    updater: (node: ProcessNode) => ProcessNode,
): void => {
    if (!selectedNode.value) {
        return;
    }

    form.definition.nodes = form.definition.nodes.map((node) =>
        node.id === selectedNode.value?.id ? updater(node) : node,
    );
};

const optionalTextareaValue = (event: Event): string | null => {
    const target = event.target as HTMLTextAreaElement | null;
    const value = target?.value.trim() ?? '';

    return value !== '' ? target?.value ?? null : null;
};

const selectedValue = (event: Event): string => {
    const target = event.target as HTMLSelectElement | null;

    return target?.value ?? '';
};

const nudgeSelectedNode = (xDelta: number, yDelta: number): void => {
    updateSelectedNode((node) => ({
        ...node,
        x: Math.max(0, node.x + xDelta),
        y: Math.max(0, node.y + yDelta),
    }));
};

const connectSelectedNode = (): void => {
    if (!selectedNode.value || !connectTargetId.value) {
        return;
    }

    if (
        form.definition.edges.some(
            (edge) =>
                edge.source === selectedNode.value?.id &&
                edge.target === connectTargetId.value,
        )
    ) {
        return;
    }

    form.definition.edges.push({
        id: nextEdgeId(),
        source: selectedNode.value.id,
        target: connectTargetId.value,
        label: null,
        condition:
            selectedNode.value.type === 'conditionGateway'
                ? 'payload.ok === true'
                : null,
    });
    connectTargetId.value = null;
};

const removeEdge = (edgeId: string): void => {
    form.definition.edges = form.definition.edges.filter(
        (edge) => edge.id !== edgeId,
    );
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
    };

    if (editorMode.value === 'edit' && props.activeProcess) {
        form.patch(update.url(props.activeProcess.id), options);

        return;
    }

    form.post(store.url(), options);
};

const deleteActiveProcess = (): void => {
    if (!props.activeProcess) {
        return;
    }

    router.delete(destroy.url(props.activeProcess.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t.business_processes.title" />

    <h1 class="sr-only">{{ t.business_processes.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.business_processes.title"
            :description="t.business_processes.description"
        />

        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">
            <article
                v-for="card in summaryCards"
                :key="card.key"
                class="rounded-2xl border border-border bg-card p-5 shadow-xs"
            >
                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ card.value }}
                        </p>
                    </div>
                    <div
                        class="flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <component :is="card.icon" class="size-5" />
                    </div>
                </div>
            </article>
        </div>

        <div class="grid gap-6 2xl:grid-cols-[320px_minmax(0,1fr)_360px]">
            <aside class="space-y-5">
                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-center justify-between gap-4">
                        <div class="space-y-1">
                            <h2 class="font-semibold">
                                {{ t.business_processes.library_title }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ t.business_processes.library_description }}
                            </p>
                        </div>
                        <Button size="sm" @click="openCreateProcess">
                            <Plus class="mr-2 size-4" />
                            {{ t.business_processes.new_process }}
                        </Button>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <Sparkles class="size-4 text-primary" />
                        <h2 class="font-semibold">
                            {{ t.business_processes.templates_title }}
                        </h2>
                    </div>
                    <div class="mt-4 space-y-3">
                        <button
                            v-for="template in processTemplates"
                            :key="template.name"
                            type="button"
                            class="w-full rounded-2xl border border-border bg-background/60 p-4 text-left transition hover:border-primary/40 hover:bg-primary/5"
                            @click="applyTemplate(template)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <h3 class="font-medium">
                                        {{ template.name }}
                                    </h3>
                                    <p class="text-sm leading-6 text-muted-foreground">
                                        {{ template.description }}
                                    </p>
                                </div>
                                <ArrowRight class="mt-1 size-4 shrink-0 text-muted-foreground" />
                            </div>
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <Workflow class="size-4 text-primary" />
                        <h2 class="font-semibold">
                            {{ t.business_processes.processes_title }}
                        </h2>
                    </div>

                    <div class="mt-4 space-y-3">
                        <button
                            v-for="process in props.processes"
                            :key="process.id"
                            type="button"
                            class="w-full rounded-2xl border p-4 text-left transition"
                            :class="
                                process.id === props.activeProcess?.id
                                    ? 'border-primary bg-primary/6'
                                    : 'border-border bg-background/60 hover:border-primary/40'
                            "
                            @click="selectProcess(process.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1">
                                    <h3 class="truncate font-medium">
                                        {{ process.name }}
                                    </h3>
                                    <p class="line-clamp-2 text-sm text-muted-foreground">
                                        {{
                                            process.description ||
                                            t.business_processes.empty_process_description
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        process.is_active
                                            ? 'bg-emerald-500/10 text-emerald-700'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{
                                        process.is_active
                                            ? t.business_processes.status_active
                                            : t.business_processes.status_draft
                                    }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    {{ t.business_processes.version_label }}
                                    {{ process.version }}
                                </span>
                                <span>
                                    {{ process.nodes_count }}
                                    {{ t.business_processes.nodes_short }}
                                </span>
                            </div>
                        </button>

                        <p
                            v-if="props.processes.length === 0"
                            class="rounded-2xl border border-dashed border-border p-5 text-sm text-muted-foreground"
                        >
                            {{ t.business_processes.empty_state }}
                        </p>
                    </div>
                </section>
            </aside>

            <section class="space-y-5">
                <div class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <GitBranchPlus class="size-4 text-primary" />
                                <h2 class="font-semibold">
                                    {{ t.business_processes.process_settings_title }}
                                </h2>
                            </div>
                            <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                                {{ t.business_processes.process_settings_help }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" @click="openCreateProcess">
                                <Copy class="mr-2 size-4" />
                                {{ t.business_processes.reset_to_new }}
                            </Button>
                            <Button
                                variant="destructive"
                                :disabled="!props.activeProcess || editorMode !== 'edit'"
                                @click="deleteActiveProcess"
                            >
                                <Trash2 class="mr-2 size-4" />
                                {{ t.business_processes.delete_process }}
                            </Button>
                            <Button :disabled="form.processing" @click="submit">
                                <Save class="mr-2 size-4" />
                                {{ t.common.save }}
                            </Button>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="process-name">
                                {{ t.business_processes.process_name }}
                            </Label>
                            <Input
                                id="process-name"
                                v-model="form.name"
                                :placeholder="t.business_processes.process_name_placeholder"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="process-trigger-type">
                                {{ t.business_processes.trigger_type_label }}
                            </Label>
                            <select
                                id="process-trigger-type"
                                v-model="form.trigger_type"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                            >
                                <option
                                    v-for="triggerType in props.catalog.triggerTypes"
                                    :key="triggerType.key"
                                    :value="triggerType.key"
                                >
                                    {{ triggerType.title }}
                                </option>
                            </select>
                            <InputError :message="form.errors.trigger_type" />
                        </div>

                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="process-description">
                                {{ t.business_processes.process_description }}
                            </Label>
                            <textarea
                                id="process-description"
                                v-model="form.description"
                                rows="3"
                                class="border-input bg-transparent placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                :placeholder="t.business_processes.process_description_placeholder"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="process-trigger-event">
                                {{ t.business_processes.trigger_event_label }}
                            </Label>
                            <select
                                id="process-trigger-event"
                                v-model="form.trigger_event"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                            >
                                <option
                                    v-for="event in triggerEventsForSelectedType"
                                    :key="event.key"
                                    :value="event.key"
                                >
                                    {{ event.category }} • {{ event.title }}
                                </option>
                            </select>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    triggerEventsForSelectedType.find(
                                        (event) =>
                                            event.key === form.trigger_event,
                                    )?.description
                                }}
                            </p>
                            <InputError :message="form.errors.trigger_event" />
                        </div>

                        <label class="flex items-center gap-3 lg:col-span-2">
                            <Checkbox
                                :checked="form.is_active"
                                @update:checked="
                                    (
                                        value:
                                            | boolean
                                            | 'indeterminate'
                                            | null
                                            | undefined,
                                    ) =>
                                        (form.is_active = value === true)
                                "
                            />
                            <span class="text-sm">
                                {{
                                    form.is_active
                                        ? t.business_processes.status_active_help
                                        : t.business_processes.status_draft_help
                                }}
                            </span>
                        </label>
                    </div>
                </div>

                <div class="rounded-[28px] border border-border bg-card p-5 shadow-xs">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Blocks class="size-4 text-primary" />
                                <h2 class="font-semibold">
                                    {{ t.business_processes.canvas_title }}
                                </h2>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.business_processes.canvas_help }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="nodeType in props.catalog.nodeTypes"
                                :key="nodeType.key"
                                size="sm"
                                variant="outline"
                                @click="addNode(nodeType.key)"
                            >
                                <Plus class="mr-2 size-4" />
                                {{ nodeType.title }}
                            </Button>
                            <Button size="sm" variant="outline" @click="addLane">
                                <Layers3 class="mr-2 size-4" />
                                {{ t.business_processes.add_lane }}
                            </Button>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto rounded-[24px] border border-border bg-background/60 p-4">
                        <div
                            class="relative overflow-hidden rounded-[24px] border border-dashed border-border/80 bg-[radial-gradient(circle_at_top_left,rgba(59,130,246,0.08),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(16,185,129,0.08),transparent_30%)]"
                            :style="{
                                width: `${form.definition.viewport.width}px`,
                                height: `${form.definition.viewport.height}px`,
                            }"
                        >
                            <div
                                v-for="lane in canvasLanes"
                                :key="lane.id"
                                class="absolute inset-x-0 border-b border-border/60"
                                :style="{
                                    top: `${lane.top}px`,
                                    height: `${lane.height}px`,
                                    backgroundColor: lane.color,
                                }"
                            >
                                <div class="px-6 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-slate-800">
                                                {{ lane.title }}
                                            </p>
                                            <p class="text-xs text-slate-600">
                                                {{ lane.id }}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="bg-white/80"
                                            @click="removeLane(lane.id)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <svg
                                class="pointer-events-none absolute inset-0 h-full w-full"
                                :viewBox="`0 0 ${form.definition.viewport.width} ${form.definition.viewport.height}`"
                            >
                                <defs>
                                    <marker
                                        id="bp-arrow"
                                        markerWidth="10"
                                        markerHeight="10"
                                        refX="9"
                                        refY="5"
                                        orient="auto"
                                    >
                                        <path d="M 0 0 L 10 5 L 0 10 z" fill="#64748B" />
                                    </marker>
                                </defs>
                                <g v-for="edge in canvasEdges" :key="edge.id">
                                    <line
                                        :x1="edge.x1"
                                        :y1="edge.y1"
                                        :x2="edge.x2"
                                        :y2="edge.y2"
                                        stroke="#64748B"
                                        stroke-width="2.5"
                                        marker-end="url(#bp-arrow)"
                                    />
                                    <text
                                        v-if="edge.label"
                                        :x="edge.labelX"
                                        :y="edge.labelY"
                                        fill="#475569"
                                        font-size="12"
                                        text-anchor="middle"
                                    >
                                        {{ edge.label }}
                                    </text>
                                </g>
                            </svg>

                            <button
                                v-for="node in canvasNodes"
                                :key="node.id"
                                type="button"
                                class="absolute flex items-center justify-center text-center transition"
                                :class="
                                    node.id === selectedNodeId
                                        ? 'ring-4 ring-primary/20'
                                        : 'hover:scale-[1.01]'
                                "
                                :style="{
                                    left: `${node.x}px`,
                                    top: `${node.y}px`,
                                    width: `${node.width}px`,
                                    height: `${node.height}px`,
                                }"
                                @click="selectedNodeId = node.id"
                            >
                                <div
                                    v-if="node.shape === 'circle'"
                                    class="flex size-full flex-col items-center justify-center rounded-full border-4 bg-white px-3 shadow-lg"
                                    :style="{ borderColor: node.accent }"
                                >
                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        {{ node.title }}
                                    </span>
                                    <span class="mt-1 text-sm font-semibold leading-5 text-slate-900">
                                        {{ node.label }}
                                    </span>
                                </div>

                                <div
                                    v-else-if="node.shape === 'diamond'"
                                    class="relative flex size-full items-center justify-center"
                                >
                                    <div
                                        class="absolute inset-[14px] rotate-45 rounded-3xl border-4 bg-white shadow-lg"
                                        :style="{ borderColor: node.accent }"
                                    />
                                    <div class="relative max-w-[90px] -rotate-0 space-y-1 px-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                            {{ node.title }}
                                        </p>
                                        <p class="text-sm font-semibold leading-4 text-slate-900">
                                            {{ node.label }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="flex size-full flex-col items-start justify-between rounded-[28px] border-4 bg-white p-4 text-left shadow-lg"
                                    :style="{ borderColor: node.accent }"
                                >
                                    <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        {{ node.title }}
                                    </span>
                                    <div class="space-y-1">
                                        <p class="text-base font-semibold leading-5 text-slate-900">
                                            {{ node.label }}
                                        </p>
                                        <p class="line-clamp-2 text-xs leading-5 text-slate-500">
                                            {{
                                                node.description ||
                                                node.typeDescription ||
                                                t.business_processes.node_placeholder
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="flex items-center gap-2">
                                <ArrowRight class="size-4 text-primary" />
                                <h3 class="font-medium">
                                    {{ t.business_processes.connections_title }}
                                </h3>
                            </div>
                            <div class="mt-4 space-y-3">
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <select
                                        v-model="connectTargetId"
                                        class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-10 flex-1 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    >
                                        <option :value="null">
                                            {{ t.business_processes.select_target }}
                                        </option>
                                        <option
                                            v-for="node in nodeOptions.filter((item) => item.id !== selectedNodeId)"
                                            :key="node.id"
                                            :value="node.id"
                                        >
                                            {{ node.label }}
                                        </option>
                                    </select>
                                    <Button
                                        :disabled="!selectedNodeId || !connectTargetId"
                                        @click="connectSelectedNode"
                                    >
                                        {{ t.business_processes.connect_nodes }}
                                    </Button>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="edge in canvasEdges"
                                        :key="edge.id"
                                        class="flex items-center justify-between gap-3 rounded-2xl border border-border px-4 py-3"
                                    >
                                        <div class="min-w-0 space-y-1">
                                            <p class="truncate text-sm font-medium">
                                                {{
                                                    form.definition.nodes.find(
                                                        (node) =>
                                                            node.id ===
                                                            edge.source,
                                                    )?.label
                                                }}
                                                →
                                                {{
                                                    form.definition.nodes.find(
                                                        (node) =>
                                                            node.id ===
                                                            edge.target,
                                                    )?.label
                                                }}
                                            </p>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{
                                                    edge.condition ||
                                                    edge.label ||
                                                    t.business_processes.direct_flow
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="removeEdge(edge.id)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="form.errors['definition.edges']" />
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="flex items-center gap-2">
                                <CalendarClock class="size-4 text-primary" />
                                <h3 class="font-medium">
                                    {{ t.business_processes.meta_title }}
                                </h3>
                            </div>
                            <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                                <p>
                                    {{ t.business_processes.version_label }}
                                    {{
                                        props.activeProcess?.version ??
                                        t.business_processes.new_process_badge
                                    }}
                                </p>
                                <p>
                                    {{ t.business_processes.last_updated_label }}
                                    {{
                                        formatDateTime(
                                            props.activeProcess?.updated_at ??
                                                null,
                                        )
                                    }}
                                </p>
                                <p>
                                    {{ t.business_processes.last_published_label }}
                                    {{
                                        formatDateTime(
                                            props.activeProcess
                                                ?.last_published_at ?? null,
                                        )
                                    }}
                                </p>
                                <p v-if="props.activeProcess?.updater">
                                    {{
                                        t.business_processes.updated_by_label.replace(
                                            ':name',
                                            props.activeProcess.updater.name,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <InputError :message="form.errors['definition.nodes']" />
                </div>
            </section>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-center justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="font-semibold">
                                {{ t.business_processes.node_settings_title }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ t.business_processes.node_settings_help }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="!selectedNode"
                            @click="duplicateSelectedNode"
                        >
                            <Copy class="mr-2 size-4" />
                            {{ t.business_processes.duplicate_node }}
                        </Button>
                    </div>

                    <div v-if="selectedNode" class="mt-5 space-y-4">
                        <div class="grid gap-2">
                            <Label for="node-label">
                                {{ t.business_processes.node_label }}
                            </Label>
                            <Input
                                id="node-label"
                                :model-value="selectedNode.label"
                                @update:model-value="
                                    (value) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            label:
                                                typeof value === 'string'
                                                    ? value
                                                    : node.label,
                                        }))
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="node-description">
                                {{ t.business_processes.node_description }}
                            </Label>
                            <textarea
                                id="node-description"
                                :value="selectedNode.description ?? ''"
                                rows="3"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                @input="
                                    (event) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            description:
                                                optionalTextareaValue(event),
                                        }))
                                "
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="node-lane">
                                    {{ t.business_processes.lane_label }}
                                </Label>
                                <select
                                    id="node-lane"
                                    :value="selectedNode.lane_id"
                                    class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    @change="
                                        (event) =>
                                            updateSelectedNode((node) => ({
                                                ...node,
                                                lane_id:
                                                    selectedValue(event) ||
                                                    node.lane_id,
                                            }))
                                    "
                                >
                                    <option
                                        v-for="lane in laneOptions"
                                        :key="lane.id"
                                        :value="lane.id"
                                    >
                                        {{ lane.title }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="node-type">
                                    {{ t.business_processes.node_type_label }}
                                </Label>
                                <Input
                                    id="node-type"
                                    :model-value="selectedNodeType?.title ?? selectedNode.type"
                                    disabled
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label>{{ t.business_processes.position_x }}</Label>
                                <div class="flex gap-2">
                                    <Input
                                        type="number"
                                        :model-value="selectedNode.x"
                                        @update:model-value="
                                            (value) =>
                                                updateSelectedNode((node) => ({
                                                    ...node,
                                                    x:
                                                        typeof value === 'number'
                                                            ? value
                                                            : Number(value),
                                                }))
                                        "
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="nudgeSelectedNode(-20, 0)"
                                    >
                                        −
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="nudgeSelectedNode(20, 0)"
                                    >
                                        +
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ t.business_processes.position_y }}</Label>
                                <div class="flex gap-2">
                                    <Input
                                        type="number"
                                        :model-value="selectedNode.y"
                                        @update:model-value="
                                            (value) =>
                                                updateSelectedNode((node) => ({
                                                    ...node,
                                                    y:
                                                        typeof value === 'number'
                                                            ? value
                                                            : Number(value),
                                                }))
                                        "
                                    />
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="nudgeSelectedNode(0, -20)"
                                    >
                                        −
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="nudgeSelectedNode(0, 20)"
                                    >
                                        +
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="selectedNodeType?.supports_action"
                            class="grid gap-2"
                        >
                            <Label for="node-action">
                                {{ t.business_processes.api_action_label }}
                            </Label>
                            <select
                                id="node-action"
                                :value="selectedNode.config.action_key ?? ''"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-10 rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                @change="
                                    (event) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            config: {
                                                ...node.config,
                                                action_key:
                                                    selectedValue(event) !== ''
                                                        ? selectedValue(event)
                                                        : null,
                                            },
                                        }))
                                "
                            >
                                <option
                                    v-for="action in props.catalog.apiActions"
                                    :key="action.key"
                                    :value="action.key"
                                >
                                    {{ action.method }} {{ action.path }}
                                </option>
                            </select>
                            <div
                                v-if="selectedAction"
                                class="rounded-2xl border border-border bg-background/70 p-4 text-sm"
                            >
                                <p class="font-medium">{{ selectedAction.title }}</p>
                                <p class="mt-1 text-muted-foreground">
                                    {{ selectedAction.description }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ selectedAction.permission }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="selectedNodeType?.supports_code"
                            class="grid gap-2"
                        >
                            <Label for="node-code">
                                {{ t.business_processes.code_label }}
                            </Label>
                            <textarea
                                id="node-code"
                                :value="selectedNode.config.code ?? ''"
                                rows="10"
                                class="min-h-56 rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 font-mono text-sm text-slate-100 outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-primary/50"
                                @input="
                                    (event) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            config: {
                                                ...node.config,
                                                code: optionalTextareaValue(event),
                                            },
                                        }))
                                "
                            />
                        </div>

                        <div
                            v-if="selectedNodeType?.supports_condition"
                            class="grid gap-2"
                        >
                            <Label for="node-condition">
                                {{ t.business_processes.condition_label }}
                            </Label>
                            <textarea
                                id="node-condition"
                                :value="
                                    selectedNode.config.condition_expression ?? ''
                                "
                                rows="4"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border px-3 py-2 font-mono text-sm outline-none focus-visible:ring-[3px]"
                                @input="
                                    (event) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            config: {
                                                ...node.config,
                                                condition_expression:
                                                    optionalTextareaValue(event),
                                            },
                                        }))
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="node-notes">
                                {{ t.business_processes.notes_label }}
                            </Label>
                            <textarea
                                id="node-notes"
                                :value="selectedNode.config.notes ?? ''"
                                rows="3"
                                class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                @input="
                                    (event) =>
                                        updateSelectedNode((node) => ({
                                            ...node,
                                            config: {
                                                ...node.config,
                                                notes: optionalTextareaValue(event),
                                            },
                                        }))
                                "
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="node-input-mapping">
                                    {{ t.business_processes.input_mapping_label }}
                                </Label>
                                <textarea
                                    id="node-input-mapping"
                                    :value="selectedNode.config.input_mapping ?? ''"
                                    rows="4"
                                    class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border px-3 py-2 font-mono text-sm outline-none focus-visible:ring-[3px]"
                                    @input="
                                        (event) =>
                                            updateSelectedNode((node) => ({
                                                ...node,
                                                config: {
                                                    ...node.config,
                                                    input_mapping:
                                                        optionalTextareaValue(
                                                            event,
                                                        ),
                                                },
                                            }))
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="node-output-mapping">
                                    {{ t.business_processes.output_mapping_label }}
                                </Label>
                                <textarea
                                    id="node-output-mapping"
                                    :value="selectedNode.config.output_mapping ?? ''"
                                    rows="4"
                                    class="border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 rounded-md border px-3 py-2 font-mono text-sm outline-none focus-visible:ring-[3px]"
                                    @input="
                                        (event) =>
                                            updateSelectedNode((node) => ({
                                                ...node,
                                                config: {
                                                    ...node.config,
                                                    output_mapping:
                                                        optionalTextareaValue(
                                                            event,
                                                        ),
                                                },
                                            }))
                                    "
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="node-retries">
                                    {{ t.business_processes.retry_limit_label }}
                                </Label>
                                <Input
                                    id="node-retries"
                                    type="number"
                                    :model-value="selectedNode.config.retry_limit"
                                    @update:model-value="
                                        (value) =>
                                            updateSelectedNode((node) => ({
                                                ...node,
                                                config: {
                                                    ...node.config,
                                                    retry_limit:
                                                        typeof value === 'number'
                                                            ? value
                                                            : Number(value),
                                                },
                                            }))
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="node-timeout">
                                    {{ t.business_processes.timeout_label }}
                                </Label>
                                <Input
                                    id="node-timeout"
                                    type="number"
                                    :model-value="
                                        selectedNode.config.timeout_seconds
                                    "
                                    @update:model-value="
                                        (value) =>
                                            updateSelectedNode((node) => ({
                                                ...node,
                                                config: {
                                                    ...node.config,
                                                    timeout_seconds:
                                                        typeof value === 'number'
                                                            ? value
                                                            : Number(value),
                                                },
                                            }))
                                    "
                                />
                            </div>
                        </div>

                        <Button
                            variant="destructive"
                            @click="removeSelectedNode"
                        >
                            <Trash2 class="mr-2 size-4" />
                            {{ t.business_processes.remove_node }}
                        </Button>
                    </div>

                    <div
                        v-else
                        class="mt-5 rounded-2xl border border-dashed border-border p-5 text-sm text-muted-foreground"
                    >
                        {{ t.business_processes.node_empty_state }}
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
