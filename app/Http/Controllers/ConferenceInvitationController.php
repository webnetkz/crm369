<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Requests\StoreConferenceInvitationRequest;
use App\Models\Conference;
use App\Support\ConferenceInvitationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ConferenceInvitationController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    public function store(
        StoreConferenceInvitationRequest $request,
        string $conference,
        ConferenceInvitationManager $invitationManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $managedConference = Conference::query()->findOrFail($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $invitedCount = DB::transaction(fn (): int => $invitationManager->inviteUsers(
            $managedConference,
            $request->invitedUserIds(),
            $user,
        ));

        Inertia::flash('toast', [
            'type' => $invitedCount > 0 ? 'success' : 'info',
            'message' => $invitedCount > 0
                ? __('ui.conferences.invited_success')
                : __('ui.conferences.invited_already'),
        ]);

        return back();
    }
}
