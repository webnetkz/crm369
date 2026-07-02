<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\LogEntryReader;
use App\Support\PaginationData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    private const int PER_PAGE = 100;

    public function edit(Request $request, LogEntryReader $reader): Response
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $logData = $reader->paginate($currentPage, self::PER_PAGE);

        $paginator = new LengthAwarePaginator(
            $logData['entries'],
            $logData['total'],
            self::PER_PAGE,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('settings/Logs', [
            'files' => $logData['files'],
            'entries' => PaginationData::from($paginator),
            'filters' => [
                'per_page' => self::PER_PAGE,
            ],
            'perPageOptions' => [self::PER_PAGE],
        ]);
    }
}
