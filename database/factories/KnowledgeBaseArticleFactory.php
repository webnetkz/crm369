<?php

namespace Database\Factories;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeBaseArticle>
 */
class KnowledgeBaseArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'knowledge_base_id' => KnowledgeBase::factory(),
            'parent_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(),
            'blocks' => [
                [
                    'type' => KnowledgeBaseArticle::BLOCK_HEADING,
                    'heading_level' => 2,
                    'content' => $title,
                ],
                [
                    'type' => KnowledgeBaseArticle::BLOCK_PARAGRAPH,
                    'content' => fake()->paragraph(),
                ],
            ],
            'sort_order' => 0,
            'is_published' => true,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }
}
