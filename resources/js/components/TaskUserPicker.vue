<script setup lang="ts">
import { Check, ChevronsUpDown, CircleX } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import type { ProjectUserSummary } from '@/types/ui';

type PickerValue = number | '' | number[];

type Props = {
    options: ProjectUserSummary[];
    multiple?: boolean;
    disabled?: boolean;
    excludeUserIds?: number[];
    emptyLabel?: string | null;
};

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    disabled: false,
    excludeUserIds: () => [],
    emptyLabel: null,
});

const model = defineModel<PickerValue>({ required: true });

const emit = defineEmits<{
    (event: 'change'): void;
}>();

const { getInitials } = useInitials();
const { t } = useLanguage();

const pickerOpen = ref(false);
const userSearchQuery = ref('');
const pickerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const availableOptions = computed(() => {
    return props.options.filter((option) => {
        return !props.excludeUserIds.includes(option.id);
    });
});

const selectedIds = computed<number[]>(() => {
    if (Array.isArray(model.value)) {
        return model.value;
    }

    return typeof model.value === 'number' ? [model.value] : [];
});

const selectedOptions = computed(() => {
    return availableOptions.value.filter((option) => {
        return selectedIds.value.includes(option.id);
    });
});

const selectedOption = computed(() => {
    return props.multiple ? null : (selectedOptions.value[0] ?? null);
});

const filteredOptions = computed(() => {
    const query = userSearchQuery.value.trim().toLocaleLowerCase();

    if (query === '') {
        return availableOptions.value.slice(0, 8);
    }

    return availableOptions.value.filter((option) => {
        const searchParts = [
            option.name,
            option.last_name ?? '',
            option.email,
            `${option.name} ${option.last_name ?? ''}`.trim(),
        ].map((part) => part.toLocaleLowerCase());

        return searchParts.some((part) => {
            return part
                .split(/\s+/)
                .some((token) => token.startsWith(query));
        });
    });
});

const triggerLabel = computed(() => {
    if (props.multiple) {
        if (selectedOptions.value.length === 0) {
            return props.emptyLabel ?? t.value.projects.no_co_assignees;
        }

        const labels = selectedOptions.value.map((option) => {
            return formatUserName(option);
        });

        if (labels.length <= 2) {
            return labels.join(', ');
        }

        return `${labels.slice(0, 2).join(', ')} +${labels.length - 2}`;
    }

    return selectedOption.value
        ? formatUserName(selectedOption.value)
        : t.value.projects.unassigned;
});

const optionAvatarStyle = (option: ProjectUserSummary): Record<string, string> => ({
    objectPosition: 'center',
    transform: `scale(${option.avatar_scale ?? 1})`,
});

const formatUserName = (user: ProjectUserSummary): string => {
    return [user.name, user.last_name].filter(Boolean).join(' ');
};

const isSelected = (userId: number): boolean => {
    return selectedIds.value.includes(userId);
};

const closePicker = (): void => {
    pickerOpen.value = false;
    userSearchQuery.value = '';
};

const openPicker = async (): Promise<void> => {
    if (props.disabled) {
        return;
    }

    pickerOpen.value = true;
    userSearchQuery.value = '';

    await nextTick();
    searchInputRef.value?.focus();
};

const togglePicker = (): void => {
    if (pickerOpen.value) {
        closePicker();

        return;
    }

    void openPicker();
};

const updateValue = (value: PickerValue): void => {
    model.value = value;
    emit('change');
};

const selectOption = (option: ProjectUserSummary | null): void => {
    updateValue(option?.id ?? '');
    closePicker();
};

const toggleOption = (option: ProjectUserSummary): void => {
    if (!Array.isArray(model.value)) {
        return;
    }

    if (isSelected(option.id)) {
        updateValue(
            model.value.filter((selectedUserId) => selectedUserId !== option.id),
        );

        return;
    }

    updateValue([...new Set([...model.value, option.id])]);
};

const handleDocumentPointerDown = (event: MouseEvent): void => {
    if (pickerRef.value && !pickerRef.value.contains(event.target as Node)) {
        closePicker();
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentPointerDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentPointerDown);
});
</script>

<template>
    <div ref="pickerRef" class="relative">
        <button
            type="button"
            class="flex min-h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-left text-sm transition-colors hover:bg-muted/40 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="disabled"
            @click="togglePicker"
        >
            <span class="truncate">
                {{ triggerLabel }}
            </span>
            <ChevronsUpDown
                class="ml-2 size-4 shrink-0 text-muted-foreground"
            />
        </button>

        <div
            v-if="pickerOpen"
            class="absolute inset-x-0 z-30 mt-2 rounded-2xl border border-border bg-background p-2 shadow-lg"
        >
            <Input
                ref="searchInputRef"
                v-model="userSearchQuery"
                autocomplete="off"
                :placeholder="t.admin.user_search_placeholder"
                @keydown.esc.prevent="closePicker"
            />

            <div class="mt-2 grid max-h-72 gap-1 overflow-y-auto">
                <button
                    v-if="!multiple"
                    type="button"
                    class="flex items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                    @mousedown.prevent="selectOption(null)"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <CircleX class="size-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">
                            {{ t.projects.unassigned }}
                        </div>
                    </div>
                    <Check
                        v-if="model === ''"
                        class="size-4 text-primary"
                    />
                </button>

                <button
                    v-for="option in filteredOptions"
                    :key="option.id"
                    type="button"
                    class="flex items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                    @mousedown.prevent="
                        multiple ? toggleOption(option) : selectOption(option)
                    "
                >
                    <Avatar class="size-9 rounded-full border border-border">
                        <AvatarImage
                            v-if="option.avatar"
                            :src="option.avatar"
                            :alt="formatUserName(option)"
                            :style="optionAvatarStyle(option)"
                        />
                        <AvatarFallback>
                            {{ getInitials(formatUserName(option)) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">
                            {{ formatUserName(option) }}
                        </div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ option.email }}
                        </div>
                    </div>
                    <Check
                        v-if="isSelected(option.id)"
                        class="size-4 text-primary"
                    />
                </button>

                <div
                    v-if="filteredOptions.length === 0"
                    class="rounded-xl px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t.admin.manager_search_empty }}
                </div>
            </div>
        </div>
    </div>
</template>
