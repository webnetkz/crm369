<?php

namespace App\Models;

use Database\Factories\ProjectTaskStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $name
 * @property string $color
 * @property bool $is_completed
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'color', 'is_completed', 'sort_order'])]
class ProjectTaskStage extends Model
{
    public const string SLUG_TODO = 'todo';

    public const string SLUG_IN_PROGRESS = 'in_progress';

    public const string SLUG_REVIEW = 'review';

    public const string SLUG_DONE = 'done';

    /** @use HasFactory<ProjectTaskStageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<ProjectTaskStage>  $query
     * @return Builder<ProjectTaskStage>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public static function availableSlugs(): array
    {
        if (! Schema::hasTable('project_task_stages')) {
            return array_column(self::defaultStages(), 'slug');
        }

        $slugs = self::query()
            ->ordered()
            ->pluck('slug')
            ->all();

        return $slugs !== [] ? $slugs : array_column(self::defaultStages(), 'slug');
    }

    /**
     * @return array<int, string>
     */
    public static function completedSlugs(): array
    {
        if (! Schema::hasTable('project_task_stages')) {
            return array_values(array_map(
                fn (array $stage): string => $stage['slug'],
                array_filter(self::defaultStages(), fn (array $stage): bool => $stage['is_completed']),
            ));
        }

        $slugs = self::query()
            ->where('is_completed', true)
            ->ordered()
            ->pluck('slug')
            ->all();

        return $slugs !== [] ? $slugs : [self::SLUG_DONE];
    }

    public static function isCompletedSlug(string $slug): bool
    {
        return in_array($slug, self::completedSlugs(), true);
    }

    public function displayName(): string
    {
        if (is_string($this->name) && trim($this->name) !== '') {
            return trim($this->name);
        }

        $translationKey = 'ui.projects.status_'.$this->slug;

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return Str::headline(str_replace('_', ' ', $this->slug));
    }

    /**
     * @return array<int, array{slug: string, name: null, color: string, is_completed: bool, sort_order: int}>
     */
    public static function defaultStages(): array
    {
        return [
            [
                'slug' => self::SLUG_TODO,
                'name' => null,
                'color' => '#64748B',
                'is_completed' => false,
                'sort_order' => 0,
            ],
            [
                'slug' => self::SLUG_IN_PROGRESS,
                'name' => null,
                'color' => '#2563EB',
                'is_completed' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => self::SLUG_REVIEW,
                'name' => null,
                'color' => '#F59E0B',
                'is_completed' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => self::SLUG_DONE,
                'name' => null,
                'color' => '#16A34A',
                'is_completed' => true,
                'sort_order' => 3,
            ],
        ];
    }
}
