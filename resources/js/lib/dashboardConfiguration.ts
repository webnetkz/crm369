import type {
    DashboardConfiguration,
    DashboardDefinition,
    DashboardWidget,
} from '@/types/dashboard';

export const cloneDashboardWidgets = (
    widgets: DashboardWidget[],
): DashboardWidget[] => {
    return widgets.map((widget) => ({ ...widget }));
};

export const cloneDashboardDefinition = (
    dashboard: DashboardDefinition,
): DashboardDefinition => {
    return {
        ...dashboard,
        widgets: cloneDashboardWidgets(dashboard.widgets),
    };
};

export const cloneDashboardConfiguration = (
    configuration: DashboardConfiguration,
): DashboardConfiguration => {
    return {
        version: configuration.version,
        activeDashboardId: configuration.activeDashboardId,
        dashboards: configuration.dashboards.map(cloneDashboardDefinition),
    };
};
