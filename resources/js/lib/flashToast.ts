import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

const flashToastIdPrefix = 'flash-toast-';
let flashToastSequence = 0;
let clickListenerInitialized = false;

const dismissFlashToastOnClick = (event: MouseEvent): void => {
    const target = event.target instanceof Element ? event.target : null;
    const toastElement = target?.closest<HTMLElement>(
        `[data-sonner-toast][data-testid^="${flashToastIdPrefix}"]`,
    );
    const toastId = toastElement?.getAttribute('data-testid');

    if (toastId) {
        toast.dismiss(toastId);
    }
};

export function initializeFlashToast(): void {
    if (!clickListenerInitialized && typeof document !== 'undefined') {
        document.addEventListener('click', dismissFlashToastOnClick);
        clickListenerInitialized = true;
    }

    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        const toastId = `${flashToastIdPrefix}${Date.now()}-${flashToastSequence++}`;

        toast[data.type](data.message, {
            id: toastId,
            testId: toastId,
            class: 'cursor-pointer',
        });
    });
}
