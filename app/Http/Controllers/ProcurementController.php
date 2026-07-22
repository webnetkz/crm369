<?php

namespace App\Http\Controllers;

use App\Support\ProcurementPageData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function index(Request $request, ProcurementPageData $procurementPageData): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('procurement/Index', $procurementPageData->index($user));
    }
}
