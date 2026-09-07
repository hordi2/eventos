<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Community\Actions\CreateCommunityPost;
use App\Domain\Community\Actions\CreateCommunityTopic;
use App\Domain\Community\Models\CommunityCategory;
use App\Domain\Community\Models\CommunityTopic;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Community\StoreCommunityPostRequest;
use App\Http\Requests\Organizer\Community\StoreCommunityTopicRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forum communautaire : un espace partagé entre toutes les organisations
 * utilisant Itaza (jamais filtré par organisation, voir la migration
 * community_topics) — accessible à tout compte organisateur authentifié,
 * quel que soit son rôle.
 */
final class CommunityController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString();
        $categoryFilter = CommunityCategory::tryFrom($category);

        $topics = CommunityTopic::query()
            ->when($categoryFilter, fn ($query) => $query->where('category', $categoryFilter))
            ->withCount('posts')
            ->with('user')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Community/Index', [
            'topics' => $topics->through(fn (CommunityTopic $topic): array => $this->serializeTopic($topic)),
            'categories' => $this->categoryOptions(),
            'activeCategory' => $categoryFilter?->value,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Community/Create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreCommunityTopicRequest $request, CreateCommunityTopic $createCommunityTopic): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $topic = $createCommunityTopic->handle(
            author: $user,
            category: CommunityCategory::from($request->string('category')->toString()),
            title: $request->string('title')->toString(),
            body: $request->string('body')->toString(),
        );

        return redirect()->route('community.show', $topic);
    }

    public function show(CommunityTopic $topic): Response
    {
        $topic->load(['user', 'posts.user']);

        return Inertia::render('Community/Show', [
            'topic' => $this->serializeTopic($topic, withBody: true),
            'posts' => $topic->posts->map(fn ($post): array => [
                'id' => $post->id,
                'body' => $post->body,
                'author' => $post->user->name,
                'created_at' => $post->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function storePost(StoreCommunityPostRequest $request, CommunityTopic $topic, CreateCommunityPost $createCommunityPost): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $createCommunityPost->handle($topic, $user, $request->string('body')->toString());

        return redirect()->route('community.show', $topic);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTopic(CommunityTopic $topic, bool $withBody = false): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'body' => $withBody ? $topic->body : null,
            'category' => $topic->category->value,
            'category_label' => $topic->category->label(),
            'author' => $topic->user->name,
            'posts_count' => $topic->posts_count ?? null,
            'created_at' => $topic->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function categoryOptions(): array
    {
        return array_map(
            fn (CommunityCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            CommunityCategory::cases(),
        );
    }
}
