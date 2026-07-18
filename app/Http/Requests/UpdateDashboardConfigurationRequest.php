<?php

namespace App\Http\Requests;

use App\Support\DashboardConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDashboardConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'configuration' => ['required', 'array:version,activeDashboardId,dashboards'],
            'configuration.version' => ['required', 'integer', Rule::in([DashboardConfiguration::VERSION])],
            'configuration.activeDashboardId' => ['required', 'string', 'max:48', 'regex:/^[A-Za-z0-9_-]+$/'],
            'configuration.dashboards' => ['required', 'array', 'list', 'min:1', 'max:'.DashboardConfiguration::MAX_DASHBOARDS],
            'configuration.dashboards.*' => ['required', 'array:id,name,period,density,widgets'],
            'configuration.dashboards.*.id' => ['required', 'string', 'max:48', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct:strict'],
            'configuration.dashboards.*.name' => ['required', 'string', 'max:60'],
            'configuration.dashboards.*.period' => ['required', 'integer', Rule::in(DashboardConfiguration::PERIODS)],
            'configuration.dashboards.*.density' => ['required', 'string', Rule::in(DashboardConfiguration::DENSITIES)],
            'configuration.dashboards.*.widgets' => ['required', 'array', 'list', 'size:'.count(DashboardConfiguration::WIDGETS)],
            'configuration.dashboards.*.widgets.*' => ['required', 'array:id,visible,size,chartType'],
            'configuration.dashboards.*.widgets.*.id' => ['required', 'string', Rule::in(DashboardConfiguration::widgetIds())],
            'configuration.dashboards.*.widgets.*.visible' => ['required', 'boolean'],
            'configuration.dashboards.*.widgets.*.size' => ['required', 'string', Rule::in(DashboardConfiguration::SIZES)],
            'configuration.dashboards.*.widgets.*.chartType' => ['required', 'string', Rule::in(DashboardConfiguration::chartTypes())],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $configuration = $this->input('configuration');

            if (! is_array($configuration)) {
                return;
            }

            $storedDashboards = $configuration['dashboards'] ?? [];
            $dashboards = is_array($storedDashboards) ? array_values($storedDashboards) : [];
            $activeDashboardId = $configuration['activeDashboardId'] ?? null;

            if (
                ! is_string($activeDashboardId)
                || ! array_filter(
                    $dashboards,
                    fn (mixed $dashboard): bool => is_array($dashboard)
                        && ($dashboard['id'] ?? null) === $activeDashboardId,
                )
            ) {
                $validator->errors()->add('configuration.activeDashboardId', __('ui.dashboard.validation.active_dashboard'));
            }

            foreach ($dashboards as $dashboardIndex => $dashboard) {
                if (! is_array($dashboard)) {
                    continue;
                }

                $storedWidgets = $dashboard['widgets'] ?? [];
                $widgets = is_array($storedWidgets) ? array_values($storedWidgets) : [];
                $widgetIds = [];
                $hasVisibleWidget = false;
                $hasDuplicateWidget = false;

                foreach ($widgets as $widgetIndex => $widget) {
                    if (! is_array($widget)) {
                        continue;
                    }

                    $widgetId = $widget['id'] ?? null;
                    $chartType = $widget['chartType'] ?? null;

                    if (is_string($widgetId)) {
                        if (in_array($widgetId, $widgetIds, true)) {
                            $hasDuplicateWidget = true;
                        }

                        $widgetIds[] = $widgetId;
                    }

                    $hasVisibleWidget = $hasVisibleWidget || ($widget['visible'] ?? false) === true;

                    if (
                        is_string($widgetId)
                        && is_string($chartType)
                        && ! in_array($chartType, DashboardConfiguration::chartTypesFor($widgetId), true)
                    ) {
                        $validator->errors()->add(
                            "configuration.dashboards.{$dashboardIndex}.widgets.{$widgetIndex}.chartType",
                            __('ui.dashboard.validation.chart_type'),
                        );
                    }
                }

                if ($hasDuplicateWidget) {
                    $validator->errors()->add(
                        "configuration.dashboards.{$dashboardIndex}.widgets",
                        __('ui.dashboard.validation.duplicate_widgets'),
                    );
                }

                if (! $hasVisibleWidget) {
                    $validator->errors()->add(
                        "configuration.dashboards.{$dashboardIndex}.widgets",
                        __('ui.dashboard.validation.visible_widget'),
                    );
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function configuration(): array
    {
        $configuration = $this->validated('configuration');

        return is_array($configuration) ? $configuration : [];
    }
}
