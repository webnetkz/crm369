<script setup lang="ts">
import { Download, LoaderCircle, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type CsvExchangeMode = 'import' | 'export';

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode: CsvExchangeMode;
        title: string;
        description: string;
        delimiter: string;
        delimiterLabel: string;
        delimiterPlaceholder: string;
        delimiterHint: string;
        fileLabel: string;
        exportLabel: string;
        importLabel: string;
        templateLabel: string;
        selectedFile?: File | null;
        processing?: boolean;
        progress?: number | null;
        delimiterError?: string;
        fileError?: string;
        accept?: string;
    }>(),
    {
        selectedFile: null,
        processing: false,
        progress: null,
        delimiterError: '',
        fileError: '',
        accept: '.csv,text/csv,text/plain',
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    'update:delimiter': [delimiter: string];
    'file-selected': [file: File | null];
    export: [];
    import: [];
    'download-template': [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.selectedFile,
    (selectedFile) => {
        if (selectedFile === null && fileInput.value) {
            fileInput.value.value = '';
        }
    },
);

const updateDelimiter = (value: unknown): void => {
    emit('update:delimiter', String(value ?? ''));
};

const handleDelimiterInput = (event: Event): void => {
    updateDelimiter((event.target as HTMLInputElement).value);
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    emit('file-selected', input.files?.[0] ?? null);
};
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            class="w-full overflow-y-auto p-0 sm:max-w-lg"
            side="right"
        >
            <SheetHeader class="border-b border-border px-6 py-6 text-left">
                <div
                    class="mb-2 flex size-12 items-center justify-center rounded-2xl border border-border bg-muted"
                >
                    <Upload
                        v-if="mode === 'import'"
                        class="size-5 text-foreground"
                    />
                    <Download v-else class="size-5 text-foreground" />
                </div>
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription>{{ description }}</SheetDescription>
            </SheetHeader>

            <div class="grid flex-1 content-start gap-6 px-6 py-6">
                <div class="grid gap-2">
                    <Label for="csv-exchange-delimiter">
                        {{ delimiterLabel }}
                    </Label>
                    <input
                        id="csv-exchange-delimiter"
                        :value="delimiter"
                        type="text"
                        :placeholder="delimiterPlaceholder"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        @input="handleDelimiterInput"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ delimiterHint }}
                    </p>
                    <InputError :message="delimiterError" />
                </div>

                <div v-if="mode === 'import'" class="grid gap-2">
                    <Label for="csv-exchange-file">{{ fileLabel }}</Label>
                    <input
                        id="csv-exchange-file"
                        ref="fileInput"
                        type="file"
                        :accept="accept"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="processing"
                        @change="handleFileChange"
                    />
                    <InputError :message="fileError" />

                    <div v-if="progress !== null" class="grid gap-2 pt-2">
                        <div
                            class="flex items-center justify-between gap-3 text-xs text-muted-foreground"
                        >
                            <span>{{ importLabel }}</span>
                            <span>{{ progress }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-[width]"
                                :style="{ width: `${progress}%` }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <SheetFooter
                class="mt-auto border-t border-border px-6 py-5 sm:flex-row sm:justify-end"
            >
                <Button
                    v-if="mode === 'import'"
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="emit('download-template')"
                >
                    <Download class="size-4" />
                    {{ templateLabel }}
                </Button>
                <Button
                    v-if="mode === 'import'"
                    type="button"
                    :disabled="processing || selectedFile === null"
                    @click="emit('import')"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="size-4 animate-spin"
                    />
                    <Upload v-else class="size-4" />
                    {{ importLabel }}
                </Button>
                <Button v-else type="button" @click="emit('export')">
                    <Download class="size-4" />
                    {{ exportLabel }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
