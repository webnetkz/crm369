<?php

namespace App\Http\Controllers;

use App\Support\DashboardPageData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardPageData $pageData): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Dashboard', $pageData->build($user));
    }
}
