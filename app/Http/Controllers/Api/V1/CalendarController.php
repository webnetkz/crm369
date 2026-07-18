<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarEventIndexRequest;
use App\Support\ApiRequestContext;
use App\Support\CalendarEventService;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    public function index(
        CalendarEventIndexRequest $request,
        CalendarEventService $calendarEvents,
    ): JsonResponse {
        $events = $calendarEvents->eventsFor(
            ApiRequestContext::subject($request),
            $request->fromDate(),
            $request->toDate(),
            $request->eventTypes(),
        );

        return response()->json([
            'data' => $events->all(),
            'meta' => [
                'from' => $request->fromDate()->toDateString(),
                'to' => $request->toDate()->toDateString(),
                'types' => $request->eventTypes(),
                'count' => $events->count(),
            ],
        ]);
    }
}
