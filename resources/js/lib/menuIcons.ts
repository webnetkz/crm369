import type { LucideIcon } from '@lucide/vue';
import {
    BellRing,
    BookOpenText,
    ClipboardList,
    FolderOpen,
    Globe,
    LayoutDashboard,
    LayoutGrid,
    LinkIcon,
    ListTodo,
    MessageSquareMore,
    Newspaper,
    Rocket,
    ShieldCheck,
} from '@lucide/vue';

export const menuIconMap = {
    link: LinkIcon,
    globe: Globe,
    book: BookOpenText,
    folder: FolderOpen,
    dashboard: LayoutDashboard,
    grid: LayoutGrid,
    clipboard: ClipboardList,
    message: MessageSquareMore,
    news: Newspaper,
    tasks: ListTodo,
    bell: BellRing,
    shield: ShieldCheck,
    rocket: Rocket,
} as const satisfies Record<string, LucideIcon>;

export type MenuIconName = keyof typeof menuIconMap;

export const resolveMenuIcon = (
    icon: string | null | undefined,
): LucideIcon => {
    if (icon && icon in menuIconMap) {
        return menuIconMap[icon as MenuIconName];
    }

    return LinkIcon;
};
