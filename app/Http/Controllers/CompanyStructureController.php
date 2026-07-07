<?php

namespace App\Http\Controllers;

use App\Support\CompanyStructureData;
use Inertia\Inertia;
use Inertia\Response;

class CompanyStructureController extends Controller
{
    public function __invoke(CompanyStructureData $companyStructureData): Response
    {
        return Inertia::render('company-structure/Index', $companyStructureData->pageData());
    }
}
