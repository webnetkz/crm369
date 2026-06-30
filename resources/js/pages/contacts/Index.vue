<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Search, Trash2, UserRound } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/ContactController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/contacts';
import type { PaginatedCollection } from '@/types/ui';

type ContactType = 'person' | 'company';

type ContactActor = {
    id: number;
    name: string;
    last_name: string | null;
} | null;

type ContactRequisitesKey =
    | 'iin'
    | 'bin'
    | 'legal_address'
    | 'actual_address'
    | 'bank_name'
    | 'bank_bik'
    | 'iban'
    | 'kbe';

type ContactRequisitesForm = Record<ContactRequisitesKey, string>;
type ContactRequisitesRow = Partial<Record<ContactRequisitesKey, string | null>> | null;

type ContactRow = {
    id: number;
    type: ContactType;
    type_label: string;
    name: string;
    contact_person: string | null;
    position: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    company_requisites: ContactRequisitesRow;
    created_at: string | null;
    updated_at: string | null;
    created_by: ContactActor;
    updated_by: ContactActor;
};

type Filters = {
    search: string;
    type: 'all' | ContactType;
    per_page: number;
};

type ContactTypeOption = {
    value: ContactType;
    label: string;
};

const props = defineProps<{
    contacts: PaginatedCollection<ContactRow>;
    filters: Filters;
    availableTypes: ContactTypeOption[];
    perPageOptions: number[];
    can: {
        create_person: boolean;
        create_company: boolean;
    };
}>();

const { language, t } = useLanguage();
const dialogOpen = ref(false);
const requisitesDialogOpen = ref(false);
const editingContactId = ref<number | null>(null);

const emptyContactRequisites = (): ContactRequisitesForm => ({
    iin: '',
    bin: '',
    legal_address: '',
    actual_address: '',
    bank_name: '',
    bank_bik: '',
    iban: '',
    kbe: '',
});

const filtersForm = useForm<Filters>({
    search: props.filters.search,
    type: props.filters.type,
    per_page: props.filters.per_page,
});

const contactForm = useForm({
    type: (props.availableTypes[0]?.value ?? 'person') as ContactType,
    name: '',
    contact_person: '',
    position: '',
    email: '',
    phone: '',
    notes: '',
    company_requisites: emptyContactRequisites(),
});

const canCreateAny = computed(() => {
    return props.can.create_person || props.can.create_company;
});

const requisitesFieldsForType = (
    type: ContactType,
): Array<{ key: ContactRequisitesKey; label: string; textarea?: boolean }> => {
    if (type === 'person') {
        return [{ key: 'iin', label: t.value.contacts.iin }];
    }

    return [
        { key: 'bin', label: t.value.contacts.bin },
        { key: 'legal_address', label: t.value.contacts.legal_address, textarea: true },
        { key: 'actual_address', label: t.value.contacts.actual_address, textarea: true },
        { key: 'bank_name', label: t.value.contacts.bank_name },
        { key: 'bank_bik', label: t.value.contacts.bank_bik },
        { key: 'iban', label: t.value.contacts.iban },
        { key: 'kbe', label: t.value.contacts.kbe },
    ];
};

const contactRequisitesFields = computed(() => {
    return requisitesFieldsForType(contactForm.type);
});

const dialogTitle = computed(() => {
    return editingContactId.value === null
        ? t.value.contacts.create_title
        : t.value.contacts.edit_title;
});

const dialogDescription = computed(() => {
    return contactForm.type === 'company'
        ? t.value.contacts.type_company
        : t.value.contacts.type_person;
});

const namePlaceholder = computed(() => {
    return contactForm.type === 'company'
        ? t.value.contacts.name_placeholder_company
        : t.value.contacts.name_placeholder_person;
});

const contactRequisitesFilledCount = computed(() => {
    return Object.values(contactForm.company_requisites).filter(
        (value) => value.trim() !== '',
    ).length;
});

const hasContactRequisites = computed(() => {
    return contactRequisitesFilledCount.value > 0;
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.contacts.title,
                href: index(),
            },
        ],
    });
});

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

const canCreateType = (type: ContactType): boolean => {
    return type === 'person'
        ? props.can.create_person
        : props.can.create_company;
};

watch(
    () => contactForm.type,
    () => {
        requisitesDialogOpen.value = false;
        contactForm.company_requisites = emptyContactRequisites();
        contactForm.clearErrors(
            'company_requisites.iin',
            'company_requisites.bin',
            'company_requisites.legal_address',
            'company_requisites.actual_address',
            'company_requisites.bank_name',
            'company_requisites.bank_bik',
            'company_requisites.iban',
            'company_requisites.kbe',
        );
    },
);

const assignContactRequisites = (value?: ContactRequisitesRow): void => {
    contactForm.company_requisites = {
        iin: value?.iin ?? '',
        bin: value?.bin ?? '',
        legal_address: value?.legal_address ?? '',
        actual_address: value?.actual_address ?? '',
        bank_name: value?.bank_name ?? '',
        bank_bik: value?.bank_bik ?? '',
        iban: value?.iban ?? '',
        kbe: value?.kbe ?? '',
    };
};

const requisitesValue = (
    requisites: ContactRequisitesRow,
    key: ContactRequisitesKey,
): string => {
    return requisites?.[key] ?? '';
};

const contactHasRequisites = (contact: ContactRow): boolean => {
    return Object.values(contact.company_requisites ?? {}).some(
        (value) => typeof value === 'string' && value.trim() !== '',
    );
};

const resetContactForm = (): void => {
    contactForm.reset();
    contactForm.clearErrors();
    editingContactId.value = null;
    contactForm.type = (props.availableTypes[0]?.value ?? 'person') as ContactType;
    contactForm.company_requisites = emptyContactRequisites();
    requisitesDialogOpen.value = false;
};

const openCreateDialog = (type: ContactType): void => {
    resetContactForm();
    contactForm.type = type;
    dialogOpen.value = true;
};

const openEditDialog = (contact: ContactRow): void => {
    editingContactId.value = contact.id;
    contactForm.clearErrors();
    contactForm.type = contact.type;
    contactForm.name = contact.name;
    contactForm.contact_person = contact.contact_person ?? '';
    contactForm.position = contact.position ?? '';
    contactForm.email = contact.email ?? '';
    contactForm.phone = contact.phone ?? '';
    contactForm.notes = contact.notes ?? '';
    assignContactRequisites(contact.company_requisites);
    dialogOpen.value = true;
};

const closeDialog = (): void => {
    dialogOpen.value = false;
    resetContactForm();
};

const closeRequisitesDialog = (): void => {
    requisitesDialogOpen.value = false;
};

const requisitesDescription = (type: ContactType): string => {
    return type === 'company'
        ? t.value.contacts.requisites_description_company
        : t.value.contacts.requisites_description_person;
};

const requisitesModalTitle = computed(() => {
    return contactForm.type === 'company'
        ? t.value.contacts.requisites_modal_title_company
        : t.value.contacts.requisites_modal_title_person;
});

const requisitesModalDescription = computed(() => {
    return contactForm.type === 'company'
        ? t.value.contacts.requisites_modal_description_company
        : t.value.contacts.requisites_modal_description_person;
});

const normalizeRequisitesField = (key: ContactRequisitesKey): void => {
    if (key !== 'bin' && key !== 'iin') {
        return;
    }

    contactForm.company_requisites[key] = contactForm.company_requisites[key]
        .replace(/\D/g, '')
        .slice(0, 12);
};

const handleDialogOpenChange = (value: boolean): void => {
    if (value) {
        dialogOpen.value = true;

        return;
    }

    closeDialog();
};

const submitFilters = (): void => {
    router.get(
        index.url(),
        {
            search: filtersForm.search,
            type: filtersForm.type,
            per_page: filtersForm.per_page,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const updatePerPage = (value: number): void => {
    filtersForm.per_page = value;
    submitFilters();
};

const submitContact = (): void => {
    if (editingContactId.value === null) {
        contactForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });

        return;
    }

    contactForm.patch(update.url(editingContactId.value), {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

const deleteContact = (contact: ContactRow): void => {
    const confirmed = window.confirm(
        `${t.value.contacts.delete}: ${contact.name}?`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(destroy.url(contact.id), {
        preserveScroll: true,
    });
};

const actorName = (actor: ContactActor): string => {
    if (!actor) {
        return '';
    }

    return [actor.name, actor.last_name].filter(Boolean).join(' ');
};
</script>

<template>
    <Head :title="t.contacts.title" />

    <div class="space-y-8">
        <section
            class="rounded-3xl border border-border bg-gradient-to-br from-primary/10 via-background to-background p-6"
        >
            <Heading
                variant="small"
                :title="t.contacts.title"
                :description="t.contacts.description"
            />

            <div class="mt-6 flex flex-wrap gap-3">
                <Button
                    v-if="canCreateType('person')"
                    type="button"
                    @click="openCreateDialog('person')"
                >
                    <Plus class="size-4" />
                    {{ t.contacts.create_person }}
                </Button>
                <Button
                    v-if="canCreateType('company')"
                    type="button"
                    variant="outline"
                    @click="openCreateDialog('company')"
                >
                    <Plus class="size-4" />
                    {{ t.contacts.create_company }}
                </Button>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="space-y-2">
                    <Label for="contacts-search">{{ t.contacts.search }}</Label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="contacts-search"
                            v-model="filtersForm.search"
                            class="pl-9"
                            :placeholder="t.contacts.search_placeholder"
                            @keyup.enter="submitFilters"
                        />
                    </div>
                </div>

                <Button type="button" variant="outline" @click="submitFilters">
                    {{ t.contacts.search }}
                </Button>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    v-if="availableTypes.length > 1"
                    type="button"
                    size="sm"
                    :variant="filtersForm.type === 'all' ? 'default' : 'outline'"
                    @click="
                        filtersForm.type = 'all';
                        submitFilters();
                    "
                >
                    {{ t.contacts.all_types }}
                </Button>
                <Button
                    v-for="type in availableTypes"
                    :key="type.value"
                    type="button"
                    size="sm"
                    :variant="filtersForm.type === type.value ? 'default' : 'outline'"
                    @click="
                        filtersForm.type = type.value;
                        submitFilters();
                    "
                >
                    {{ type.label }}
                </Button>
            </div>
        </section>

        <section
            v-if="contacts.data.length === 0"
            class="rounded-2xl border border-dashed border-border bg-muted/20 p-10 text-center text-sm text-muted-foreground"
        >
            {{ t.contacts.empty }}
        </section>

        <section v-else class="grid gap-4">
            <article
                v-for="contact in contacts.data"
                :key="contact.id"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0 space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                            >
                                <Building2
                                    v-if="contact.type === 'company'"
                                    class="size-5"
                                />
                                <UserRound v-else class="size-5" />
                            </div>

                            <div class="space-y-1">
                                <h2 class="text-lg font-semibold">
                                    {{ contact.name }}
                                </h2>
                                <div class="text-sm text-muted-foreground">
                                    {{ contact.type_label }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-4"
                        >
                            <div v-if="contact.contact_person">
                                <div class="font-medium text-foreground">
                                    {{ t.contacts.contact_person }}
                                </div>
                                <div>{{ contact.contact_person }}</div>
                            </div>
                            <div v-if="contact.position">
                                <div class="font-medium text-foreground">
                                    {{ t.contacts.position }}
                                </div>
                                <div>{{ contact.position }}</div>
                            </div>
                            <div v-if="contact.email">
                                <div class="font-medium text-foreground">
                                    {{ t.contacts.email }}
                                </div>
                                <div>{{ contact.email }}</div>
                            </div>
                            <div v-if="contact.phone">
                                <div class="font-medium text-foreground">
                                    {{ t.contacts.phone }}
                                </div>
                                <div>{{ contact.phone }}</div>
                            </div>
                        </div>

                        <p
                            v-if="contact.notes"
                            class="rounded-2xl bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        >
                            {{ contact.notes }}
                        </p>

                        <div
                            v-if="
                                contactHasRequisites(contact)
                            "
                            class="space-y-3 rounded-2xl border border-border/60 bg-muted/20 p-4"
                        >
                            <div>
                                <div class="text-sm font-medium text-foreground">
                                    {{ t.contacts.requisites }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ requisitesDescription(contact.type) }}
                                </div>
                            </div>

                            <div class="grid gap-3 text-sm text-muted-foreground md:grid-cols-2 xl:grid-cols-3">
                                <div
                                    v-for="field in requisitesFieldsForType(contact.type)"
                                    v-show="
                                        requisitesValue(
                                            contact.company_requisites,
                                            field.key,
                                        )
                                    "
                                    :key="`${contact.id}-${field.key}`"
                                >
                                    <div class="font-medium text-foreground">
                                        {{ field.label }}
                                    </div>
                                    <div class="whitespace-pre-line">
                                        {{
                                            requisitesValue(
                                                contact.company_requisites,
                                                field.key,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-xs text-muted-foreground">
                            {{ t.contacts.last_update }}:
                            {{ formatDateTime(contact.updated_at) }}
                            <span v-if="contact.updated_by">
                                • {{ actorName(contact.updated_by) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openEditDialog(contact)"
                        >
                            <Pencil class="size-4" />
                            {{ t.common.edit }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="text-destructive"
                            @click="deleteContact(contact)"
                        >
                            <Trash2 class="size-4" />
                            {{ t.contacts.delete }}
                        </Button>
                    </div>
                </div>
            </article>
        </section>

        <PaginationControls
            :pagination="contacts"
            :per-page-options="perPageOptions"
            @update:per-page="updatePerPage"
        />

        <Dialog :open="dialogOpen" @update:open="handleDialogOpenChange">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription>{{ dialogDescription }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="type in availableTypes"
                            :key="type.value"
                            type="button"
                            size="sm"
                            :variant="contactForm.type === type.value ? 'default' : 'outline'"
                            @click="contactForm.type = type.value"
                        >
                            {{ type.label }}
                        </Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <Label for="contact-name">{{ t.contacts.name }}</Label>
                            <Input
                                id="contact-name"
                                v-model="contactForm.name"
                                :placeholder="namePlaceholder"
                            />
                            <InputError :message="contactForm.errors.name" />
                        </div>

                        <div
                            v-if="contactForm.type === 'company'"
                            class="space-y-2"
                        >
                            <Label for="contact-person">
                                {{ t.contacts.contact_person }}
                            </Label>
                            <Input
                                id="contact-person"
                                v-model="contactForm.contact_person"
                                :placeholder="t.contacts.contact_person_placeholder"
                            />
                            <InputError
                                :message="contactForm.errors.contact_person"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="contact-position">
                                {{ t.contacts.position }}
                            </Label>
                            <Input
                                id="contact-position"
                                v-model="contactForm.position"
                                :placeholder="t.contacts.position_placeholder"
                            />
                            <InputError :message="contactForm.errors.position" />
                        </div>

                        <div class="space-y-2">
                            <Label for="contact-email">{{ t.contacts.email }}</Label>
                            <Input
                                id="contact-email"
                                v-model="contactForm.email"
                                type="email"
                            />
                            <InputError :message="contactForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="contact-phone">{{ t.contacts.phone }}</Label>
                            <Input
                                id="contact-phone"
                                v-model="contactForm.phone"
                            />
                            <InputError :message="contactForm.errors.phone" />
                        </div>

                        <div class="space-y-3 md:col-span-2">
                            <div
                                class="flex flex-col gap-3 rounded-2xl border border-dashed border-border/80 bg-muted/20 p-4 md:flex-row md:items-center md:justify-between"
                            >
                                <div class="space-y-1">
                                    <div class="text-sm font-medium text-foreground">
                                        {{ t.contacts.requisites }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        {{
                                            hasContactRequisites
                                                ? t.contacts.requisites_filled.replace(
                                                      ':count',
                                                      String(
                                                          contactRequisitesFilledCount,
                                                      ),
                                                  )
                                                : t.contacts.requisites_empty
                                        }}
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="requisitesDialogOpen = true"
                                >
                                    {{
                                        hasContactRequisites
                                            ? t.contacts.requisites_edit_button
                                            : t.contacts.requisites_add_button
                                    }}
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="contact-notes">{{ t.contacts.notes }}</Label>
                            <textarea
                                id="contact-notes"
                                v-model="contactForm.notes"
                                class="min-h-28 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :placeholder="t.contacts.notes_placeholder"
                            ></textarea>
                            <InputError :message="contactForm.errors.notes" />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">
                        {{ t.common.cancel }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="contactForm.processing || !canCreateAny"
                        @click="submitContact"
                    >
                        {{
                            editingContactId === null
                                ? t.contacts.save
                                : t.contacts.update
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="requisitesDialogOpen"
            @update:open="requisitesDialogOpen = $event"
        >
            <DialogScrollContent class="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ requisitesModalTitle }}</DialogTitle>
                    <DialogDescription>
                        {{ requisitesModalDescription }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="field in contactRequisitesFields"
                        :key="field.key"
                        class="space-y-2"
                        :class="{ 'md:col-span-2': field.textarea }"
                    >
                        <Label :for="`company-requisites-${field.key}`">
                            {{ field.label }}
                        </Label>

                        <textarea
                            v-if="field.textarea"
                            :id="`company-requisites-${field.key}`"
                            v-model="contactForm.company_requisites[field.key]"
                            class="min-h-24 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <Input
                            v-else
                            :id="`company-requisites-${field.key}`"
                            v-model="contactForm.company_requisites[field.key]"
                            :inputmode="field.key === 'bin' || field.key === 'iin' ? 'numeric' : undefined"
                            :maxlength="field.key === 'bin' || field.key === 'iin' ? 12 : undefined"
                            @input="normalizeRequisitesField(field.key)"
                        />

                        <InputError
                            :message="
                                contactForm.errors[`company_requisites.${field.key}`]
                            "
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeRequisitesDialog"
                    >
                        {{ t.common.cancel }}
                    </Button>
                    <Button type="button" @click="closeRequisitesDialog">
                        {{ t.common.save }}
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
