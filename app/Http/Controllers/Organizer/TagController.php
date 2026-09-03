<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\CreateTag;
use App\Domain\Contact\Actions\DeleteTag;
use App\Domain\Contact\Actions\UpdateTag;
use App\Domain\Contact\Models\Tag;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Tag\SaveTagRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TagController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', [Tag::class, $this->currentOrganization()]);

        $tags = Tag::query()
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'contacts_count' => $tag->contacts_count,
            ]);

        return Inertia::render('Tags/Index', ['tags' => $tags]);
    }

    public function store(SaveTagRequest $request, CreateTag $action): RedirectResponse
    {
        $action->handle($this->currentOrganization(), $request->user(), $request->validated('name'), $request->validated('color'));

        return redirect()->route('tags.index');
    }

    public function update(SaveTagRequest $request, int $tag, UpdateTag $action): RedirectResponse
    {
        $action->handle($this->findTag($tag), $request->user(), $request->validated('name'), $request->validated('color'));

        return redirect()->route('tags.index');
    }

    public function destroy(Request $request, int $tag, DeleteTag $action): RedirectResponse
    {
        $action->handle($this->findTag($tag), $request->user());

        return redirect()->route('tags.index');
    }

    private function findTag(int $id): Tag
    {
        return Tag::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
