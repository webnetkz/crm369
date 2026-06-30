<?php

namespace App\Http\Resources;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiChatConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChatConversation $conversation */
        $conversation = $this->resource;
        $viewer = $request->user();
        $otherParticipant = $viewer
            ? $conversation->participants
                ->first(fn (ChatConversationParticipant $participant): bool => $participant->user_id !== $viewer->id)
                ?->user
            : null;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $this->displayName($otherParticipant)
                : ($conversation->title ?? __('ui.chat.untitled_chat')),
            'subtitle' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $otherParticipant?->email
                : null,
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'participant' => $otherParticipant
                ? (new ApiUserResource($otherParticipant))->resolve()
                : null,
            'participants' => $conversation->relationLoaded('participants')
                ? $conversation->participants
                    ->map(fn (ChatConversationParticipant $participant): array => (new ApiUserResource($participant->user))->resolve())
                    ->values()
                    ->all()
                : [],
            'messages' => $conversation->relationLoaded('messages')
                ? ApiChatMessageResource::collection($conversation->messages)->resolve()
                : [],
            'created_at' => $conversation->created_at?->toISOString(),
            'updated_at' => $conversation->updated_at?->toISOString(),
        ];
    }

    private function displayName(?User $user): string
    {
        if (! $user) {
            return __('ui.chat.unknown_user');
        }

        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : $user->email;
    }
}
