import type { ComputedRef } from 'vue';
import { computed, ref } from 'vue';

export type BackgroundPreviewSettings = {
    color: string | null;
    image: string | null;
    blur: number;
};

export type UseBackgroundPreviewReturn = {
    persisted: ComputedRef<BackgroundPreviewSettings | null>;
    preview: ComputedRef<BackgroundPreviewSettings | null>;
    setPersisted: (value: BackgroundPreviewSettings | null) => void;
    setPreview: (value: BackgroundPreviewSettings) => void;
    clearPreview: () => void;
};

const persistedColor = ref<string | null>(null);
const persistedImage = ref<string | null>(null);
const persistedBlur = ref(0);
const hasPersistedBackground = ref(false);
const previewColor = ref<string | null>(null);
const previewImage = ref<string | null>(null);
const previewBlur = ref(0);
const isPreviewActive = ref(false);

export function useBackgroundPreview(): UseBackgroundPreviewReturn {
    const persisted = computed<BackgroundPreviewSettings | null>(() => {
        if (!hasPersistedBackground.value) {
            return null;
        }

        return {
            color: persistedColor.value,
            image: persistedImage.value,
            blur: persistedBlur.value,
        };
    });

    const preview = computed<BackgroundPreviewSettings | null>(() => {
        if (!isPreviewActive.value) {
            return null;
        }

        return {
            color: previewColor.value,
            image: previewImage.value,
            blur: previewBlur.value,
        };
    });

    const setPersisted = (value: BackgroundPreviewSettings | null): void => {
        if (value === null) {
            persistedColor.value = null;
            persistedImage.value = null;
            persistedBlur.value = 0;
            hasPersistedBackground.value = false;

            return;
        }

        persistedColor.value = value.color;
        persistedImage.value = value.image;
        persistedBlur.value = value.blur;
        hasPersistedBackground.value = true;
    };

    const setPreview = (value: BackgroundPreviewSettings): void => {
        previewColor.value = value.color;
        previewImage.value = value.image;
        previewBlur.value = value.blur;
        isPreviewActive.value = true;
    };

    const clearPreview = (): void => {
        previewColor.value = null;
        previewImage.value = null;
        previewBlur.value = 0;
        isPreviewActive.value = false;
    };

    return {
        persisted,
        preview,
        setPersisted,
        setPreview,
        clearPreview,
    };
}
