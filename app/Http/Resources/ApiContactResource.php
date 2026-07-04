<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Models\ContactComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Contact */
class ApiContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'is_blacklisted' => $this->is_blacklisted,
            'avatar' => $this->avatarUrl(),
            'company_requisites' => $this->company_requisites,
            'comments' => $this->relationLoaded('comments')
                ? $this->comments
                    ->map(fn (ContactComment $comment): array => [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'created_at' => $comment->created_at?->toISOString(),
                        'created_by' => $comment->author
                            ? [
                                'id' => $comment->author->id,
                                'name' => $comment->author->name,
                                'last_name' => $comment->author->last_name,
                            ]
                            : null,
                    ])
                    ->values()
                    ->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'last_name' => $this->creator->last_name,
                ]
                : null,
            'updated_by' => $this->updater
                ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'last_name' => $this->updater->last_name,
                ]
                : null,
        ];
    }
}
