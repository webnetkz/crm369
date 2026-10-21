<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Requests\StoreConferenceRequest;
use App\Support\ConferenceManager;
use App\Support\ConferencePageData;
use App\Support\ConferenceRoomManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConferenceController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    public function index(Request $request, ConferencePageData $pageData): Response
    {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('conferences/Index', $pageData->buildIndexForViewer($user));
    }

    public function store(
        StoreConferenceRequest $request,
        ConferenceManager $conferenceManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $conference = $conferenceManager->create($request, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.conferences.created_success')]);

        return to_route('conferences.show', $conference);
    }

    public function show(
        Request $request,
        string $conference,
        ConferenceManager $conferenceManager,
        ConferencePageData $pageData,
    ): Response {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleConference = $conferenceManager->findVisibleConference($conference, $user);

        return Inertia::render('conferences/Show', $pageData->buildShowForViewer($visibleConference, $user));
    }

    public function end(
        Request $request,
        string $conference,
        ConferenceManager $conferenceManager,
        ConferenceRoomManager $roomManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $managedConference = $conferenceManager->findConference($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $roomManager->end($managedConference);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.conferences.ended_success')]);

        return back();
    }
}
