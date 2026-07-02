<?php

use App\Models\News;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open the news page', function () {
    $user = User::factory()->create();
    $news = News::factory()->create([
        'title' => 'Product release',
        'slug' => 'product-release',
        'excerpt' => 'Short release summary',
        'content' => "Paragraph one.\n\nParagraph two.",
    ]);

    $this->actingAs($user)
        ->get(route('news.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/Index')
            ->where('newsItems.0.slug', $news->slug)
            ->where('activeNews', null)
        );
});

test('news remains a top-level sidebar item without separate news submenu entries', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $menuResponse = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($menuResponse->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain('newsIndex()')
        ->and($sidebar)->toContain('t.value.news.title')
        ->and($sidebar)->not->toContain('page.props.menu.newsItems')
        ->and($builtInKeys->all())->toContain('news');
});

test('super admin can create news with an image and regular users cannot see drafts', function () {
    Storage::fake('public');
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $regularUser = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('news.store'), [
            'title' => 'July roadmap',
            'slug' => '',
            'excerpt' => 'Preview of the next release.',
            'content' => "Line one.\n\nLine two.",
            'is_published' => false,
            'remove_image' => false,
            'image_file' => UploadedFile::fake()->image('roadmap.png'),
        ])
        ->assertRedirect();

    $news = News::query()->where('title', 'July roadmap')->firstOrFail();

    expect($news->slug)->toBe('july-roadmap')
        ->and($news->image_path)->toBeString()
        ->and($news->is_published)->toBeFalse();

    Storage::disk('public')->assertExists((string) $news->image_path);

    $this->actingAs($superAdmin)
        ->get(route('news.show', $news))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeNews.slug', 'july-roadmap')
            ->where('can.manage', true)
        );

    $this->actingAs($regularUser)
        ->get(route('news.show', $news))
        ->assertNotFound();
});
