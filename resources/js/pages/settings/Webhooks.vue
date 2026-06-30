<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    CheckSquare2,
    Copy,
    KeyRound,
    RefreshCcw,
    Trash2,
    Webhook,
} from '@lucide/vue';
import { ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import {
    destroy,
    edit,
    regenerate,
    store,
    update,
} from '@/routes/settings/webhooks';

type PermissionOption = {
    key: string;
    label: string;
    description: string;
};

type WebhookCreator = {
    id: number;
    name: string;
    email: string;
} | null;

type WebhookRow = {
    id: number;
    name: string;
    token_prefix: string;
    permissions: string[];
    is_active: boolean;
    is_expired: boolean;
    expires_at: string | null;
    last_used_at: string | null;
    created_at: string | null;
    endpoint_url: string;
    creator: WebhookCreator;
};

type IssuedWebhook = {
    name: string;
    token: string;
    endpoint_url: string;
    signed_url: string;
} | null;

type DraftWebhook = {
    name: string;
    is_active: boolean;
    never_expires: boolean;
    expires_at: string;
    permissions: string[];
};

type PageProps = {
    flash?: {
        webhookToken?: IssuedWebhook;
    };
};

const props = defineProps<{
    webhooks: WebhookRow[];
    availablePermissions: PermissionOption[];
}>();

const page = usePage<PageProps>();
const { language, t } = useLanguage();
const copiedWebhookToken = ref(false);
const savingWebhookId = ref<number | null>(null);
const webhookErrors = ref<Record<number, Record<string, string>>>({});
const drafts = ref<Record<number, DraftWebhook>>({});

const createForm = useForm({
    name: '',
    permissions: [] as string[],
    is_active: true,
    never_expires: true,
    expires_at: '',
});

const readFlashWebhook = (): IssuedWebhook => {
    const flashFromPage = (page as typeof page & {
        flash?: { webhookToken?: IssuedWebhook };
    }).flash?.webhookToken;

    return flashFromPage ?? page.props.flash?.webhookToken ?? null;
};

const applyIssuedWebhook = (webhook: IssuedWebhook): void => {
    if (!webhook) {
        return;
    }

    issuedWebhook.value = webhook;
    issuedWebhookDialogOpen.value = true;
    copiedWebhookToken.value = false;
};

const formatDateTimeLocal = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - offset * 60_000);

    return localDate.toISOString().slice(0, 16);
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const authorizationHeaderValue = (token: string): string => {
    return `Authorization: Bearer ${token}`;
};

const webhookHeaderValue = (token: string): string => {
    return `X-Webhook-Token: ${token}`;
};

const syncDrafts = (): void => {
    drafts.value = Object.fromEntries(
        props.webhooks.map((webhook) => [
            webhook.id,
            {
                name: webhook.name,
                is_active: webhook.is_active,
                never_expires: webhook.expires_at === null,
                expires_at: formatDateTimeLocal(webhook.expires_at),
                permissions: [...webhook.permissions],
            },
        ]),
    );
};

watch(
    () => props.webhooks,
    () => syncDrafts(),
    { deep: true, immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.webhooks.title,
                href: edit(),
            },
        ],
    });
});

const issuedWebhook = ref<IssuedWebhook>(readFlashWebhook());
const issuedWebhookDialogOpen = ref(issuedWebhook.value !== null);

watch(
    () => readFlashWebhook(),
    (flashWebhook) => {
        applyIssuedWebhook(flashWebhook);
    },
    { immediate: true },
);

const togglePermission = (
    permissions: string[],
    permission: string,
    checked: boolean | 'indeterminate',
): string[] => {
    const set = new Set(permissions);

    if (checked === true) {
        set.add(permission);
    } else {
        set.delete(permission);
    }

    return [...set];
};

const serializePermissions = (permissions: string[]): string[] => {
    return [
        ...new Set(
            permissions.filter(
                (permission): permission is string => permission.trim() !== '',
            ),
        ),
    ];
};

const submitCreate = (): void => {
    createForm.transform((data) => ({
        ...data,
        permissions: serializePermissions(data.permissions),
        is_active: data.is_active === true,
        never_expires: data.never_expires === true,
        expires_at: data.never_expires ? null : data.expires_at.trim() || null,
    }));

    createForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.permissions = [];
            createForm.is_active = true;
            createForm.never_expires = true;
            createForm.expires_at = '';
        },
        onFlash: (flash: { webhookToken?: IssuedWebhook }) => {
            applyIssuedWebhook(flash.webhookToken ?? null);
        },
    });
};

const saveWebhook = (webhook: WebhookRow): void => {
    const draft = drafts.value[webhook.id];

    if (!draft) {
        return;
    }

    savingWebhookId.value = webhook.id;
    webhookErrors.value[webhook.id] = {};

    router.patch(
        update.url(webhook.id),
        {
            name: draft.name,
            permissions: serializePermissions(draft.permissions),
            is_active: draft.is_active,
            never_expires: draft.never_expires,
            expires_at: draft.never_expires
                ? null
                : draft.expires_at.trim() || null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                webhookErrors.value[webhook.id] = errors;
            },
            onFinish: () => {
                savingWebhookId.value = null;
            },
        },
    );
};

const regenerateWebhook = (webhook: WebhookRow): void => {
    router.post(regenerate.url(webhook.id), {}, {
        preserveScroll: true,
        onFlash: (flash: { webhookToken?: IssuedWebhook }) => {
            applyIssuedWebhook(flash.webhookToken ?? null);
        },
    });
};

const deleteWebhook = (webhook: WebhookRow): void => {
    router.delete(destroy.url(webhook.id), { preserveScroll: true });
};

const copyWebhookToken = async (): Promise<void> => {
    if (!issuedWebhook.value?.token) {
        return;
    }

    await navigator.clipboard.writeText(issuedWebhook.value.token);
    copiedWebhookToken.value = true;
};

const closeIssuedWebhookDialog = (): void => {
    issuedWebhookDialogOpen.value = false;
};
</script>

<template>
    <Head :title="t.webhooks.title" />

    <h1 class="sr-only">{{ t.webhooks.title }}</h1>

    <Dialog
        :open="issuedWebhookDialogOpen"
        @update:open="
            (isOpen) => {
                if (!isOpen) closeIssuedWebhookDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5" />
                    {{ t.webhooks.issued_token_title }}
                </DialogTitle>
                <DialogDescription>
                    {{ t.webhooks.issued_token_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="issuedWebhook" class="space-y-4">
                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.endpoint_url }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="issuedWebhook.endpoint_url"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.plain_token }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="issuedWebhook.token"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.authorization_header }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="authorizationHeaderValue(issuedWebhook.token)"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.header_token }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="webhookHeaderValue(issuedWebhook.token)"
                        ></textarea>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.webhooks.signed_url }}</Label>
                    <textarea
                        class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        rows="3"
                        readonly
                        :value="issuedWebhook.signed_url"
                    ></textarea>
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ t.webhooks.token_usage_help }}
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="closeIssuedWebhookDialog"
                >
                    {{ t.common.cancel }}
                </Button>
                <Button type="button" @click="copyWebhookToken">
                    <Copy class="size-4" />
                    {{
                        copiedWebhookToken
                            ? t.common.copied
                            : t.webhooks.copy_token
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.webhooks.title"
            :description="t.webhooks.description"
        />

        <section class="space-y-4 rounded-2xl border border-border bg-card p-5">
            <Heading
                variant="small"
                :title="t.webhooks.create_title"
                :description="t.webhooks.create_description"
            />

            <form class="space-y-5" @submit.prevent="submitCreate">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="webhook-name">{{ t.webhooks.name }}</Label>
                        <Input
                            id="webhook-name"
                            v-model="createForm.name"
                            :placeholder="t.webhooks.name_placeholder"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.status }}</Label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="createForm.is_active"
                                @update:checked="
                                    (value: boolean | 'indeterminate') =>
                                        (createForm.is_active = value === true)
                                "
                            />
                            <span>{{ t.webhooks.active }}</span>
                        </label>
                        <InputError :message="createForm.errors.is_active" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <CheckSquare2 class="size-4 text-muted-foreground" />
                        {{ t.webhooks.permissions }}
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ t.webhooks.permissions_description }}
                    </p>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="permission in availablePermissions"
                            :key="`create-${permission.key}`"
                            class="flex items-start gap-3 rounded-xl border border-border bg-background/70 p-4"
                        >
                            <Checkbox
                                :checked="
                                    createForm.permissions.includes(
                                        permission.key,
                                    )
                                "
                                @update:checked="
                                    (value: boolean | 'indeterminate') =>
                                        (createForm.permissions =
                                            togglePermission(
                                                createForm.permissions,
                                                permission.key,
                                                value,
                                            ))
                                "
                            />
                            <div class="space-y-1">
                                <div class="text-sm font-medium">
                                    {{ permission.label }}
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ permission.description }}
                                </p>
                            </div>
                        </label>
                    </div>
                    <InputError :message="createForm.errors.permissions" />
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        class="space-y-3 rounded-xl border border-border bg-background/70 p-4"
                    >
                        <label
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <Checkbox
                                :checked="createForm.never_expires"
                                @update:checked="
                                    (value: boolean | 'indeterminate') => {
                                        createForm.never_expires =
                                            value === true;
                                        if (createForm.never_expires) {
                                            createForm.expires_at = '';
                                        }
                                    }
                                "
                            />
                            <span>{{ t.webhooks.never_expires }}</span>
                        </label>

                        <div
                            v-if="!createForm.never_expires"
                            class="grid gap-2"
                        >
                            <Label for="create-expires-at">{{
                                t.webhooks.expires_at
                            }}</Label>
                            <Input
                                id="create-expires-at"
                                v-model="createForm.expires_at"
                                type="datetime-local"
                            />
                            <InputError
                                :message="createForm.errors.expires_at"
                            />
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.expires_at_help }}
                        </p>
                    </div>
                </div>

                <Button type="submit" :disabled="createForm.processing">
                    {{ t.webhooks.create }}
                </Button>
            </form>
        </section>

        <section class="space-y-4">
            <Heading variant="small" :title="t.webhooks.existing_title" />

            <div
                v-if="webhooks.length === 0"
                class="rounded-2xl border border-dashed border-border p-6 text-sm text-muted-foreground"
            >
                {{ t.webhooks.empty }}
            </div>

            <article
                v-for="webhook in webhooks"
                :key="webhook.id"
                class="space-y-5 rounded-2xl border border-border bg-card p-5"
            >
                <div
                    class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Webhook class="size-4 text-muted-foreground" />
                            <h2 class="text-lg font-semibold">
                                {{ drafts[webhook.id]?.name || webhook.name }}
                            </h2>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span
                                class="rounded-full px-2.5 py-1"
                                :class="
                                    webhook.is_expired
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                        : drafts[webhook.id]?.is_active
                                          ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                          : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    webhook.is_expired
                                        ? t.webhooks.expired
                                        : drafts[webhook.id]?.is_active
                                          ? t.webhooks.active
                                          : t.webhooks.inactive
                                }}
                            </span>
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-muted-foreground"
                            >
                                {{ t.webhooks.token_prefix }}:
                                {{ webhook.token_prefix }}
                            </span>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :checked="
                                drafts[webhook.id]?.is_active ??
                                webhook.is_active
                            "
                            @update:checked="
                                (value: boolean | 'indeterminate') => {
                                    if (drafts[webhook.id]) {
                                        drafts[webhook.id].is_active =
                                            value === true;
                                    }
                                }
                            "
                        />
                        <span>{{ t.webhooks.active }}</span>
                    </label>
                </div>

                <div
                    class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]"
                >
                    <div class="space-y-5">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="grid gap-2 lg:col-span-2">
                                <Label :for="`webhook-name-${webhook.id}`">{{
                                    t.webhooks.name
                                }}</Label>
                                <Input
                                    :id="`webhook-name-${webhook.id}`"
                                    v-model="drafts[webhook.id].name"
                                    :placeholder="t.webhooks.name_placeholder"
                                />
                                <p
                                    v-if="webhookErrors[webhook.id]?.name"
                                    class="text-sm text-destructive"
                                >
                                    {{ webhookErrors[webhook.id].name }}
                                </p>
                            </div>

                            <div class="grid gap-2 lg:col-span-2">
                                <Label>{{ t.webhooks.endpoint_url }}</Label>
                                <textarea
                                    class="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    rows="2"
                                    readonly
                                    :value="webhook.endpoint_url"
                                ></textarea>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.webhooks.token_usage_help }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.webhooks.token_regenerate_hint }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <CheckSquare2
                                    class="size-4 text-muted-foreground"
                                />
                                {{ t.webhooks.permissions }}
                            </div>

                            <div
                                class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <label
                                    v-for="permission in availablePermissions"
                                    :key="`${webhook.id}-${permission.key}`"
                                    class="flex items-start gap-3 rounded-xl border border-border bg-background/70 p-4"
                                >
                                    <Checkbox
                                        :checked="
                                            drafts[
                                                webhook.id
                                            ].permissions.includes(
                                                permission.key,
                                            )
                                        "
                                        @update:checked="
                                            (
                                                value:
                                                    boolean | 'indeterminate',
                                            ) => {
                                                drafts[webhook.id].permissions =
                                                    togglePermission(
                                                        drafts[webhook.id]
                                                            .permissions,
                                                        permission.key,
                                                        value,
                                                    );
                                            }
                                        "
                                    />
                                    <div class="space-y-1">
                                        <div class="text-sm font-medium">
                                            {{ permission.label }}
                                        </div>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ permission.description }}
                                        </p>
                                    </div>
                                </label>
                            </div>
                            <p
                                v-if="webhookErrors[webhook.id]?.permissions"
                                class="text-sm text-destructive"
                            >
                                {{ webhookErrors[webhook.id].permissions }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="space-y-4 rounded-2xl border border-border bg-background/70 p-4"
                    >
                        <div class="grid gap-3">
                            <label
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Checkbox
                                    :checked="drafts[webhook.id].never_expires"
                                    @update:checked="
                                        (value: boolean | 'indeterminate') => {
                                            drafts[webhook.id].never_expires =
                                                value === true;
                                            if (
                                                drafts[webhook.id].never_expires
                                            ) {
                                                drafts[webhook.id].expires_at =
                                                    '';
                                            }
                                        }
                                    "
                                />
                                <span>{{ t.webhooks.never_expires }}</span>
                            </label>

                            <div
                                v-if="!drafts[webhook.id].never_expires"
                                class="grid gap-2"
                            >
                                <Label :for="`expires-at-${webhook.id}`">{{
                                    t.webhooks.expires_at
                                }}</Label>
                                <Input
                                    :id="`expires-at-${webhook.id}`"
                                    v-model="drafts[webhook.id].expires_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="webhookErrors[webhook.id]?.expires_at"
                                    class="text-sm text-destructive"
                                >
                                    {{ webhookErrors[webhook.id].expires_at }}
                                </p>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ t.webhooks.expires_at_help }}
                            </p>
                        </div>

                        <div class="grid gap-2 text-sm">
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.created_by }}:</span
                                >
                                {{ webhook.creator?.name ?? '—' }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.created_at }}:</span
                                >
                                {{
                                    webhook.created_at
                                        ? formatDateTime(webhook.created_at)
                                        : '—'
                                }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.last_used_at }}:</span
                                >
                                {{
                                    webhook.last_used_at
                                        ? formatDateTime(webhook.last_used_at)
                                        : t.webhooks.never_used
                                }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.expires_at }}:</span
                                >
                                {{
                                    webhook.expires_at
                                        ? formatDateTime(webhook.expires_at)
                                        : t.webhooks.never_expires
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-5">
                    <Button
                        type="button"
                        :disabled="savingWebhookId === webhook.id"
                        @click="saveWebhook(webhook)"
                    >
                        {{ t.webhooks.save }}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        @click="regenerateWebhook(webhook)"
                    >
                        <RefreshCcw class="mr-2 size-4" />
                        {{ t.webhooks.regenerate }}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        @click="deleteWebhook(webhook)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ t.webhooks.delete }}
                    </Button>
                </div>
            </article>
        </section>
    </div>
</template>
