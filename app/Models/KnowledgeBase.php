<?php

namespace App\Models;

use Database\Factories\KnowledgeBaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property bool $is_published
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'description', 'is_published', 'created_by_user_id', 'updated_by_user_id'])]
class KnowledgeBase extends Model
{
    /** @use HasFactory<KnowledgeBaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $knowledgeBase): void {
            if (! is_string($knowledgeBase->slug) || trim($knowledgeBase->slug) === '') {
                $knowledgeBase->slug = Str::slug($knowledgeBase->title);
            }
        });
    }

    /**
     * @return HasMany<KnowledgeBaseArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class);
    }

    /**
     * @return BelongsToMany<UserGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'knowledge_base_group');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $groupIds = $this->relationLoaded('groups')
            ? $this->groups->pluck('id')->all()
            : $this->groups()->pluck('user_groups.id')->all();

        if ($groupIds === []) {
            return true;
        }

        if (! is_numeric($user->user_group_id)) {
            return false;
        }

        return in_array((int) $user->user_group_id, array_map('intval', $groupIds), true);
    }
}
