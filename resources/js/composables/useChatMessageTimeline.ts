import type { ComputedRef, Ref } from 'vue';
import { computed } from 'vue';
import type { ChatMessageItem, Language } from '@/types/ui';

type ChatTimelineSeparator = {
    type: 'separator';
    key: string;
    label: string;
};

type ChatTimelineMessage = {
    type: 'message';
    key: string;
    message: ChatMessageItem;
};

export type ChatTimelineEntry = ChatTimelineSeparator | ChatTimelineMessage;

const resolveLocale = (language: Language): string => {
    return language === 'ru' ? 'ru-RU' : 'en-US';
};

const calendarDayKey = (date: Date): string => {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export function useChatMessageTimeline(
    messages: ComputedRef<ChatMessageItem[]>,
    language: Ref<Language>,
): {
    timelineEntries: ComputedRef<ChatTimelineEntry[]>;
    formatDateTime: (value: string | null, short?: boolean) => string;
    formatDayLabel: (value: string | null) => string;
} {
    const formatDateTime = (value: string | null, short = false): string => {
        if (!value) {
            return '';
        }

        const locale = resolveLocale(language.value);

        return short
            ? new Intl.DateTimeFormat(locale, {
                  hour: '2-digit',
                  minute: '2-digit',
              }).format(new Date(value))
            : new Intl.DateTimeFormat(locale, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value));
    };

    const formatDayLabel = (value: string | null): string => {
        if (!value) {
            return '';
        }

        return new Intl.DateTimeFormat(resolveLocale(language.value), {
            dateStyle: 'medium',
        }).format(new Date(value));
    };

    const timelineEntries = computed<ChatTimelineEntry[]>(() => {
        let previousDayKey: string | null = null;

        return messages.value.flatMap((message) => {
            const createdAt = message.createdAt;

            if (!createdAt) {
                return [
                    {
                        type: 'message',
                        key: `message-${message.id}`,
                        message,
                    },
                ];
            }

            const createdAtDate = new Date(createdAt);
            const dayKey = calendarDayKey(createdAtDate);
            const entries: ChatTimelineEntry[] = [];

            if (dayKey !== previousDayKey) {
                entries.push({
                    type: 'separator',
                    key: `separator-${dayKey}`,
                    label: formatDayLabel(createdAt),
                });
                previousDayKey = dayKey;
            }

            entries.push({
                type: 'message',
                key: `message-${message.id}`,
                message,
            });

            return entries;
        });
    });

    return {
        timelineEntries,
        formatDateTime,
        formatDayLabel,
    };
}
