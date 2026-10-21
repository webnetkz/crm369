<?php

namespace App\Support;

class ProductionPageData
{
    public const string DEFAULT_SECTION = 'overview';

    /**
     * @var list<string>
     */
    public const array SECTION_KEYS = [
        'overview',
        'workshops',
        'machines',
        'raw-materials',
        'finished-products',
        'production-orders',
        'quality-control',
    ];

    public function hasSection(string $section): bool
    {
        return in_array($section, self::SECTION_KEYS, true);
    }

    /**
     * @return array{activeSection: string, sections: list<string>}
     */
    public function build(string $activeSection): array
    {
        return [
            'activeSection' => $activeSection,
            'sections' => self::SECTION_KEYS,
        ];
    }
}
