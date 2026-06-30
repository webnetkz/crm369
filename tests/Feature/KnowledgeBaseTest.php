<?php

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open public knowledge bases and see articles', function () {
    $user = User::factory()->create();

    $base = KnowledgeBase::factory()->create([
        'title' => 'Portal Handbook',
        'description' => 'Public handbook',
    ]);

    $article = KnowledgeBaseArticle::factory()->create([
        'knowledge_base_id' => $base->id,
        'title' => 'Getting Started',
        'blocks' => [
            [
                'type' => KnowledgeBaseArticle::BLOCK_PARAGRAPH,
                'content' => 'Welcome to the handbook.',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('knowledge-bases.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledge/Index')
            ->where('can.manage', false)
            ->has('bases', 1)
            ->where('activeBase.id', $base->id)
            ->where('activeArticle.id', $article->id)
        );
});

test('knowledge base visibility is limited by assigned groups', function () {
    $managers = UserGroup::factory()->create(['name' => 'Managers']);
    $manager = User::factory()->create(['user_group_id' => $managers->id]);
    $regularUser = User::factory()->create(['user_group_id' => null]);

    $restrictedBase = KnowledgeBase::factory()->create([
        'title' => 'Managers Playbook',
    ]);
    $restrictedBase->groups()->sync([$managers->id]);

    KnowledgeBaseArticle::factory()->create([
        'knowledge_base_id' => $restrictedBase->id,
        'title' => 'Manager Rules',
    ]);

    $this->actingAs($manager)
        ->get(route('knowledge-bases.show', $restrictedBase))
        ->assertSuccessful();

    $this->actingAs($regularUser)
        ->get(route('knowledge-bases.show', $restrictedBase))
        ->assertNotFound();
});

test('visible knowledge bases are shared as submenu items for the current user', function () {
    $managers = UserGroup::factory()->create(['name' => 'Managers']);
    $manager = User::factory()->create(['user_group_id' => $managers->id]);
    $regularUser = User::factory()->create(['user_group_id' => null]);

    KnowledgeBase::factory()->create([
        'title' => 'Company Handbook',
        'is_published' => true,
    ]);

    $restrictedBase = KnowledgeBase::factory()->create([
        'title' => 'Managers Only',
        'is_published' => true,
    ]);
    $restrictedBase->groups()->sync([$managers->id]);

    KnowledgeBase::factory()->create([
        'title' => 'Draft Handbook',
        'is_published' => false,
    ]);

    $managerKnowledgeMenu = collect(
        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->inertiaProps('menu.knowledgeBases'),
    )->pluck('title');

    $regularKnowledgeMenu = collect(
        $this->actingAs($regularUser)
            ->get(route('dashboard'))
            ->inertiaProps('menu.knowledgeBases'),
    )->pluck('title');

    expect($managerKnowledgeMenu->all())
        ->toContain('Company Handbook')
        ->toContain('Managers Only')
        ->not->toContain('Draft Handbook')
        ->and($regularKnowledgeMenu->all())
        ->toContain('Company Handbook')
        ->not->toContain('Managers Only')
        ->not->toContain('Draft Handbook');
});

test('super admin can create knowledge base with group visibility and article image blocks', function () {
    Storage::fake('public');
    config(['admin.super_admin_email' => 'super@example.com']);

    $group = UserGroup::factory()->create(['name' => 'Support']);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('knowledge-bases.store'), [
            'title' => 'Support Base',
            'slug' => 'support-base',
            'description' => 'Restricted base',
            'is_published' => true,
            'user_group_ids' => [$group->id],
        ])
        ->assertRedirect();

    $base = KnowledgeBase::query()->where('slug', 'support-base')->firstOrFail();

    expect($base->groups()->pluck('user_groups.id')->all())->toBe([$group->id]);

    $this->actingAs($superAdmin)
        ->post(route('knowledge-bases.articles.store', $base), [
            'title' => 'Escalation Guide',
            'slug' => 'escalation-guide',
            'excerpt' => 'How to escalate cases',
            'sort_order' => 5,
            'is_published' => true,
            'blocks' => [
                [
                    'type' => KnowledgeBaseArticle::BLOCK_HEADING,
                    'content' => 'Escalation Guide',
                    'heading_level' => 2,
                ],
                [
                    'type' => KnowledgeBaseArticle::BLOCK_LIST,
                    'ordered' => true,
                    'items' => ['Check SLA', 'Contact lead'],
                ],
                [
                    'type' => KnowledgeBaseArticle::BLOCK_IMAGE,
                    'caption' => 'Escalation flow',
                    'image_file' => UploadedFile::fake()->image('flow.png'),
                ],
            ],
        ])
        ->assertRedirect();

    $article = KnowledgeBaseArticle::query()->where('knowledge_base_id', $base->id)->firstOrFail();
    $imagePath = data_get($article->blocks, '2.image_path');

    expect($article->title)->toBe('Escalation Guide')
        ->and($imagePath)->toBeString()
        ->and($imagePath)->not->toBe('');

    Storage::disk('public')->assertExists((string) $imagePath);
});

test('article rich text keeps allowed formatting and strips unsafe markup', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $base = KnowledgeBase::factory()->create([
        'title' => 'Formatted Base',
        'slug' => 'formatted-base',
    ]);

    $this->actingAs($superAdmin)
        ->post(route('knowledge-bases.articles.store', $base), [
            'title' => 'Formatted Article',
            'slug' => 'formatted-article',
            'excerpt' => 'Rich text article',
            'sort_order' => 1,
            'is_published' => true,
            'blocks' => [
                [
                    'type' => KnowledgeBaseArticle::BLOCK_PARAGRAPH,
                    'content' => '<b>Keep</b><script>alert(1)</script><a href="javascript:alert(1)">Bad</a>',
                ],
                [
                    'type' => KnowledgeBaseArticle::BLOCK_LIST,
                    'ordered' => false,
                    'items' => ['<i>First</i>', '<a href="https://example.com/docs">Docs</a>'],
                ],
            ],
        ])
        ->assertRedirect();

    $article = KnowledgeBaseArticle::query()
        ->where('knowledge_base_id', $base->id)
        ->where('slug', 'formatted-article')
        ->firstOrFail();

    expect(data_get($article->blocks, '0.content'))->toBe('<strong>Keep</strong>Bad')
        ->and(data_get($article->blocks, '1.items.0'))->toBe('<em>First</em>')
        ->and(data_get($article->blocks, '1.items.1'))->toBe('<a href="https://example.com/docs">Docs</a>');

    $this->actingAs(User::factory()->create())
        ->get(route('knowledge-bases.articles.show', [$base, $article]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeArticle.blocks.0.content', '<strong>Keep</strong>Bad')
            ->where('activeArticle.blocks.1.items.0', '<em>First</em>')
            ->where('activeArticle.blocks.1.items.1', '<a href="https://example.com/docs">Docs</a>')
        );
});

test('knowledge base section is integrated into the sidebar and page editor files', function () {
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $page = file_get_contents(resource_path('js/pages/knowledge/Index.vue'));
    $editor = file_get_contents(resource_path('js/components/knowledge/KnowledgeArticleBlocksEditor.vue'));
    $richText = file_get_contents(resource_path('js/components/knowledge/RichTextEditable.vue'));
    $renderer = file_get_contents(resource_path('js/components/knowledge/KnowledgeArticleBlocksRenderer.vue'));
    $treeItem = file_get_contents(resource_path('js/components/knowledge/KnowledgeTreeItem.vue'));

    expect($sidebar)->toContain('knowledge-bases')
        ->and($sidebar)->toContain('page.props.menu.knowledgeBases')
        ->and($sidebar)->toContain('showKnowledgeBase(')
        ->and($sidebar)->toContain('t.value.knowledge.title')
        ->and($page)->toContain('KnowledgeTreeItem')
        ->and($page)->toContain('isKnowledgeBaseIndexPage')
        ->and($page)->toContain('xl:grid-cols-[22rem_minmax(0,1fr)]')
        ->and($page)->toContain('KnowledgeArticleBlocksEditor')
        ->and($page)->toContain('sidebarCollapsed: isArticleEditorActive.value')
        ->and($editor)->toContain('format_bold')
        ->and($editor)->toContain('sticky bottom-4 z-20')
        ->and($editor)->toContain('RichTextEditable')
        ->and($richText)->toContain('contenteditable="true"')
        ->and($treeItem)->toContain('space-y-0.5')
        ->and($treeItem)->toContain('leading-5')
        ->and($editor)->toContain('LocalizedFilePicker')
        ->and($renderer)->toContain('v-html="block.content ?? \'\'"')
        ->and($renderer)->toContain("block.type === 'image'");
});
