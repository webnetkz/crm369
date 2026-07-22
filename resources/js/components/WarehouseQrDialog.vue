<script setup lang="ts">
import { Package, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLanguage } from '@/composables/useLanguage';

type WarehouseQrContent = {
    id: number;
    name: string;
    sku: string | null;
    quantity: number;
    qr_code: string;
    location: {
        path: string;
    };
};

type WarehouseQrEntity = {
    entity_type: string;
    entity_type_label: string;
    title: string;
    qr_code: string;
    qr_code_svg_data_uri: string;
    warehouse: {
        id: number;
        name: string;
    };
    location: {
        path: string;
    };
    details: Record<string, string | number | null>;
    contents: WarehouseQrContent[];
    contents_truncated: boolean;
};

const props = defineProps<{
    open: boolean;
    entity: WarehouseQrEntity | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { language, t } = useLanguage();

const detailLabels = computed<Record<string, string>>(() => ({
    area_sqm: t.value.warehouses.area_sqm,
    row_count: t.value.warehouses.metric_rows,
    column_count: t.value.warehouses.metric_columns,
    floor_count: t.value.warehouses.metric_floors,
    place_count: t.value.warehouses.metric_places,
    item_count: t.value.warehouses.metric_items,
    quantity: t.value.warehouses.quantity,
    sku: t.value.warehouses.sku,
    notes: t.value.warehouses.notes,
}));

const detailEntries = computed(() => {
    if (!props.entity) {
        return [];
    }

    return Object.entries(props.entity.details)
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => ({
            key,
            label: detailLabels.value[key] ?? key,
            value,
        }));
});

const formatValue = (value: string | number | null): string => {
    if (value === null) {
        return '—';
    }

    if (typeof value !== 'number') {
        return value;
    }

    return new Intl.NumberFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        maximumFractionDigits: 2,
    }).format(value);
};

const escapeHtml = (value: string): string => {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
};

const printQrCode = (): void => {
    if (!props.entity) {
        return;
    }

    const title = escapeHtml(props.entity.title);
    const entityType = escapeHtml(props.entity.entity_type_label);
    const location = escapeHtml(props.entity.location.path);
    const qrCodeValue = escapeHtml(props.entity.qr_code);
    const qrCode = props.entity.qr_code_svg_data_uri;
    const locale = document.documentElement.lang || 'en';
    const printMarkup = `<!doctype html>
<html lang="${escapeHtml(locale)}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>${title}</title>
        <style>
            :root { color-scheme: light; font-family: "Instrument Sans", Arial, sans-serif; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
            .card { width: min(440px, calc(100vw - 48px)); border: 1px solid #cbd5e1; border-radius: 24px; background: #fff; padding: 28px; box-sizing: border-box; text-align: center; }
            .type { font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: #64748b; }
            .title { margin-top: 8px; font-size: 24px; font-weight: 700; line-height: 1.2; }
            .location { margin-top: 10px; font-size: 14px; line-height: 1.5; color: #475569; }
            .qr { margin: 24px auto 0; width: 240px; height: 240px; display: block; border: 1px solid #e2e8f0; border-radius: 24px; background: #fff; padding: 16px; box-sizing: border-box; }
            .code { margin-top: 18px; font-size: 14px; font-weight: 600; letter-spacing: .08em; overflow-wrap: anywhere; }
            @media print { body { background: #fff; } .card { width: auto; border-color: #e2e8f0; box-shadow: none; } }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="type">${entityType}</div>
            <div class="title">${title}</div>
            <div class="location">${location}</div>
            <img class="qr" src="${qrCode}" alt="QR code ${qrCodeValue}" />
            <div class="code">${qrCodeValue}</div>
        </main>
    </body>
</html>`;
    const printFrame = document.createElement('iframe');

    printFrame.setAttribute('aria-hidden', 'true');
    printFrame.className =
        'pointer-events-none fixed bottom-0 right-0 h-0 w-0 border-0 opacity-0';

    const cleanup = (): void => {
        printFrame.remove();
    };

    printFrame.onload = () => {
        const frameWindow = printFrame.contentWindow;

        if (!frameWindow) {
            cleanup();

            return;
        }

        frameWindow.onafterprint = cleanup;

        window.setTimeout(() => {
            frameWindow.focus();
            frameWindow.print();
            window.setTimeout(cleanup, 1_000);
        }, 150);
    };

    document.body.append(printFrame);
    printFrame.srcdoc = printMarkup;
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogScrollContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ t.warehouses.qr_details_title }}</DialogTitle>
                <DialogDescription>
                    {{ t.warehouses.qr_details_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="props.entity" class="space-y-6">
                <div
                    class="grid gap-5 rounded-3xl border border-border bg-muted/30 p-5 md:grid-cols-[240px_minmax(0,1fr)]"
                >
                    <div class="flex justify-center">
                        <div
                            class="h-fit overflow-hidden rounded-[1.75rem] border border-border bg-white p-4 shadow-sm"
                        >
                            <img
                                :src="props.entity.qr_code_svg_data_uri"
                                :alt="`${props.entity.entity_type_label}: ${props.entity.qr_code}`"
                                class="size-52"
                            />
                        </div>
                    </div>

                    <div class="min-w-0 space-y-4">
                        <div>
                            <div
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                {{ props.entity.entity_type_label }}
                            </div>
                            <h2
                                class="mt-1 text-xl font-semibold tracking-tight"
                            >
                                {{ props.entity.title }}
                            </h2>
                            <div class="mt-2 text-sm text-muted-foreground">
                                {{ t.warehouses.location_path }}:
                                <span class="text-foreground">{{
                                    props.entity.location.path
                                }}</span>
                            </div>
                        </div>

                        <code
                            class="block w-full rounded-xl bg-background px-3 py-2 text-sm font-medium break-all"
                        >
                            {{ props.entity.qr_code }}
                        </code>

                        <div
                            v-if="detailEntries.length > 0"
                            class="grid gap-2 sm:grid-cols-2"
                        >
                            <div
                                v-for="detail in detailEntries"
                                :key="detail.key"
                                class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm"
                            >
                                <div class="text-muted-foreground">
                                    {{ detail.label }}
                                </div>
                                <div class="mt-1 font-medium break-words">
                                    {{ formatValue(detail.value) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <section
                    v-if="props.entity.entity_type !== 'item'"
                    class="space-y-3"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold">
                            {{ t.warehouses.qr_contents_title }}
                        </h3>
                        <div class="text-sm text-muted-foreground">
                            {{ props.entity.contents.length }}
                        </div>
                    </div>

                    <div
                        v-if="props.entity.contents.length === 0"
                        class="rounded-2xl border border-dashed border-border bg-muted/20 px-5 py-7 text-center text-sm text-muted-foreground"
                    >
                        {{ t.warehouses.qr_contents_empty }}
                    </div>

                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <article
                            v-for="item in props.entity.contents"
                            :key="item.id"
                            class="rounded-2xl border border-border bg-card p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="rounded-xl bg-primary/10 p-2 text-primary"
                                >
                                    <Package class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium">
                                        {{ item.name }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ item.location.path }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span
                                    v-if="item.sku"
                                    class="rounded-full border border-border px-2.5 py-1"
                                >
                                    {{ t.warehouses.sku }}: {{ item.sku }}
                                </span>
                                <span
                                    class="rounded-full border border-border px-2.5 py-1"
                                >
                                    {{ t.warehouses.quantity }}:
                                    {{ item.quantity }}
                                </span>
                            </div>
                        </article>
                    </div>

                    <p
                        v-if="props.entity.contents_truncated"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t.warehouses.qr_contents_truncated }}
                    </p>
                </section>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="printQrCode"
                    >
                        <Printer class="size-4" />
                        <span>{{ t.warehouses.print_qr }}</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('update:open', false)"
                    >
                        {{ t.warehouses.close }}
                    </Button>
                </DialogFooter>
            </div>
        </DialogScrollContent>
    </Dialog>
</template>
