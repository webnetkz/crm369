<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Support\ProductionPageData;
use Illuminate\Http\JsonResponse;

class MobileProductionController extends Controller
{
    public function index(ProductionPageData $pageData): JsonResponse
    {
        return $this->page($pageData, ProductionPageData::DEFAULT_SECTION);
    }

    public function show(string $section, ProductionPageData $pageData): JsonResponse
    {
        abort_unless($pageData->hasSection($section), 404);

        return $this->page($pageData, $section);
    }

    /**
     * The web page props plus the localized texts the web client renders for them.
     */
    private function page(ProductionPageData $pageData, string $activeSection): JsonResponse
    {
        return response()->json([
            'data' => [
                ...$pageData->build($activeSection),
                'labels' => $this->labels(),
                'sectionItems' => collect(ProductionPageData::SECTION_KEYS)
                    ->map(fn (string $section): array => [
                        'key' => $section,
                        'title' => $this->sectionText($section, 'title', $section),
                        'summary' => $this->sectionText($section, 'summary'),
                    ])
                    ->values()
                    ->all(),
                'activeSectionContent' => $this->sectionContent($activeSection),
            ],
        ]);
    }

    /**
     * @return array{title: string, description: string, eyebrow: string, navigation_title: string, navigation_description: string, cards_title: string, records_label: string, focus_label: string, next_steps_label: string}
     */
    private function labels(): array
    {
        return [
            'title' => $this->text('ui.production.title'),
            'description' => $this->text('ui.production.description'),
            'eyebrow' => $this->text('ui.production.eyebrow'),
            'navigation_title' => $this->text('ui.production.navigation_title'),
            'navigation_description' => $this->text('ui.production.navigation_description'),
            'cards_title' => $this->text('ui.production.cards_title'),
            'records_label' => $this->text('ui.production.records_label'),
            'focus_label' => $this->text('ui.production.focus_label'),
            'next_steps_label' => $this->text('ui.production.next_steps_label'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionContent(string $section): array
    {
        return [
            'key' => $section,
            'title' => $this->sectionText($section, 'title', $section),
            'summary' => $this->sectionText($section, 'summary'),
            'hero_title' => $this->sectionText($section, 'hero_title'),
            'hero_description' => $this->sectionText($section, 'hero_description'),
            'metrics' => $this->sectionRows($section, 'metrics', ['label', 'value', 'caption']),
            'cards' => $this->sectionRows($section, 'cards', ['title', 'description']),
            'records_title' => $this->sectionText($section, 'records_title'),
            'records_description' => $this->sectionText($section, 'records_description'),
            'records' => $this->sectionRows($section, 'records', ['title', 'badge', 'description']),
            'focus_title' => $this->sectionText($section, 'focus_title'),
            'focus_points' => $this->sectionStrings($section, 'focus_points'),
            'next_steps_title' => $this->sectionText($section, 'next_steps_title'),
            'next_steps' => $this->sectionStrings($section, 'next_steps'),
        ];
    }

    private function sectionText(string $section, string $key, string $fallback = ''): string
    {
        $value = $this->sectionValue($section, $key);

        return is_string($value) ? $value : $fallback;
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    private function sectionRows(string $section, string $key, array $columns): array
    {
        $rows = $this->sectionValue($section, $key);
        $sectionRows = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sectionRow = [];

            foreach ($columns as $column) {
                $value = $row[$column] ?? null;
                $sectionRow[$column] = is_string($value) ? $value : '';
            }

            $sectionRows[] = $sectionRow;
        }

        return $sectionRows;
    }

    /**
     * @return list<string>
     */
    private function sectionStrings(string $section, string $key): array
    {
        $values = $this->sectionValue($section, $key);

        return array_values(array_filter(
            is_array($values) ? $values : [],
            fn (mixed $value): bool => is_string($value),
        ));
    }

    private function sectionValue(string $section, string $key): mixed
    {
        $content = __('ui.production.sections.'.$section);

        return is_array($content) ? ($content[$key] ?? null) : null;
    }

    private function text(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : '';
    }
}
