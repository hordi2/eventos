<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventTemplate;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Template\PublishEventTemplateRequest;
use App\Http\Requests\Organizer\Template\UseEventTemplateRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Templates\CreateEventFromTemplate;
use App\Support\Templates\PublishEventTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bibliothèque de modèles d'événement (D11), côté organisateur : ce que les
 * autres ont publié, et ce qu'on publie soi-même.
 */
final class EventTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->currentOrganization();
        Gate::authorize('createEvents', $organization);

        $category = $request->string('categorie')->toString();

        $templates = EventTemplate::query()
            ->published()
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->with(['organization'])
            ->orderByDesc('uses_count')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return Inertia::render('Templates/Index', [
            'templates' => $templates->map(fn (EventTemplate $template): array => $this->present($template, $organization))->values(),
            'categories' => EventType::options(),
            'category' => $category,
            // De quoi publier l'un de ses propres événements.
            'myEvents' => Event::query()
                ->orderByDesc('start_at')
                ->limit(50)
                ->get(['id', 'title'])
                ->map(fn (Event $event): array => ['id' => $event->id, 'title' => $event->title])
                ->values(),
        ]);
    }

    public function store(PublishEventTemplateRequest $request, int $event, PublishEventTemplate $publishEventTemplate): RedirectResponse
    {
        $eventModel = Event::query()->findOrFail($event);
        /** @var User $user */
        $user = $request->user();

        $template = $publishEventTemplate->handle(
            $eventModel,
            $user,
            $request->string('name')->toString(),
            $request->string('summary')->toString() ?: null,
        );

        return back()->with('success', "« {$template->name} » est publié dans la bibliothèque.");
    }

    public function update(Request $request, int $template): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $model = EventTemplate::query()->where('organization_id', $organization->id)->findOrFail($template);
        Gate::authorize('createEvents', $organization);

        $model->update(['is_published' => $request->boolean('is_published')]);

        return back()->with('success', $model->is_published ? 'Le modèle est de nouveau visible.' : 'Le modèle est retiré de la bibliothèque.');
    }

    public function use(UseEventTemplateRequest $request, int $template, CreateEventFromTemplate $createEventFromTemplate): RedirectResponse
    {
        $organization = $this->currentOrganization();
        Gate::authorize('createEvents', $organization);

        $model = EventTemplate::query()->published()->findOrFail($template);
        /** @var User $user */
        $user = $request->user();

        $event = $createEventFromTemplate->handle(
            $model,
            $organization,
            $user,
            $request->string('title')->toString(),
            CarbonImmutable::parse($request->string('start_at')->toString()),
        );

        return redirect()->route('events.show', $event->id)->with('success', 'Votre événement est prêt, en brouillon.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EventTemplate $template, Organization $organization): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'summary' => $template->summary,
            'category' => $template->category->label(),
            'publisher' => $template->organization?->name,
            'mine' => $template->organization_id === $organization->id,
            'usesCount' => $template->uses_count,
            'fieldCount' => count($template->payload['fields'] ?? []),
            'blockCount' => count($template->payload['blocks'] ?? []),
        ];
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
