<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Activity,
    BadgeCheck,
    Box,
    Camera,
    Clock3,
    QrCode,
    RadioTower,
    ScanLine,
    Webhook,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index as qrIndex } from '@/routes/qr';
import { index as tsdIndex, store as storeTsdScan } from '@/routes/tsd';

type ScanActor = {
    id: number;
    name: string;
    last_name: string | null;
    email: string;
} | null;

type ScanWebhook = {
    id: number;
    name: string;
} | null;

type ScanItem = {
    id: number;
    qr_code: string;
    normalized_qr_code: string;
    source: string;
    source_label: string;
    device_name: string | null;
    location: string | null;
    context: string | null;
    payload: Record<string, unknown> | null;
    scanned_at: string | null;
    created_at: string | null;
    actor: ScanActor;
    webhook: ScanWebhook;
};

type DetectedBarcode = {
    rawValue?: string;
};

type BarcodeDetectorLike = {
    detect: (source: HTMLVideoElement) => Promise<DetectedBarcode[]>;
};

type BarcodeDetectorConstructor = new (options?: {
    formats?: string[];
}) => BarcodeDetectorLike;

type BarcodeDetectorWindow = Window & {
    BarcodeDetector?: BarcodeDetectorConstructor;
};

const props = defineProps<{
    autoStartScanner: boolean;
    initialQrCode: string;
    stats: {
        total: number;
        today: number;
        web: number;
        api: number;
        webhook: number;
    };
    recentScans: ScanItem[];
}>();

const { language, t } = useLanguage();
const videoElement = ref<HTMLVideoElement | null>(null);
const scannerActive = ref(false);
const scannerStarting = ref(false);
const scannerBusy = ref(false);
const scannerError = ref<string | null>(null);
const autoStartDismissed = ref(false);
const autoStartAttempted = ref(false);
let scannerStream: MediaStream | null = null;
let scannerIntervalId: number | null = null;
let barcodeDetector: BarcodeDetectorLike | null = null;

const form = useForm({
    qr_code: props.initialQrCode,
    device_name: '',
    location: '',
    context: '',
});

const pageTitle = computed((): string => {
    return props.autoStartScanner
        ? t.value.tsd.quick_scan_title
        : t.value.tsd.title;
});

const shouldRenderScannerOnly = computed((): boolean => {
    return props.autoStartScanner;
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: pageTitle.value,
                href: props.autoStartScanner ? qrIndex() : tsdIndex(),
            },
        ],
    });
});

const statsCards = computed(() => [
    {
        key: 'total',
        title: t.value.tsd.stats_total,
        value: props.stats.total,
        icon: QrCode,
        tone: 'bg-primary/10 text-primary',
    },
    {
        key: 'today',
        title: t.value.tsd.stats_today,
        value: props.stats.today,
        icon: Clock3,
        tone: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
    },
    {
        key: 'web',
        title: t.value.tsd.stats_web,
        value: props.stats.web,
        icon: ScanLine,
        tone: 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    },
    {
        key: 'api',
        title: t.value.tsd.stats_api,
        value: props.stats.api,
        icon: RadioTower,
        tone: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
    },
    {
        key: 'webhook',
        title: t.value.tsd.stats_webhook,
        value: props.stats.webhook,
        icon: Webhook,
        tone: 'bg-fuchsia-500/12 text-fuchsia-700 dark:text-fuchsia-300',
    },
]);

const scannerSupported = computed((): boolean => {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') {
        return false;
    }

    return (
        typeof (window as BarcodeDetectorWindow).BarcodeDetector !==
            'undefined' &&
        typeof navigator.mediaDevices?.getUserMedia === 'function'
    );
});

const scannerStatusMessage = computed((): string => {
    if (scannerError.value) {
        return scannerError.value;
    }

    if (scannerStarting.value) {
        return t.value.tsd.scanner_starting;
    }

    if (scannerActive.value) {
        return t.value.tsd.scanner_active;
    }

    return scannerSupported.value
        ? t.value.tsd.scanner_ready
        : t.value.tsd.scanner_unsupported;
});

const stopScanner = (disableAutoStart: boolean = false): void => {
    if (disableAutoStart) {
        autoStartDismissed.value = true;
    }

    if (scannerIntervalId !== null && typeof window !== 'undefined') {
        window.clearInterval(scannerIntervalId);
        scannerIntervalId = null;
    }

    scannerStream?.getTracks().forEach((track) => track.stop());
    scannerStream = null;
    barcodeDetector = null;
    scannerBusy.value = false;
    scannerActive.value = false;

    if (videoElement.value) {
        videoElement.value.pause();
        videoElement.value.srcObject = null;
    }
};

const detectQrCode = async (): Promise<void> => {
    if (scannerBusy.value || !scannerActive.value) {
        return;
    }

    const video = videoElement.value;

    if (
        !video ||
        !barcodeDetector ||
        video.readyState < HTMLMediaElement.HAVE_ENOUGH_DATA
    ) {
        return;
    }

    scannerBusy.value = true;

    try {
        const qrCode = (await barcodeDetector.detect(video))
            .map((barcode) => barcode.rawValue?.trim() ?? '')
            .find((value) => value !== '');

        if (!qrCode) {
            return;
        }

        form.qr_code = qrCode;
        submitScan();
    } catch {
        // Ignore transient detection errors while the camera is warming up.
    } finally {
        scannerBusy.value = false;
    }
};

const startScanner = async (): Promise<void> => {
    if (scannerStarting.value || scannerActive.value) {
        return;
    }

    scannerError.value = null;

    const detectorConstructor =
        typeof window !== 'undefined'
            ? (window as BarcodeDetectorWindow).BarcodeDetector
            : undefined;

    if (!detectorConstructor || !scannerSupported.value) {
        scannerError.value = t.value.tsd.scanner_unsupported;

        return;
    }

    scannerStarting.value = true;

    try {
        barcodeDetector = new detectorConstructor({
            formats: ['qr_code'],
        });

        scannerStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: {
                    ideal: 'environment',
                },
            },
        });

        if (!videoElement.value) {
            throw new Error('Scanner video element is unavailable.');
        }

        videoElement.value.srcObject = scannerStream;
        await videoElement.value.play();

        scannerActive.value = true;
        scannerIntervalId = window.setInterval(() => {
            void detectQrCode();
        }, 250);
    } catch (error) {
        stopScanner();

        scannerError.value =
            error instanceof DOMException && error.name === 'NotAllowedError'
                ? t.value.tsd.scanner_permission_denied
                : t.value.tsd.scanner_error;
    } finally {
        scannerStarting.value = false;
    }
};

watchEffect(() => {
    if (
        !props.autoStartScanner ||
        props.initialQrCode !== '' ||
        autoStartDismissed.value ||
        autoStartAttempted.value ||
        !scannerSupported.value ||
        scannerActive.value ||
        scannerStarting.value
    ) {
        return;
    }

    autoStartAttempted.value = true;
    void startScanner();
});

const submitScan = (): void => {
    stopScanner();

    form.post(storeTsdScan.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('qr_code', 'context');
            form.clearErrors();
        },
    });
};

const formatDateTime = (value: string | null): string => {
    if (! value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const actorLabel = (scan: ScanItem): string => {
    if (scan.actor) {
        return (
            [scan.actor.name, scan.actor.last_name].filter(Boolean).join(' ') ||
            scan.actor.email
        );
    }

    if (scan.webhook) {
        return scan.webhook.name;
    }

    return '—';
};

const sourceTone = (source: string): string => {
    switch (source) {
        case 'api':
            return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
        case 'webhook':
            return 'bg-fuchsia-500/12 text-fuchsia-700 dark:text-fuchsia-300';
        default:
            return 'bg-sky-500/12 text-sky-700 dark:text-sky-300';
    }
};

const payloadPreview = (payload: Record<string, unknown> | null): string => {
    if (! payload || Object.keys(payload).length === 0) {
        return '—';
    }

    return JSON.stringify(payload, null, 2);
};

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div
        v-if="shouldRenderScannerOnly"
        class="mx-auto flex min-h-[calc(100vh-10rem)] max-w-3xl items-center"
    >
        <section class="w-full overflow-hidden rounded-3xl border border-border bg-card shadow-xs">
            <div class="space-y-5 p-6 sm:p-8">
                <div
                    class="relative overflow-hidden rounded-3xl border border-border bg-black"
                >
                    <video
                        ref="videoElement"
                        autoplay
                        muted
                        playsinline
                        class="aspect-[4/5] h-full w-full object-cover transition-opacity duration-200 sm:aspect-video"
                        :class="scannerActive ? 'opacity-100' : 'opacity-0'"
                    />

                    <div
                        v-if="!scannerActive"
                        class="absolute inset-0 flex items-center justify-center px-6 text-center"
                    >
                        <div class="space-y-3">
                            <div
                                class="mx-auto flex size-14 items-center justify-center rounded-full bg-white/10 text-white"
                            >
                                <Camera class="size-6" />
                            </div>
                            <p
                                class="max-w-sm text-sm leading-6 text-white/80"
                            >
                                {{ scannerStatusMessage }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="pointer-events-none absolute inset-0 p-5 sm:p-8"
                    >
                        <div
                            class="h-full w-full rounded-[2rem] border-2 border-dashed border-white/70"
                        />
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-border bg-muted/20 px-4 py-3 text-center text-sm leading-6 text-muted-foreground"
                >
                    {{ scannerStatusMessage }}
                </div>

                <div class="flex justify-center">
                    <Button
                        v-if="scannerActive"
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="stopScanner(true)"
                    >
                        <QrCode class="size-4" />
                        <span>{{ t.tsd.scanner_stop }}</span>
                    </Button>

                    <Button
                        v-else-if="scannerError"
                        type="button"
                        class="gap-2"
                        :disabled="scannerStarting || form.processing"
                        @click="startScanner"
                    >
                        <ScanLine class="size-4" />
                        <span>{{
                            scannerStarting
                                ? t.tsd.scanner_starting
                                : t.tsd.scanner_start
                        }}</span>
                    </Button>
                </div>
            </div>
        </section>
    </div>

    <div v-else class="space-y-8">
        <section
            class="overflow-hidden rounded-3xl border border-border bg-card shadow-xs"
        >
            <div
                class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px]"
            >
                <div class="space-y-4">
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                    >
                        <ScanLine class="size-4" />
                        {{ t.tsd.eyebrow }}
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ t.tsd.hero_title }}
                        </h2>
                        <p
                            class="max-w-3xl text-sm leading-6 text-muted-foreground"
                        >
                            {{ t.tsd.hero_description }}
                        </p>
                    </div>
                </div>

                <div class="space-y-3 rounded-3xl border border-border bg-background/80 p-5">
                    <Heading
                        variant="small"
                        :title="t.tsd.integration_title"
                        :description="t.tsd.integration_description"
                    />

                    <div
                        class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm leading-6 text-muted-foreground"
                    >
                        {{ t.tsd.api_hint }}
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-muted/40 px-4 py-3 text-sm leading-6 text-muted-foreground"
                    >
                        {{ t.tsd.webhook_hint }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-5 md:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="card in statsCards"
                    :key="card.key"
                    class="rounded-2xl border border-border bg-background/80 p-5"
                >
                    <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <div
                            class="flex size-8 items-center justify-center rounded-full"
                            :class="card.tone"
                        >
                            <component :is="card.icon" class="size-4" />
                        </div>
                        <span>{{ card.title }}</span>
                    </div>

                    <div class="mt-4 text-3xl font-semibold tracking-tight">
                        {{ card.value }}
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.tsd.recent_title"
                        :description="t.tsd.recent_description"
                    />

                    <div
                        v-if="recentScans.length === 0"
                        class="mt-5 rounded-2xl border border-dashed border-border bg-muted/20 px-5 py-8 text-center"
                    >
                        <div
                            class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <QrCode class="size-5" />
                        </div>
                        <h3 class="mt-4 text-base font-semibold">
                            {{ t.tsd.empty_title }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t.tsd.empty_description }}
                        </p>
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article
                            v-for="scan in recentScans"
                            :key="scan.id"
                            class="rounded-2xl border border-border bg-background/80 p-5"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant="secondary"
                                            class="border-transparent"
                                            :class="sourceTone(scan.source)"
                                        >
                                            {{ scan.source_label }}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            class="border-transparent bg-primary/10 text-primary"
                                        >
                                            #{{ scan.id }}
                                        </Badge>
                                    </div>

                                    <p
                                        class="break-all font-mono text-sm text-foreground"
                                    >
                                        {{ scan.qr_code }}
                                    </p>
                                </div>

                                <div class="text-sm text-muted-foreground">
                                    {{ formatDateTime(scan.scanned_at) }}
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                <div class="rounded-2xl border border-border bg-card px-4 py-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.tsd.normalized_qr_code }}
                                    </div>
                                    <p class="mt-2 break-all font-mono text-sm">
                                        {{ scan.normalized_qr_code }}
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-border bg-card px-4 py-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.tsd.actor }}
                                    </div>
                                    <p class="mt-2 text-sm">
                                        {{ actorLabel(scan) }}
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-border bg-card px-4 py-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.tsd.location }}
                                    </div>
                                    <p class="mt-2 text-sm">
                                        {{ scan.location || '—' }}
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-border bg-card px-4 py-3">
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.tsd.context }}
                                    </div>
                                    <p class="mt-2 text-sm">
                                        {{ scan.context || '—' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-4 rounded-2xl border border-border bg-card px-4 py-3"
                            >
                                <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    {{ t.tsd.payload }}
                                </div>
                                <pre
                                    class="mt-2 overflow-x-auto whitespace-pre-wrap break-words text-xs leading-6 text-muted-foreground"
                                >{{ payloadPreview(scan.payload) }}</pre>
                            </div>
                        </article>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.tsd.scanner_title"
                        :description="t.tsd.scanner_description"
                    />

                    <div class="mt-5 space-y-4">
                        <div
                            class="relative overflow-hidden rounded-2xl border border-border bg-black"
                        >
                            <video
                                ref="videoElement"
                                autoplay
                                muted
                                playsinline
                                class="aspect-video h-full w-full object-cover transition-opacity duration-200"
                                :class="scannerActive ? 'opacity-100' : 'opacity-0'"
                            />

                            <div
                                v-if="!scannerActive"
                                class="absolute inset-0 flex items-center justify-center px-6 text-center"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-white/10 text-white"
                                    >
                                        <Camera class="size-5" />
                                    </div>
                                    <p
                                        class="max-w-xs text-sm leading-6 text-white/80"
                                    >
                                        {{ scannerStatusMessage }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                class="pointer-events-none absolute inset-0 p-5"
                            >
                                <div
                                    class="h-full w-full rounded-[2rem] border-2 border-dashed border-white/70"
                                />
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-muted/20 px-4 py-3 text-sm leading-6 text-muted-foreground"
                        >
                            {{ scannerStatusMessage }}
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Button
                                type="button"
                                class="gap-2 sm:flex-1"
                                :disabled="scannerStarting || form.processing || scannerActive"
                                @click="startScanner"
                            >
                                <ScanLine class="size-4" />
                                <span>{{
                                    scannerStarting
                                        ? t.tsd.scanner_starting
                                        : t.tsd.scanner_start
                                }}</span>
                            </Button>

                            <Button
                                v-if="scannerActive"
                                type="button"
                                variant="outline"
                                class="gap-2 sm:flex-1"
                                @click="stopScanner(true)"
                            >
                                <QrCode class="size-4" />
                                <span>{{ t.tsd.scanner_stop }}</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.tsd.submit_title"
                        :description="t.tsd.submit_description"
                    />

                    <form class="mt-5 space-y-4" @submit.prevent="submitScan">
                        <div class="space-y-2">
                            <Label for="tsd-qr-code">{{ t.tsd.qr_code }}</Label>
                            <Input
                                id="tsd-qr-code"
                                v-model="form.qr_code"
                                :placeholder="t.tsd.qr_code_placeholder"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.qr_code" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="tsd-device-name">{{
                                    t.tsd.device_name
                                }}</Label>
                                <Input
                                    id="tsd-device-name"
                                    v-model="form.device_name"
                                    :placeholder="t.tsd.device_name_placeholder"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.device_name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="tsd-location">{{
                                    t.tsd.location
                                }}</Label>
                                <Input
                                    id="tsd-location"
                                    v-model="form.location"
                                    :placeholder="t.tsd.location_placeholder"
                                    autocomplete="off"
                                />
                                <InputError :message="form.errors.location" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="tsd-context">{{ t.tsd.context }}</Label>
                            <Input
                                id="tsd-context"
                                v-model="form.context"
                                :placeholder="t.tsd.context_placeholder"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.context" />
                        </div>

                        <Button
                            type="submit"
                            class="w-full gap-2"
                            :disabled="form.processing"
                        >
                            <BadgeCheck class="size-4" />
                            <span>{{ t.tsd.submit }}</span>
                        </Button>
                    </form>
                </div>

                <div class="rounded-3xl border border-border bg-muted/20 p-6">
                    <Heading
                        variant="small"
                        :title="t.tsd.integration_title"
                        :description="t.tsd.integration_description"
                    />

                    <div class="mt-5 space-y-3">
                        <div
                            class="flex items-start gap-3 rounded-2xl border border-border bg-background/80 px-4 py-3"
                        >
                            <div
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Activity class="size-4" />
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ t.tsd.api_hint }}
                            </p>
                        </div>

                        <div
                            class="flex items-start gap-3 rounded-2xl border border-border bg-background/80 px-4 py-3"
                        >
                            <div
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Box class="size-4" />
                            </div>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ t.tsd.webhook_hint }}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</template>
