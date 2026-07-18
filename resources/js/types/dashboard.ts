export type DashboardWidgetId =
    'highlights' | 'metrics' | 'activity' | 'donuts' | 'bars' | 'radar';

export type DashboardWidgetSize = 'standard' | 'wide' | 'full';
export type DashboardDensity = 'comfortable' | 'compact';
export type DashboardChartType =
    'cards' | 'area' | 'line' | 'donut' | 'progress' | 'bars' | 'radar';

export type DashboardWidget = {
    id: DashboardWidgetId;
    visible: boolean;
    size: DashboardWidgetSize;
    chartType: DashboardChartType;
};

export type DashboardDefinition = {
    id: string;
    name: string;
    period: 7 | 14 | 30;
    density: DashboardDensity;
    widgets: DashboardWidget[];
};

export type DashboardConfiguration = {
    version: number;
    activeDashboardId: string;
    dashboards: DashboardDefinition[];
};

export type DashboardCard = {
    title: string;
    value: string;
    helper: string;
    icon: string;
};

export type DashboardDonutSegment = {
    label: string;
    value: number;
    color: string;
};

export type DashboardDonut = {
    title: string;
    subtitle: string;
    total: number;
    totalLabel: string;
    highlight: string;
    highlightLabel: string;
    segments: DashboardDonutSegment[];
};

export type DashboardActivitySeries = {
    label: string;
    values: number[];
    color: string;
};

export type DashboardActivity = {
    title: string;
    subtitle: string;
    labels: string[];
    series: DashboardActivitySeries[];
};

export type DashboardBarItem = {
    label: string;
    value: number;
    color: string;
};

export type DashboardRadarItem = {
    label: string;
    value: number;
    helper: string;
};

export type DashboardHighlight = {
    label: string;
    value: string;
    helper: string;
};

export type DashboardStats = {
    eyebrow: string;
    subtitle: string;
    cards: DashboardCard[];
    donuts: DashboardDonut[];
    activity: DashboardActivity;
    bars: {
        title: string;
        subtitle: string;
        items: DashboardBarItem[];
    };
    radar: {
        title: string;
        subtitle: string;
        items: DashboardRadarItem[];
    };
    highlights: DashboardHighlight[];
};
