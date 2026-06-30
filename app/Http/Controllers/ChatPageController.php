<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterChatsIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

class ChatPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FilterChatsIndexRequest $request): Response
    {
        $filters = $request->filters();

        return Inertia::render('chats/Index', [
            'mode' => $filters['mode'],
            'initialConversationId' => $filters['conversation'],
            'initialContactId' => $filters['contact'],
        ]);
    }
}
