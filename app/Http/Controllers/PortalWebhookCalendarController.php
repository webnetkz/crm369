<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarEventIndexRequest;
use App\Models\PortalWebhook;
use App\Models\User;
use App\Support\CalendarEventService;
use Illuminate\Http\JsonResponse;

class PortalWebhookCalendarController extends Controller
{
    public function index(
        CalendarEventIndexRequest $request,
        PortalWebhook $portalWebhook,
        CalendarEventService $calendarEvents,
    ): JsonResponse {
        $user = $portalWebhook->creator()->with('group')->first();
        abort_unless($user instanceof User && $user->is_active && $user->email_verified_at !== null, 422);

        $events = $calendarEvents->eventsFor(
            $user,
            $request->fromDate(),
            $request->toDate(),
            $request->eventTypes(),
        );

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => $events->all(),
            'meta' => [
                'user_id' => $user->id,
                'from' => $request->fromDate()->toDateString(),
                'to' => $request->toDate()->toDateString(),
                'types' => $request->eventTypes(),
                'count' => $events->count(),
            ],
        ]);
    }
}
