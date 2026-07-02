<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const array SECTION_KEYS = [
        'overview',
        'warehouses',
        'workshops',
        'machines',
        'raw-materials',
        'finished-products',
        'production-orders',
        'quality-control',
    ];

    public function index(): Response
    {
        return $this->page('overview');
    }

    public function show(string $section): Response
    {
        abort_unless(in_array($section, self::SECTION_KEYS, true), 404);

        return $this->page($section);
    }

    private function page(string $activeSection): Response
    {
        return Inertia::render('production/Index', [
            'activeSection' => $activeSection,
            'sections' => self::SECTION_KEYS,
        ]);
    }
}
