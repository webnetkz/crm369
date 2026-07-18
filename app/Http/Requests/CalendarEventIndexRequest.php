<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalendarEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in(['task', 'conference'])],
            'view' => ['nullable', Rule::in(['month', 'week', 'day', 'agenda'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $types = $this->input('types');

        if (is_string($types)) {
            $types = explode(',', $types);
        }

        if (is_array($types)) {
            $this->merge([
                'types' => collect($types)
                    ->filter(fn (mixed $type): bool => is_string($type))
                    ->map(fn (string $type): string => mb_strtolower(trim($type)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                    return;
                }

                $from = $this->input('from');
                $to = $this->input('to');

                if (! is_string($from) || ! is_string($to)) {
                    return;
                }

                if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 370) {
                    $validator->errors()->add('to', __('ui.calendar.range_too_large'));
                }
            },
        ];
    }

    public function fromDate(): CarbonImmutable
    {
        $from = $this->validated('from');

        return is_string($from)
            ? CarbonImmutable::parse($from)->startOfDay()
            : CarbonImmutable::now()->startOfMonth()->startOfWeek();
    }

    public function toDate(): CarbonImmutable
    {
        $to = $this->validated('to');

        return is_string($to)
            ? CarbonImmutable::parse($to)->endOfDay()
            : CarbonImmutable::now()->endOfMonth()->endOfWeek();
    }

    /**
     * @return array<int, 'task'|'conference'>
     */
    public function eventTypes(): array
    {
        $types = $this->validated('types');

        return is_array($types) && $types !== []
            ? array_values($types)
            : ['task', 'conference'];
    }

    public function calendarView(): string
    {
        $view = $this->validated('view');

        return is_string($view) ? $view : 'month';
    }

    public function referenceDate(): string
    {
        $date = $this->validated('date');

        return is_string($date) ? $date : CarbonImmutable::now()->toDateString();
    }
}
