import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watchEffect } from 'vue';
import { update } from '@/routes/language';
import type { Language, LocaleMessages } from '@/types';

export type UseLanguageReturn = {
    language: Ref<Language>;
    t: ComputedRef<LocaleMessages>;
    updateLanguage: (value: Language) => Promise<void>;
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const globalLanguage = ref<Language>('ru');

export function useLanguage(): UseLanguageReturn {
    const page = usePage();

    watchEffect(() => {
        globalLanguage.value = page.props.locale.current;
        setCookie('language', globalLanguage.value);
    });

    const t = computed(() => {
        return page.props.locale.messages;
    });

    const updateLanguage = async (value: Language) => {
        setCookie('language', value);

        router.post(
            update.url(),
            { language: value },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return {
        language: globalLanguage,
        t,
        updateLanguage,
    };
}

export function initializeLanguage(): void {
    setCookie('language', globalLanguage.value);
}
