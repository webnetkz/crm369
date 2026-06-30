import type { CSSProperties } from 'vue';
import type { PortalFormStyleSettings, PortalFormWidth } from '@/types/ui';

const widthClasses: Record<PortalFormWidth, string> = {
    sm: 'max-w-xl',
    md: 'max-w-2xl',
    lg: 'max-w-3xl',
    xl: 'max-w-4xl',
};

const clamp = (value: number, min: number, max: number): number => {
    return Math.min(max, Math.max(min, value));
};

const hexToRgb = (color: string): [number, number, number] | null => {
    const normalized = color.trim();

    if (!/^#[0-9A-Fa-f]{6}$/.test(normalized)) {
        return null;
    }

    return [
        Number.parseInt(normalized.slice(1, 3), 16),
        Number.parseInt(normalized.slice(3, 5), 16),
        Number.parseInt(normalized.slice(5, 7), 16),
    ];
};

export const portalFormWidthClass = (width: PortalFormWidth): string => {
    return widthClasses[width] ?? widthClasses.lg;
};

export const clonePortalFormStyleSettings = (
    settings: PortalFormStyleSettings,
): PortalFormStyleSettings => {
    return { ...settings };
};

export const colorWithAlpha = (color: string, alpha: number): string => {
    const rgb = hexToRgb(color);

    if (!rgb) {
        return color;
    }

    return `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${clamp(alpha, 0, 1)})`;
};

export const buildPortalFormCardStyle = (
    settings: PortalFormStyleSettings,
): CSSProperties => {
    return {
        backgroundColor: settings.background_color,
        borderColor: settings.border_color,
        color: settings.text_color,
        borderRadius: `${clamp(settings.border_radius, 12, 32)}px`,
        padding: `${clamp(settings.padding, 20, 48)}px`,
    };
};

export const buildPortalFormInputStyle = (
    settings: PortalFormStyleSettings,
): CSSProperties => {
    return {
        backgroundColor: settings.input_background_color,
        borderColor: settings.input_border_color,
        color: settings.text_color,
        borderRadius: `${Math.max(10, clamp(settings.border_radius, 12, 32) - 10)}px`,
    };
};

export const buildPortalFormButtonStyle = (
    settings: PortalFormStyleSettings,
): CSSProperties => {
    return {
        backgroundColor: settings.button_background_color,
        borderColor: settings.button_background_color,
        color: settings.button_text_color,
        borderRadius: `${Math.max(10, clamp(settings.border_radius, 12, 32) - 8)}px`,
    };
};

export const buildPortalFormMutedTextStyle = (
    settings: PortalFormStyleSettings,
): CSSProperties => {
    return {
        color: colorWithAlpha(settings.text_color, 0.72),
    };
};

export const buildPortalFormBadgeStyle = (
    settings: PortalFormStyleSettings,
): CSSProperties => {
    return {
        backgroundColor: colorWithAlpha(settings.button_background_color, 0.12),
        borderColor: colorWithAlpha(settings.button_background_color, 0.16),
        color: settings.button_background_color,
    };
};
