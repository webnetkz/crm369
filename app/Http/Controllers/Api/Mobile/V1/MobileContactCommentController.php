<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactCommentRequest;
use App\Models\Contact;
use App\Models\ContactComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileContactCommentController extends Controller
{
    use EnsuresContactsTableIsReady;

    /**
     * The history comments of a contact visible to the user, newest first.
     */
    public function index(Request $request, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        return response()->json([
            'data' => $visibleContact->comments()
                ->with('author:id,name,last_name,avatar_path')
                ->get()
                ->map(fn (ContactComment $comment): array => $this->comment($comment))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreContactCommentRequest $request, string $contact): JsonResponse
    {
        if (! $this->contactsTableExists()) {
            return $this->contactsUnavailableResponse();
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $visibleContact = Contact::query()
            ->visibleTo($user)
            ->findOrFail($contact);

        $comment = $visibleContact->comments()->create([
            ...$request->payload(),
            'created_by_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => __('ui.contacts.comment_created_success'),
            'data' => $this->comment($comment->load('author:id,name,last_name,avatar_path')),
        ], 201);
    }

    /**
     * A contact history comment as serialized on the web contacts page, with the author's avatar.
     *
     * @return array{id: int, content: string, created_at: string|null, created_by: array{id: int, name: string, last_name: string|null, avatar: string|null}|null}
     */
    private function comment(ContactComment $comment): array
    {
        $author = $comment->author;

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toISOString(),
            'created_by' => $author instanceof User
                ? [
                    'id' => $author->id,
                    'name' => $author->name,
                    'last_name' => $author->last_name,
                    'avatar' => $author->avatar,
                ]
                : null,
        ];
    }
}
