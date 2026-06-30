<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChatMessage $message */
        $message = $this->resource;
        $viewer = $request->user();

        return [
            'id' => $message->id,
            'chat_conversation_id' => $message->chat_conversation_id,
            'body' => $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
            'is_own' => $viewer ? $message->user_id === $viewer->id : false,
            'user' => $message->user
                ? (new ApiUserResource($message->user))->resolve()
                : null,
        ];
    }
}
