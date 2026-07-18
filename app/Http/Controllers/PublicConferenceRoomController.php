<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresConferenceTablesAreReady;
use App\Http\Requests\JoinConferenceRoomRequest;
use App\Http\Requests\LeaveConferenceRoomRequest;
use App\Http\Requests\StoreConferenceMessageRequest;
use App\Http\Requests\StoreConferenceSignalRequest;
use App\Http\Requests\SyncConferenceRoomRequest;
use App\Models\Conference;
use App\Support\ConferenceRoomManager;
use Illuminate\Http\JsonResponse;

class PublicConferenceRoomController extends Controller
{
    use EnsuresConferenceTablesAreReady;

    public function join(
        JoinConferenceRoomRequest $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): JsonResponse {
        $room = $this->findConference($conference);
        $user = $request->user();

        abort_unless(
            ($user !== null && $room->isAccessibleBy($user)) || $room->allowsPublicJoin(),
            404,
        );

        return response()->json($roomManager->join($room, $user, $request->displayName()), 201);
    }

    public function sync(
        SyncConferenceRoomRequest $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): JsonResponse {
        return response()->json($roomManager->sync(
            $this->findConference($conference),
            $request->participantId(),
            $request->participantToken(),
            $request->signalCursor(),
            $request->messageCursor(),
        ));
    }

    public function signal(
        StoreConferenceSignalRequest $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): JsonResponse {
        $signal = $roomManager->signal(
            $this->findConference($conference),
            $request->participantId(),
            $request->participantToken(),
            $request->targetParticipantId(),
            $request->signalType(),
            $request->signalPayload(),
        );

        return response()->json(['id' => $signal->id], 201);
    }

    public function message(
        StoreConferenceMessageRequest $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): JsonResponse {
        $message = $roomManager->message(
            $this->findConference($conference),
            $request->participantId(),
            $request->participantToken(),
            $request->messageBody(),
        );

        return response()->json(['id' => $message->id], 201);
    }

    public function leave(
        LeaveConferenceRoomRequest $request,
        string $conference,
        ConferenceRoomManager $roomManager,
    ): JsonResponse {
        $roomManager->leave(
            $this->findConference($conference),
            $request->participantId(),
            $request->participantToken(),
        );

        return response()->json(['left' => true]);
    }

    private function findConference(string $publicToken): Conference
    {
        $this->ensureConferencesTablesExist();

        return Conference::query()
            ->where('public_token', $publicToken)
            ->firstOrFail();
    }
}
