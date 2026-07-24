<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Requests\StoreConferenceRequest;
use App\Models\Conference;
use App\Models\User;
use App\Support\ConferenceInvitationManager;
use App\Support\ConferencePageData;
use App\Support\ConferenceRoomManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('conferences/Index', $pageData->buildIndex(
            $this->visibleConferences($user),
            $this->availableUsers(),
        ));
    }

    public function store(
        StoreConferenceRequest $request,
        ConferenceInvitationManager $invitationManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $conference = DB::transaction(function () use ($request, $user, $invitationManager): Conference {
            $conference = Conference::query()->create([
                ...$request->payload(),
                'room_name' => Conference::generateRoomName(),
                'public_token' => Conference::generatePublicToken(),
                'created_by_user_id' => $user->id,
            ]);

            $invitationManager->inviteUsers($conference, $request->invitedUserIds(), $user);

            return $conference;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.conferences.created_success')]);

        return to_route('conferences.show', $conference);
    }

    public function show(Request $request, string $conference, ConferencePageData $pageData): Response
    {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $visibleConference = $this->findVisibleConference($conference, $user);

        $visibleConference->load([
            'creator:id,name,last_name,email',
            'invitations.user:id,name,last_name,email,avatar_path,avatar_scale,avatar_position_x,avatar_position_y',
        ]);

        return Inertia::render('conferences/Show', $pageData->buildShow(
            $visibleConference,
            $this->visibleConferences($user),
            $this->availableUsers(),
            $user,
        ));
    }

    public function end(
        Request $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): RedirectResponse {
        $this->ensureConferencesTablesExist();

        $user = $request->user();
        abort_unless($user !== null, 403);

        $managedConference = $this->findConference($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $roomManager->end($managedConference);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.conferences.ended_success')]);

        return back();
    }

    /**
     * @return Collection<int, Conference>
     */
    private function visibleConferences(User $user): Collection
    {
        return Conference::query()
            ->visibleTo($user)
            ->with([
                'creator:id,name,last_name,email',
                'invitations.user:id,name,last_name,email,avatar_path,avatar_scale,avatar_position_x,avatar_position_y',
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function availableUsers(): Collection
    {
        return User::query()
            ->select([
                'id',
                'name',
                'last_name',
                'email',
                'avatar_path',
                'avatar_scale',
                'avatar_position_x',
                'avatar_position_y',
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('last_name')
            ->get();
    }

    private function findConference(string $conference): Conference
    {
        return Conference::query()->findOrFail($conference);
    }

    private function findVisibleConference(string $conference, User $user): Conference
    {
        return Conference::query()
            ->visibleTo($user)
            ->findOrFail($conference);
    }
}
