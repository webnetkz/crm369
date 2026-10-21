<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Requests\StoreConferenceInvitationRequest;
use App\Support\ConferenceManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ConferenceInvitationController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    public function store(
        StoreConferenceInvitationRequest $request,
        string $conference,
        ConferenceManager $conferenceManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $managedConference = $conferenceManager->findConference($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $invitedCount = $conferenceManager->invite($managedConference, $request->invitedUserIds(), $user);

        Inertia::flash('toast', [
            'type' => $invitedCount > 0 ? 'success' : 'info',
            'message' => $invitedCount > 0
                ? __('ui.conferences.invited_success')
                : __('ui.conferences.invited_already'),
        ]);

        return back();
    }
}
