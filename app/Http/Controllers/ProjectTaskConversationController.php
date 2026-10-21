<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Support\TaskConversationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskConversationController extends Controller
{
    public function show(
        Request $request,
        ProjectTask $projectTask,
        TaskConversationData $taskConversationData,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'conversation' => $taskConversationData->open($projectTask, $user),
        ]);
    }
}
