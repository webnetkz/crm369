<?php

namespace App\Http\Controllers;

use App\Support\ProductionPageData;
use Inertia\Inertia;
use Inertia\Response;

class ProductionController extends Controller
{
    public function index(ProductionPageData $pageData): Response
    {
        return $this->page($pageData, ProductionPageData::DEFAULT_SECTION);
    }

    public function show(string $section, ProductionPageData $pageData): Response
    {
        abort_unless($pageData->hasSection($section), 404);

        return $this->page($pageData, $section);
    }

    private function page(ProductionPageData $pageData, string $activeSection): Response
    {
        return Inertia::render('production/Index', $pageData->build($activeSection));
    }
}
