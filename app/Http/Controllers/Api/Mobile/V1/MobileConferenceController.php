<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConferenceInvitationRequest;
use App\Http\Requests\StoreConferenceRequest;
use App\Models\Conference;
use App\Models\User;
use App\Support\ConferenceManager;
use App\Support\ConferencePageData;
use App\Support\ConferenceRoomManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileConferenceController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    /**
     * The conferences page props: the conferences the user created or was invited to, grouped by status.
     */
    public function index(Request $request, ConferencePageData $pageData): JsonResponse
    {
        $this->ensureConferencesTablesExist();

        return response()->json([
            'data' => $pageData->buildIndexForViewer($this->viewer($request)),
        ]);
    }

    public function store(
        StoreConferenceRequest $request,
        ConferenceManager $conferenceManager,
        ConferencePageData $pageData,
    ): JsonResponse {
        $this->ensureConferencesTablesExist();

        $user = $this->viewer($request);
        $conference = $conferenceManager->create($request, $user);

        return response()->json([
            'message' => __('ui.conferences.created_success'),
            'data' => $this->conference($conference, $user, $pageData),
        ], 201);
    }

    /**
     * The meeting room page props. The conference also carries the web path of the meeting room page
     * (to open it in an authenticated web view) and the guest link when external guests may join.
     */
    public function show(
        Request $request,
        string $conference,
        ConferenceManager $conferenceManager,
        ConferencePageData $pageData,
    ): JsonResponse {
        $this->ensureConferencesTablesExist();

        $user = $this->viewer($request);
        $visibleConference = $conferenceManager->findVisibleConference($conference, $user);
        $showData = $pageData->buildShowForViewer($visibleConference, $user);

        return response()->json([
            'data' => [
                ...$showData,
                'conference' => $this->withMobileLinks((array) $showData['conference'], $visibleConference),
            ],
        ]);
    }

    /**
     * Invite more users to a conference the user manages. Users that are already invited are skipped.
     */
    public function storeInvitation(
        StoreConferenceInvitationRequest $request,
        string $conference,
        ConferenceManager $conferenceManager,
        ConferencePageData $pageData,
    ): JsonResponse {
        $this->ensureConferencesTablesExist();

        $user = $this->viewer($request);
        $managedConference = $conferenceManager->findConference($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $invitedCount = $conferenceManager->invite($managedConference, $request->invitedUserIds(), $user);

        return response()->json([
            'message' => $invitedCount > 0
                ? __('ui.conferences.invited_success')
                : __('ui.conferences.invited_already'),
            'invited_count' => $invitedCount,
            'data' => $this->conference($managedConference, $user, $pageData),
        ], $invitedCount > 0 ? 201 : 200);
    }

    /**
     * End a conference the user manages and disconnect its participants.
     */
    public function end(
        Request $request,
        string $conference,
        ConferenceManager $conferenceManager,
        ConferenceRoomManager $roomManager,
        ConferencePageData $pageData,
    ): JsonResponse {
        $this->ensureConferencesTablesExist();

        $user = $this->viewer($request);
        $managedConference = $conferenceManager->findConference($conference);
        abort_unless($managedConference->canBeManagedBy($user), 403);

        $roomManager->end($managedConference);

        return response()->json([
            'message' => __('ui.conferences.ended_success'),
            'data' => $this->conference($managedConference->refresh(), $user, $pageData),
        ]);
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function conference(Conference $conference, User $user, ConferencePageData $pageData): array
    {
        return $this->withMobileLinks($pageData->detail($conference, $user), $conference);
    }

    /**
     * @param  array<mixed>  $serializedConference
     * @return array<mixed>
     */
    private function withMobileLinks(array $serializedConference, Conference $conference): array
    {
        return [
            ...$serializedConference,
            'web_path' => route('conferences.show', $conference, false),
            'public_url' => $conference->allowsPublicJoin()
                ? route('conferences.public.show', ['conference' => $conference->public_token])
                : null,
        ];
    }
}
