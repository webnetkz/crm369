<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventIndexRequest;
use App\Support\CalendarEventService;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(CalendarEventIndexRequest $request, CalendarEventService $calendarEvents): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('calendar/Index', [
            'events' => $calendarEvents->eventsFor(
                $user,
                $request->fromDate(),
                $request->toDate(),
                $request->eventTypes(),
            )->all(),
            'range' => [
                'from' => $request->fromDate()->toDateString(),
                'to' => $request->toDate()->toDateString(),
            ],
            'filters' => ['types' => $request->eventTypes()],
            'view' => $request->calendarView(),
            'referenceDate' => $request->referenceDate(),
        ]);
    }
}
