<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\CreateWhatsappTemplate;
use App\Domain\Messaging\Actions\DeleteWhatsappTemplate;
use App\Domain\Messaging\Actions\UpdateWhatsappTemplate;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\WhatsappTemplate\PreviewWhatsappTemplateRequest;
use App\Http\Requests\Organizer\WhatsappTemplate\SaveWhatsappTemplateRequest;
use App\Http\Requests\Organizer\WhatsappTemplate\SendTestWhatsappRequest;
use App\Support\Messaging\ResolveWhatsappTemplateVariables;
use App\Support\Messaging\SendTestWhatsapp;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class WhatsappTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', [WhatsappTemplate::class, $this->currentOrganization()]);

        $templates = WhatsappTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsappTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'provider_template_sid' => $template->provider_template_sid,
                'language' => $template->language,
                'category' => $template->category,
                'variable_mapping' => $template->variable_mapping,
                'updated_at' => $template->updated_at->toIso8601String(),
            ]);

        return Inertia::render('WhatsappTemplates/Index', [
            'templates' => $templates,
            'contacts' => $this->contactOptions(),
            'events' => $this->eventOptions(),
        ]);
    }

    public function store(SaveWhatsappTemplateRequest $request, CreateWhatsappTemplate $action): RedirectResponse
    {
        $action->handle(
            $this->currentOrganization(),
            $request->user(),
            $request->validated('name'),
            $request->validated('provider_template_sid'),
            $request->validated('language'),
            $request->validated('category'),
            $request->validated('variable_mapping'),
        );

        return redirect()->route('whatsapp-templates.index');
    }

    public function update(SaveWhatsappTemplateRequest $request, int $whatsappTemplate, UpdateWhatsappTemplate $action): RedirectResponse
    {
        $action->handle(
            $this->findTemplate($whatsappTemplate),
            $request->user(),
            $request->validated('name'),
            $request->validated('provider_template_sid'),
            $request->validated('language'),
            $request->validated('category'),
            $request->validated('variable_mapping'),
        );

        return redirect()->route('whatsapp-templates.index');
    }

    public function destroy(Request $request, int $whatsappTemplate, DeleteWhatsappTemplate $action): RedirectResponse
    {
        $action->handle($this->findTemplate($whatsappTemplate), $request->user());

        return redirect()->route('whatsapp-templates.index');
    }

    /**
     * Pas de rendu HTML comme l'e-mail (T-044) : un modèle WhatsApp est un
     * texte figé chez le prestataire, hors de portée de l'app (accord
     * explicite) — l'« aperçu » se limite donc aux variables numérotées
     * réellement envoyées, pour vérifier la correspondance avant un test.
     */
    public function preview(PreviewWhatsappTemplateRequest $request, int $whatsappTemplate, ResolveWhatsappTemplateVariables $action): JsonResponse
    {
        $template = $this->findTemplate($whatsappTemplate);
        Gate::authorize('update', $template);

        $contact = Contact::query()->findOrFail($request->validated('contact_id'));
        $event = $request->validated('event_id') !== null ? Event::query()->find($request->validated('event_id')) : null;

        return response()->json(['content_variables' => $action->handle($template, $contact, $event)]);
    }

    public function sendTest(SendTestWhatsappRequest $request, int $whatsappTemplate, SendTestWhatsapp $action): JsonResponse
    {
        $template = $this->findTemplate($whatsappTemplate);
        Gate::authorize('update', $template);

        $contact = Contact::query()->findOrFail($request->validated('contact_id'));
        $event = $request->validated('event_id') !== null ? Event::query()->find($request->validated('event_id')) : null;

        $action->handle($this->currentOrganization(), $template, $contact, $event, $request->validated('to_phone_e164'));

        return response()->json(['sent' => true]);
    }

    private function findTemplate(int $id): WhatsappTemplate
    {
        return WhatsappTemplate::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function contactOptions(): array
    {
        return Contact::query()
            ->orderBy('last_name')
            ->limit(200)
            ->get()
            ->map(fn (Contact $contact): array => ['id' => $contact->id, 'label' => $contact->fullName()])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function eventOptions(): array
    {
        return Event::query()
            ->orderByDesc('start_at')
            ->limit(200)
            ->get(['id', 'title'])
            ->map(fn (Event $event): array => ['id' => $event->id, 'label' => $event->title])
            ->all();
    }
}
