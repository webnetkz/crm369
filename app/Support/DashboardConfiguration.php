<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DashboardConfiguration
{
    public const int VERSION = 1;

    public const int MAX_DASHBOARDS = 6;

    /** @var list<int> */
    public const array PERIODS = [7, 14, 30];

    /** @var list<string> */
    public const array DENSITIES = ['comfortable', 'compact'];

    /** @var list<string> */
    public const array SIZES = ['standard', 'wide', 'full'];

    /** @var array<string, array{size: string, chartType: string, chartTypes: list<string>}> */
    public const array WIDGETS = [
        'highlights' => ['size' => 'full', 'chartType' => 'cards', 'chartTypes' => ['cards']],
        'metrics' => ['size' => 'full', 'chartType' => 'cards', 'chartTypes' => ['cards']],
        'activity' => ['size' => 'full', 'chartType' => 'area', 'chartTypes' => ['area', 'line']],
        'donuts' => ['size' => 'full', 'chartType' => 'donut', 'chartTypes' => ['donut', 'progress']],
        'bars' => ['size' => 'wide', 'chartType' => 'bars', 'chartTypes' => ['bars', 'progress']],
        'radar' => ['size' => 'standard', 'chartType' => 'radar', 'chartTypes' => ['radar', 'progress']],
    ];

    /**
     * @param  array<string, mixed>|null  $configuration
     * @return array{version: int, activeDashboardId: string, dashboards: list<array{id: string, name: string, period: int, density: string, widgets: list<array{id: string, visible: bool, size: string, chartType: string}>}>}
     */
    public function normalize(?array $configuration): array
    {
        $storedDashboards = Arr::get($configuration ?? [], 'dashboards', []);
        $storedDashboards = is_array($storedDashboards) ? array_values($storedDashboards) : [];
        $dashboards = [];

        foreach ($storedDashboards as $storedDashboard) {
            if (! is_array($storedDashboard) || count($dashboards) >= self::MAX_DASHBOARDS) {
                continue;
            }

            $dashboard = $this->normalizeDashboard($storedDashboard, count($dashboards));

            if (in_array($dashboard['id'], array_column($dashboards, 'id'), true)) {
                continue;
            }

            $dashboards[] = $dashboard;
        }

        if ($dashboards === []) {
            $dashboards = [$this->defaultDashboard()];
        }

        $activeDashboardId = Arr::get($configuration ?? [], 'activeDashboardId');
        $dashboardIds = array_column($dashboards, 'id');

        if (! is_string($activeDashboardId) || ! in_array($activeDashboardId, $dashboardIds, true)) {
            $activeDashboardId = $dashboards[0]['id'];
        }

        return [
            'version' => self::VERSION,
            'activeDashboardId' => $activeDashboardId,
            'dashboards' => $dashboards,
        ];
    }

    /**
     * @param  array{version: int, activeDashboardId: string, dashboards: list<array{id: string, name: string, period: int, density: string, widgets: list<array{id: string, visible: bool, size: string, chartType: string}>}>}  $configuration
     * @return array{id: string, name: string, period: int, density: string, widgets: list<array{id: string, visible: bool, size: string, chartType: string}>}
     */
    public function activeDashboard(array $configuration): array
    {
        foreach ($configuration['dashboards'] as $dashboard) {
            if ($dashboard['id'] === $configuration['activeDashboardId']) {
                return $dashboard;
            }
        }

        return $configuration['dashboards'][0];
    }

    /** @return list<string> */
    public static function widgetIds(): array
    {
        return array_keys(self::WIDGETS);
    }

    /** @return list<string> */
    public static function chartTypes(): array
    {
        $chartTypes = [];

        foreach (self::WIDGETS as $widget) {
            foreach ($widget['chartTypes'] as $chartType) {
                if (! in_array($chartType, $chartTypes, true)) {
                    $chartTypes[] = $chartType;
                }
            }
        }

        return $chartTypes;
    }

    /** @return list<string> */
    public static function chartTypesFor(string $widgetId): array
    {
        return self::WIDGETS[$widgetId]['chartTypes'] ?? [];
    }

    /**
     * @return array{id: string, name: string, period: int, density: string, widgets: list<array{id: string, visible: bool, size: string, chartType: string}>}
     */
    private function defaultDashboard(): array
    {
        return $this->normalizeDashboard([
            'id' => 'overview',
            'name' => __('ui.dashboard.default_name'),
        ], 0);
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{id: string, name: string, period: int, density: string, widgets: list<array{id: string, visible: bool, size: string, chartType: string}>}
     */
    private function normalizeDashboard(array $dashboard, int $index): array
    {
        $id = Str::of((string) Arr::get($dashboard, 'id', ''))
            ->trim()
            ->replaceMatches('/[^A-Za-z0-9_-]/', '')
            ->limit(48, '')
            ->toString();
        $name = Str::of((string) Arr::get($dashboard, 'name', ''))
            ->squish()
            ->limit(60)
            ->toString();
        $period = (int) Arr::get($dashboard, 'period', self::PERIODS[0]);
        $density = Arr::get($dashboard, 'density', self::DENSITIES[0]);

        return [
            'id' => $id !== '' ? $id : 'dashboard_'.($index + 1),
            'name' => $name !== '' ? $name : __('ui.dashboard.default_name'),
            'period' => in_array($period, self::PERIODS, true) ? $period : self::PERIODS[0],
            'density' => is_string($density) && in_array($density, self::DENSITIES, true)
                ? $density
                : self::DENSITIES[0],
            'widgets' => $this->normalizeWidgets(Arr::get($dashboard, 'widgets', [])),
        ];
    }

    /**
     * @return list<array{id: string, visible: bool, size: string, chartType: string}>
     */
    private function normalizeWidgets(mixed $widgets): array
    {
        $storedWidgets = is_array($widgets) ? array_values($widgets) : [];
        $widgetsById = [];
        $orderedIds = [];

        foreach ($storedWidgets as $storedWidget) {
            if (! is_array($storedWidget)) {
                continue;
            }

            $id = Arr::get($storedWidget, 'id');

            if (! is_string($id) || ! array_key_exists($id, self::WIDGETS)) {
                continue;
            }

            $widgetsById[$id] = $storedWidget;

            if (! in_array($id, $orderedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        foreach (self::widgetIds() as $widgetId) {
            if (! in_array($widgetId, $orderedIds, true)) {
                $orderedIds[] = $widgetId;
            }
        }

        $normalizedWidgets = [];

        foreach ($orderedIds as $id) {
            $defaults = self::WIDGETS[$id];
            $widget = $widgetsById[$id] ?? [];
            $size = Arr::get($widget, 'size', $defaults['size']);
            $chartType = Arr::get($widget, 'chartType', $defaults['chartType']);
            $visible = filter_var(
                Arr::get($widget, 'visible', true),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            );

            $normalizedWidgets[] = [
                'id' => $id,
                'visible' => $visible ?? true,
                'size' => is_string($size) && in_array($size, self::SIZES, true)
                    ? $size
                    : $defaults['size'],
                'chartType' => is_string($chartType) && in_array($chartType, $defaults['chartTypes'], true)
                    ? $chartType
                    : $defaults['chartType'],
            ];
        }

        if (! array_filter($normalizedWidgets, fn (array $widget): bool => $widget['visible'])) {
            $normalizedWidgets[0] = [...$normalizedWidgets[0], 'visible' => true];
        }

        return $normalizedWidgets;
    }
}
