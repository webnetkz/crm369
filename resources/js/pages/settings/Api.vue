<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import { BookText, Copy, KeyRound, ShieldCheck, Trash2 } from '@lucide/vue';
import {
    computed,
    ref,
    watch,
    watchEffect,
} from 'vue';
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
import { edit } from '@/routes/settings/api';
import { destroy, store } from '@/routes/settings/api/tokens';

type PermissionOption = {
    key: string;
    label: string;
    description: string;
};

type TokenRow = {
    id: number;
    name: string;
    token_prefix: string;
    permissions: string[];
    expires_at: string | null;
    is_expired: boolean;
    last_used_at: string | null;
    last_used_ip_address: string | null;
    last_used_user_agent: string | null;
    created_at: string | null;
};

type DocumentationEndpoint = {
    method: string;
    path: string;
    summary: string;
    permission: string;
    access: string;
    content_type: string;
    target_user: string;
};

type DocumentationSection = {
    title: string;
    description: string;
    endpoints: DocumentationEndpoint[];
};

type IssuedApiToken = {
    name: string;
    token: string;
    expires_at: string | null;
} | null;

type PageProps = {
    flash?: {
        apiToken?: IssuedApiToken;
    };
};

const props = defineProps<{
    can: {
        manage_tokens: boolean;
    };
    baseUrl: string;
    documentation: DocumentationSection[];
    permissions: PermissionOption[];
    tokens: TokenRow[];
}>();

const page = usePage<PageProps>();
const { language, t } = useLanguage();
const copiedToken = ref(false);
const readFlashApiToken = (): IssuedApiToken => {
    const flashFromPage = (page as typeof page & {
        flash?: { apiToken?: IssuedApiToken };
    }).flash?.apiToken;

    return flashFromPage ?? page.props.flash?.apiToken ?? null;
};

const applyIssuedToken = (token: IssuedApiToken): void => {
    if (!token) {
        return;
    }

    issuedToken.value = token;
    issuedTokenDialogOpen.value = true;
    copiedToken.value = false;
};

const issuedToken = ref<IssuedApiToken>(readFlashApiToken());
const issuedTokenDialogOpen = ref(issuedToken.value !== null);
const createTokenSectionId = 'api-create-token';
const tokensSectionId = 'api-tokens';
const documentationSectionId = 'api-documentation';
const documentationSectionAnchorId = (index: number): string =>
    `api-doc-section-${index + 1}`;

const form = useForm({
    name: '',
    permissions: [] as string[],
    never_expires: true,
    expires_at: '',
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.api.title,
                href: edit(),
            },
        ],
    });
});

watch(
    () => readFlashApiToken(),
    (flashToken) => {
        applyIssuedToken(flashToken);
    },
    { immediate: true },
);

const navigationSections = computed(() => {
    const sections = [];

    if (props.can.manage_tokens) {
        sections.push(
            {
                id: createTokenSectionId,
                title: t.value.api.create_token,
            },
            {
                id: tokensSectionId,
                title: t.value.api.active_tokens,
            },
        );
    }

    sections.push({
        id: documentationSectionId,
        title: t.value.api.documentation_title,
    });

    return sections;
});

const documentationNavigationSections = computed(() =>
    props.documentation.map((section, index) => ({
        id: documentationSectionAnchorId(index),
        title: section.title,
    })),
);

const togglePermission = (
    permissions: string[],
    permission: string,
    checked: boolean | 'indeterminate',
): string[] => {
    const values = new Set(permissions);

    if (checked === true) {
        values.add(permission);
    } else {
        values.delete(permission);
    }

    return [...values];
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

const submit = (): void => {
    form.never_expires = form.expires_at.trim() === '';
    form.transform((data) => ({
        ...data,
        permissions: serializePermissions(data.permissions),
        never_expires: data.expires_at.trim() === '',
        expires_at: data.expires_at.trim() || null,
    }));

    form.post(store.url(), {
        preserveScroll: true,
        onFlash: (flash: { apiToken?: IssuedApiToken }) => {
            applyIssuedToken(flash.apiToken ?? null);
        },
        onSuccess: () => {
            form.reset();
            form.permissions = [];
            form.never_expires = true;
            form.expires_at = '';
            copiedToken.value = false;
        },
    });
};

const deleteToken = (token: TokenRow): void => {
    router.delete(destroy.url(token.id), {
        preserveScroll: true,
    });
};

const copyToken = async (): Promise<void> => {
    if (!issuedToken.value?.token) {
        return;
    }

    await navigator.clipboard.writeText(issuedToken.value.token);
    copiedToken.value = true;
};

const closeIssuedTokenDialog = (): void => {
    issuedTokenDialogOpen.value = false;
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
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
</script>

<template>
    <Head :title="t.api.title" />

    <h1 class="sr-only">{{ t.api.title }}</h1>

    <Dialog
        :open="issuedTokenDialogOpen"
        @update:open="(isOpen) => { if (!isOpen) closeIssuedTokenDialog(); }"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ShieldCheck class="size-5" />
                    {{ t.api.generated_token_title }}
                </DialogTitle>
                <DialogDescription>
                    {{ t.api.generated_token_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="issuedToken" class="grid gap-2">
                <Label>{{ t.api.plain_token }}</Label>
                <Input :model-value="issuedToken.token" readonly />
                <p class="text-xs text-muted-foreground">
                    {{ t.api.copy_hint }}
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button type="button" variant="outline" @click="closeIssuedTokenDialog">
                    {{ t.common.cancel }}
                </Button>
                <Button type="button" @click="copyToken">
                    <Copy class="size-4" />
                    {{ copiedToken ? t.common.copied : t.api.copy_token }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.api.title"
            :description="t.api.description"
        />

        <section class="space-y-4 rounded-2xl border border-border p-5">
            <div class="flex items-center gap-2 text-base font-medium">
                <BookText class="size-5" />
                {{ t.api.overview_title }}
            </div>

            <p class="text-sm text-muted-foreground">
                {{ t.api.overview_description }}
            </p>

            <p class="rounded-xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                {{ t.api.target_user_overview }}
            </p>

            <div class="grid gap-2">
                <Label>{{ t.api.base_url }}</Label>
                <Input :model-value="props.baseUrl" readonly />
            </div>
        </section>

        <nav
            aria-label="API sections"
            class="rounded-2xl border border-border bg-card p-4"
        >
            <div class="flex flex-wrap gap-3">
                <a
                    v-for="section in navigationSections"
                    :key="section.id"
                    :href="`#${section.id}`"
                    class="inline-flex items-center rounded-full border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                >
                    {{ section.title }}
                </a>
            </div>
        </nav>

        <section
            v-if="props.can.manage_tokens"
            class="space-y-6 rounded-2xl border border-border p-5"
        >
            <Heading
                variant="small"
                :title="t.api.token_management"
                :description="t.api.token_management_description"
            />

            <form
                :id="createTokenSectionId"
                class="scroll-mt-6 space-y-6 rounded-2xl border border-border p-4"
                @submit.prevent="submit"
            >
                <div class="flex items-center gap-2 text-base font-medium">
                    <KeyRound class="size-5" />
                    {{ t.api.create_token }}
                </div>

                <div class="grid gap-2">
                    <Label for="api_token_name">{{ t.api.token_name }}</Label>
                    <Input
                        id="api_token_name"
                        v-model="form.name"
                        :placeholder="t.api.token_name_placeholder"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-3">
                    <Label>{{ t.api.permissions }}</Label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="permission in props.permissions"
                            :key="permission.key"
                            class="flex items-start gap-3 rounded-2xl border border-border p-3"
                        >
                            <Checkbox
                                :checked="
                                    form.permissions.includes(permission.key)
                                "
                                @update:checked="
                                    (value: boolean | 'indeterminate') =>
                                        (form.permissions = togglePermission(
                                            form.permissions,
                                            permission.key,
                                            value,
                                        ))
                                "
                            />
                            <div class="space-y-1">
                                <div class="text-sm font-medium">
                                    {{ permission.label }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ permission.description }}
                                </div>
                            </div>
                        </label>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>

                <div class="grid gap-2">
                    <Label for="api_token_expires_at">{{
                        t.api.expires_at
                    }}</Label>
                    <Input
                        id="api_token_expires_at"
                        v-model="form.expires_at"
                        type="datetime-local"
                    />
                    <InputError :message="form.errors.expires_at" />
                    <p class="text-xs text-muted-foreground">
                        {{ t.api.expires_at_help }}
                    </p>
                </div>

                <Button type="submit" :disabled="form.processing">
                    {{ t.api.create_token }}
                </Button>
            </form>

            <section
                :id="tokensSectionId"
                class="scroll-mt-6 space-y-3"
            >
                <Heading variant="small" :title="t.api.active_tokens" />

                <div
                    v-if="props.tokens.length"
                    class="overflow-x-auto rounded-2xl border border-border"
                >
                    <table class="w-full min-w-[920px] text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ t.api.token_name }}
                                </th>
                                <th
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ t.api.token_prefix }}
                                </th>
                                <th
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ t.api.permissions }}
                                </th>
                                <th
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ t.api.last_used_at }}
                                </th>
                                <th
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ t.api.expires_at_column }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ t.admin.actions }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="token in props.tokens" :key="token.id">
                                <td
                                    class="border-r border-border px-4 py-3 font-medium"
                                >
                                    {{ token.name }}
                                </td>
                                <td
                                    class="border-r border-border px-4 py-3 text-muted-foreground"
                                >
                                    {{ token.token_prefix }}
                                </td>
                                <td
                                    class="border-r border-border px-4 py-3 text-muted-foreground"
                                >
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="permission in token.permissions"
                                            :key="permission"
                                            class="rounded-full bg-muted px-2 py-1 text-xs"
                                        >
                                            {{ permission }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="border-r border-border px-4 py-3 text-muted-foreground"
                                >
                                    {{ formatDateTime(token.last_used_at) }}
                                </td>
                                <td
                                    class="border-r border-border px-4 py-3 text-muted-foreground"
                                >
                                    {{
                                        token.is_expired
                                            ? t.common.expired
                                            : formatDateTime(token.expires_at)
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="deleteToken(token)"
                                    >
                                        <Trash2 class="size-4" />
                                        {{ t.api.revoke }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-border p-4 text-sm text-muted-foreground"
                >
                    {{ t.api.no_tokens }}
                </div>
            </section>
        </section>

        <section
            :id="documentationSectionId"
            class="scroll-mt-6 space-y-4 rounded-2xl border border-border p-5"
        >
            <Heading
                variant="small"
                :title="t.api.documentation_title"
                :description="t.api.documentation_description"
            />

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start xl:gap-6">
                <details
                    open
                    class="rounded-2xl border border-border bg-background/95 p-4 shadow-sm xl:hidden"
                >
                    <summary class="cursor-pointer list-none text-sm font-semibold">
                        {{ t.api.documentation_blocks }}
                    </summary>

                    <div class="mt-4 grid gap-2">
                        <a
                            v-for="section in documentationNavigationSections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                        >
                            {{ section.title }}
                        </a>
                    </div>
                </details>

                <div class="space-y-4">
                    <div
                        v-for="(section, index) in props.documentation"
                        :id="documentationSectionAnchorId(index)"
                        :key="section.title"
                        class="scroll-mt-24 space-y-3 rounded-2xl border border-border p-4"
                    >
                        <div>
                            <h2 class="text-base font-semibold">{{ section.title }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ section.description }}
                            </p>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-border">
                            <table class="w-full min-w-[1260px] text-sm">
                                <thead class="bg-muted/50 text-left">
                                    <tr>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.method }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.path }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.summary }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.permission }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.access }}
                                        </th>
                                        <th
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ t.api.target_user }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.api.content_type }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="endpoint in section.endpoints"
                                        :key="`${endpoint.method}-${endpoint.path}`"
                                    >
                                        <td
                                            class="border-r border-border px-4 py-3 font-medium"
                                        >
                                            {{ endpoint.method }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 font-mono text-xs"
                                        >
                                            {{ endpoint.path }}
                                        </td>
                                        <td class="border-r border-border px-4 py-3">
                                            {{ endpoint.summary }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 font-mono text-xs"
                                        >
                                            {{ endpoint.permission }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 text-muted-foreground"
                                        >
                                            {{ endpoint.access }}
                                        </td>
                                        <td
                                            class="border-r border-border px-4 py-3 text-xs text-muted-foreground"
                                        >
                                            {{ endpoint.target_user }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs">
                                            {{ endpoint.content_type }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <aside class="hidden xl:block xl:sticky xl:top-24">
                    <div
                        class="rounded-2xl border border-border bg-background/95 p-4 shadow-sm supports-[backdrop-filter]:bg-background/80 supports-[backdrop-filter]:backdrop-blur"
                    >
                        <div class="text-sm font-semibold">
                            {{ t.api.documentation_blocks }}
                        </div>

                        <div class="mt-4 grid gap-2">
                            <a
                                v-for="section in documentationNavigationSections"
                                :key="section.id"
                                :href="`#${section.id}`"
                                class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                            >
                                {{ section.title }}
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
