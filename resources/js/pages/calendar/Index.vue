<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Circle,
    ListFilter,
    Video,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLanguage } from '@/composables/useLanguage';
import { index as calendarIndex } from '@/routes/calendar';

type CalendarView = 'month' | 'week' | 'day' | 'agenda';
type CalendarEventType = 'task' | 'conference';

type CalendarEvent = {
    id: string;
    source_id: number;
    type: CalendarEventType;
    title: string;
    description: string | null;
    start_at: string;
    end_at: string;
    all_day: boolean;
    status: string;
    color: string;
    url: string;
    meta: {
        project?: { id: number; name: string } | null;
        assignee?: { id: number; name: string } | null;
        organizer?: { id: number; name: string } | null;
        importance?: string;
        is_completed?: boolean;
        is_organizer?: boolean;
    };
};

type CalendarDay = {
    key: string;
    date: Date;
    isCurrentPeriod: boolean;
    isToday: boolean;
};

const props = defineProps<{
    events: CalendarEvent[];
    range: { from: string; to: string };
    filters: { types: CalendarEventType[] };
    view: CalendarView;
    referenceDate: string;
}>();

const { language, t } = useLanguage();
const referenceDate = ref(parseDateKey(props.referenceDate));
const activeView = ref<CalendarView>(props.view);
const selectedTypes = ref<CalendarEventType[]>([...props.filters.types]);
const selectedEvent = ref<CalendarEvent | null>(null);
const isNavigating = ref(false);

watch(
    () => props.referenceDate,
    (value) => {
        referenceDate.value = parseDateKey(value);
    },
);

watch(
    () => props.view,
    (value) => {
        activeView.value = value;
    },
);

watch(
    () => props.filters.types,
    (value) => {
        selectedTypes.value = [...value];
    },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.calendar.title,
                href: calendarIndex(),
            },
        ],
    });
});

const locale = computed(() => (language.value === 'ru' ? 'ru-RU' : 'en-US'));
const todayKey = computed(() => dateKey(new Date()));
const hours = Array.from({ length: 24 }, (_, hour) => hour);

const viewOptions = computed<Array<{ value: CalendarView; label: string }>>(
    () => [
        { value: 'month', label: t.value.calendar.view_month },
        { value: 'week', label: t.value.calendar.view_week },
        { value: 'day', label: t.value.calendar.view_day },
        { value: 'agenda', label: t.value.calendar.view_agenda },
    ],
);

const filterOptions = computed<
    Array<{
        value: CalendarEventType;
        label: string;
        color: string;
        icon: typeof CalendarDays;
    }>
>(() => [
    {
        value: 'task',
        label: t.value.calendar.filter_tasks,
        color: '#2563eb',
        icon: Check,
    },
    {
        value: 'conference',
        label: t.value.calendar.filter_conferences,
        color: '#7c3aed',
        icon: Video,
    },
]);

const periodTitle = computed(() => {
    const options: Intl.DateTimeFormatOptions =
        activeView.value === 'day'
            ? {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              }
            : { month: 'long', year: 'numeric' };

    return new Intl.DateTimeFormat(locale.value, options).format(
        referenceDate.value,
    );
});

const monthDays = computed<CalendarDay[]>(() => {
    const monthStart = startOfMonth(referenceDate.value);
    const gridStart = startOfWeek(monthStart);

    return Array.from({ length: 42 }, (_, index) => {
        const date = addDays(gridStart, index);

        return {
            key: dateKey(date),
            date,
            isCurrentPeriod: date.getMonth() === referenceDate.value.getMonth(),
            isToday: dateKey(date) === todayKey.value,
        };
    });
});

const visibleTimelineDays = computed<CalendarDay[]>(() => {
    const start =
        activeView.value === 'week'
            ? startOfWeek(referenceDate.value)
            : startOfDay(referenceDate.value);
    const length = activeView.value === 'week' ? 7 : 1;

    return Array.from({ length }, (_, index) => {
        const date = addDays(start, index);

        return {
            key: dateKey(date),
            date,
            isCurrentPeriod: true,
            isToday: dateKey(date) === todayKey.value,
        };
    });
});

const eventsByDay = computed(() => {
    const grouped = new Map<string, CalendarEvent[]>();

    props.events.forEach((event) => {
        const key = dateKey(new Date(event.start_at));
        const events = grouped.get(key) ?? [];
        events.push(event);
        grouped.set(key, events);
    });

    grouped.forEach((events) => {
        events.sort(
            (first, second) =>
                new Date(first.start_at).getTime() -
                new Date(second.start_at).getTime(),
        );
    });

    return grouped;
});

const agendaGroups = computed(() => {
    return [...eventsByDay.value.entries()]
        .sort(([first], [second]) => first.localeCompare(second))
        .map(([key, events]) => ({
            key,
            date: parseDateKey(key),
            events,
        }));
});

function parseDateKey(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1, 12, 0, 0, 0);
}

function dateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), 12);
}

function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1, 12);
}

function endOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0, 12);
}

function startOfWeek(date: Date): Date {
    const day = date.getDay() === 0 ? 7 : date.getDay();

    return addDays(startOfDay(date), 1 - day);
}

function endOfWeek(date: Date): Date {
    return addDays(startOfWeek(date), 6);
}

function addDays(date: Date, amount: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + amount);

    return next;
}

function addMonths(date: Date, amount: number): Date {
    const next = new Date(date);
    next.setDate(1);
    next.setMonth(next.getMonth() + amount);

    return next;
}

function visibleRange(
    date: Date,
    view: CalendarView,
): { from: string; to: string } {
    if (view === 'month') {
        return {
            from: dateKey(startOfWeek(startOfMonth(date))),
            to: dateKey(endOfWeek(endOfMonth(date))),
        };
    }

    if (view === 'week') {
        return {
            from: dateKey(startOfWeek(date)),
            to: dateKey(endOfWeek(date)),
        };
    }

    if (view === 'agenda') {
        return {
            from: dateKey(startOfMonth(date)),
            to: dateKey(endOfMonth(date)),
        };
    }

    return { from: dateKey(date), to: dateKey(date) };
}

function visitCalendar(date: Date, view: CalendarView): void {
    const range = visibleRange(date, view);
    isNavigating.value = true;

    router.get(
        calendarIndex.url({
            query: {
                ...range,
                date: dateKey(date),
                view,
                types: selectedTypes.value,
            },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                isNavigating.value = false;
            },
        },
    );
}

function navigatePeriod(direction: -1 | 1): void {
    const nextDate =
        activeView.value === 'month' || activeView.value === 'agenda'
            ? addMonths(referenceDate.value, direction)
            : addDays(
                  referenceDate.value,
                  activeView.value === 'week' ? direction * 7 : direction,
              );

    visitCalendar(nextDate, activeView.value);
}

function goToday(): void {
    visitCalendar(new Date(), activeView.value);
}

function changeView(view: CalendarView): void {
    if (view === activeView.value) {
        return;
    }

    activeView.value = view;
    visitCalendar(referenceDate.value, view);
}

function toggleType(type: CalendarEventType): void {
    const selected = new Set(selectedTypes.value);

    if (selected.has(type)) {
        if (selected.size === 1) {
            return;
        }

        selected.delete(type);
    } else {
        selected.add(type);
    }

    selectedTypes.value = filterOptions.value
        .map((option) => option.value)
        .filter((value) => selected.has(value));

    visitCalendar(referenceDate.value, activeView.value);
}

function dayEvents(day: CalendarDay): CalendarEvent[] {
    return eventsByDay.value.get(day.key) ?? [];
}

function formatDayNumber(date: Date): string {
    return new Intl.DateTimeFormat(locale.value, { day: 'numeric' }).format(
        date,
    );
}

function formatWeekday(date: Date, short = true): string {
    return new Intl.DateTimeFormat(locale.value, {
        weekday: short ? 'short' : 'long',
    }).format(date);
}

function formatDayHeading(date: Date): string {
    return new Intl.DateTimeFormat(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }).format(date);
}

function formatTime(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatHour(hour: number): string {
    return `${String(hour).padStart(2, '0')}:00`;
}

function formatEventRange(event: CalendarEvent): string {
    if (event.all_day) {
        return t.value.calendar.all_day;
    }

    return `${formatTime(event.start_at)}–${formatTime(event.end_at)}`;
}

function eventPosition(event: CalendarEvent): Record<string, string> {
    const start = new Date(event.start_at);
    const end = new Date(event.end_at);
    const startMinutes = start.getHours() * 60 + start.getMinutes();
    const durationMinutes = Math.max(
        30,
        (end.getTime() - start.getTime()) / 60000,
    );

    return {
        top: `${(startMinutes / 60) * 56}px`,
        height: `${Math.max(28, (durationMinutes / 60) * 56)}px`,
        backgroundColor: event.color,
    };
}

function eventLabel(event: CalendarEvent): string {
    return event.type === 'task'
        ? t.value.calendar.task
        : t.value.calendar.conference;
}

function selectEvent(event: CalendarEvent): void {
    selectedEvent.value = event;
}
</script>

<template>
    <Head :title="t.calendar.title" />

    <div class="flex min-h-0 flex-1 flex-col gap-5 pb-6">
        <header
            class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <CalendarDays class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="truncate text-2xl font-semibold tracking-tight"
                        >
                            {{ t.calendar.title }}
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ t.calendar.description }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isNavigating"
                    @click="goToday"
                >
                    {{ t.calendar.today }}
                </Button>
                <div
                    class="flex items-center rounded-lg border border-border bg-background p-0.5"
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t.calendar.previous_period"
                        :disabled="isNavigating"
                        @click="navigatePeriod(-1)"
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="t.calendar.next_period"
                        :disabled="isNavigating"
                        @click="navigatePeriod(1)"
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <div
                    class="flex max-w-full items-center overflow-x-auto rounded-lg border border-border bg-background p-0.5"
                >
                    <Button
                        v-for="option in viewOptions"
                        :key="option.value"
                        size="sm"
                        :variant="
                            activeView === option.value ? 'secondary' : 'ghost'
                        "
                        :disabled="isNavigating"
                        @click="changeView(option.value)"
                    >
                        {{ option.label }}
                    </Button>
                </div>
            </div>
        </header>

        <div class="grid min-h-0 gap-5 xl:grid-cols-[15rem_minmax(0,1fr)]">
            <aside
                class="space-y-4 rounded-2xl border border-border bg-card p-4 xl:self-start"
            >
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <ListFilter class="size-4" />
                    {{ t.calendar.filters }}
                </div>

                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                    <button
                        v-for="option in filterOptions"
                        :key="option.value"
                        type="button"
                        class="flex items-center gap-3 rounded-xl border px-3 py-2.5 text-left text-sm transition-colors"
                        :class="
                            selectedTypes.includes(option.value)
                                ? 'border-border bg-muted/60 text-foreground'
                                : 'border-transparent text-muted-foreground hover:bg-muted/40'
                        "
                        :disabled="isNavigating"
                        @click="toggleType(option.value)"
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-md text-white"
                            :style="{ backgroundColor: option.color }"
                        >
                            <component
                                :is="
                                    selectedTypes.includes(option.value)
                                        ? option.icon
                                        : Circle
                                "
                                class="size-3.5"
                            />
                        </span>
                        <span class="flex-1">{{ option.label }}</span>
                    </button>
                </div>

                <div
                    class="rounded-xl bg-muted/50 px-3 py-2.5 text-xs text-muted-foreground"
                >
                    {{
                        t.calendar.events_count.replace(
                            ':count',
                            String(events.length),
                        )
                    }}
                </div>
            </aside>

            <section
                class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
            >
                <div
                    class="flex min-h-16 items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5"
                >
                    <h2 class="truncate text-lg font-semibold capitalize">
                        {{ periodTitle }}
                    </h2>
                    <span
                        v-if="isNavigating"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t.calendar.loading }}
                    </span>
                </div>

                <div v-if="activeView === 'month'" class="overflow-x-auto">
                    <div class="min-w-[760px]">
                        <div
                            class="grid grid-cols-7 border-b border-border bg-muted/30"
                        >
                            <div
                                v-for="day in monthDays.slice(0, 7)"
                                :key="`heading-${day.key}`"
                                class="px-2 py-2 text-center text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ formatWeekday(day.date) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7">
                            <div
                                v-for="day in monthDays"
                                :key="day.key"
                                class="min-h-32 border-r border-b border-border p-1.5 last:border-r-0"
                                :class="
                                    day.isCurrentPeriod
                                        ? 'bg-card'
                                        : 'bg-muted/20'
                                "
                            >
                                <div
                                    class="mb-1 flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="flex size-7 items-center justify-center rounded-full text-xs font-medium"
                                        :class="[
                                            day.isToday
                                                ? 'bg-primary text-primary-foreground'
                                                : day.isCurrentPeriod
                                                  ? 'text-foreground'
                                                  : 'text-muted-foreground',
                                        ]"
                                    >
                                        {{ formatDayNumber(day.date) }}
                                    </span>
                                </div>

                                <div class="grid gap-1">
                                    <button
                                        v-for="event in dayEvents(day).slice(
                                            0,
                                            3,
                                        )"
                                        :key="event.id"
                                        type="button"
                                        class="flex min-w-0 items-center gap-1.5 rounded-md px-1.5 py-1 text-left text-xs font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                                        :style="{
                                            backgroundColor: event.color,
                                        }"
                                        @click="selectEvent(event)"
                                    >
                                        <span class="shrink-0 opacity-80">{{
                                            formatTime(event.start_at)
                                        }}</span>
                                        <span class="truncate">{{
                                            event.title
                                        }}</span>
                                    </button>
                                    <span
                                        v-if="dayEvents(day).length > 3"
                                        class="px-1.5 text-xs font-medium text-muted-foreground"
                                    >
                                        {{
                                            t.calendar.more_events.replace(
                                                ':count',
                                                String(
                                                    dayEvents(day).length - 3,
                                                ),
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="activeView === 'week' || activeView === 'day'"
                    class="overflow-x-auto"
                >
                    <div
                        class="min-w-[760px]"
                        :style="{
                            minWidth: activeView === 'day' ? '520px' : '900px',
                        }"
                    >
                        <div
                            class="grid border-b border-border bg-muted/30"
                            :style="{
                                gridTemplateColumns: `4rem repeat(${visibleTimelineDays.length}, minmax(0, 1fr))`,
                            }"
                        >
                            <div class="border-r border-border" />
                            <div
                                v-for="day in visibleTimelineDays"
                                :key="`timeline-heading-${day.key}`"
                                class="border-r border-border px-2 py-3 text-center last:border-r-0"
                            >
                                <div
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ formatWeekday(day.date) }}
                                </div>
                                <div
                                    class="mx-auto mt-1 flex size-8 items-center justify-center rounded-full text-sm font-semibold"
                                    :class="
                                        day.isToday
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-foreground'
                                    "
                                >
                                    {{ formatDayNumber(day.date) }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid"
                            :style="{
                                gridTemplateColumns: `4rem repeat(${visibleTimelineDays.length}, minmax(0, 1fr))`,
                            }"
                        >
                            <div class="border-r border-border bg-muted/20">
                                <div
                                    v-for="hour in hours"
                                    :key="`hour-${hour}`"
                                    class="h-14 border-b border-border pt-1 pr-2 text-right text-[10px] text-muted-foreground"
                                >
                                    {{ formatHour(hour) }}
                                </div>
                            </div>

                            <div
                                v-for="day in visibleTimelineDays"
                                :key="`timeline-${day.key}`"
                                class="relative border-r border-border last:border-r-0"
                                style="height: 1344px"
                            >
                                <div
                                    v-for="hour in hours"
                                    :key="`${day.key}-${hour}`"
                                    class="h-14 border-b border-border"
                                />
                                <button
                                    v-for="event in dayEvents(day)"
                                    :key="event.id"
                                    type="button"
                                    class="absolute right-1 left-1 z-10 overflow-hidden rounded-md px-2 py-1 text-left text-xs text-white shadow-sm ring-1 ring-black/5 transition-opacity hover:opacity-90"
                                    :style="eventPosition(event)"
                                    @click="selectEvent(event)"
                                >
                                    <span
                                        class="block truncate font-semibold"
                                        >{{ event.title }}</span
                                    >
                                    <span class="block truncate opacity-85">{{
                                        formatEventRange(event)
                                    }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-4 sm:p-5">
                    <div v-if="agendaGroups.length" class="grid gap-6">
                        <section
                            v-for="group in agendaGroups"
                            :key="group.key"
                            class="grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)]"
                        >
                            <div>
                                <h3 class="text-sm font-semibold capitalize">
                                    {{ formatDayHeading(group.date) }}
                                </h3>
                            </div>
                            <div class="grid gap-2">
                                <button
                                    v-for="event in group.events"
                                    :key="event.id"
                                    type="button"
                                    class="group flex items-start gap-3 rounded-xl border border-border bg-background p-3 text-left transition-colors hover:bg-muted/40"
                                    @click="selectEvent(event)"
                                >
                                    <span
                                        class="mt-1 size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor: event.color,
                                        }"
                                    />
                                    <span
                                        class="w-24 shrink-0 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ formatEventRange(event) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span
                                            class="block truncate text-sm font-semibold group-hover:text-primary"
                                        >
                                            {{ event.title }}
                                        </span>
                                        <span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                        >
                                            {{ eventLabel(event) }}
                                        </span>
                                    </span>
                                </button>
                            </div>
                        </section>
                    </div>

                    <div
                        v-else
                        class="flex min-h-72 flex-col items-center justify-center gap-3 text-center"
                    >
                        <CalendarDays
                            class="size-10 text-muted-foreground/50"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ t.calendar.empty }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <Dialog
        :open="selectedEvent !== null"
        @update:open="(open) => !open && (selectedEvent = null)"
    >
        <DialogContent v-if="selectedEvent" class="sm:max-w-lg">
            <DialogHeader>
                <div
                    class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ backgroundColor: selectedEvent.color }"
                    />
                    {{ eventLabel(selectedEvent) }}
                </div>
                <DialogTitle>{{ selectedEvent.title }}</DialogTitle>
                <DialogDescription>
                    {{ formatDayHeading(new Date(selectedEvent.start_at)) }} ·
                    {{ formatEventRange(selectedEvent) }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 text-sm">
                <p
                    v-if="selectedEvent.description"
                    class="leading-6 text-muted-foreground"
                >
                    {{ selectedEvent.description }}
                </p>

                <dl class="grid gap-2 rounded-xl bg-muted/50 p-3">
                    <div
                        v-if="selectedEvent.meta.project"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.calendar.project }}
                        </dt>
                        <dd class="text-right font-medium">
                            {{ selectedEvent.meta.project.name }}
                        </dd>
                    </div>
                    <div
                        v-if="selectedEvent.meta.assignee"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.calendar.assignee }}
                        </dt>
                        <dd class="text-right font-medium">
                            {{ selectedEvent.meta.assignee.name }}
                        </dd>
                    </div>
                    <div
                        v-if="selectedEvent.meta.organizer"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.calendar.organizer }}
                        </dt>
                        <dd class="text-right font-medium">
                            {{ selectedEvent.meta.organizer.name }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-muted-foreground">
                            {{ t.calendar.status }}
                        </dt>
                        <dd class="text-right font-medium">
                            {{ selectedEvent.status }}
                        </dd>
                    </div>
                </dl>
            </div>

            <DialogFooter>
                <Button as-child>
                    <Link :href="selectedEvent.url">
                        {{ t.calendar.open_event }}
                    </Link>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
