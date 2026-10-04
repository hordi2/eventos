<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\CreateEmailTemplate;
use App\Domain\Messaging\Actions\DeleteEmailTemplate;
use App\Domain\Messaging\Actions\UpdateEmailTemplate;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\EmailTemplate\PreviewEmailTemplateRequest;
use App\Http\Requests\Organizer\EmailTemplate\SaveEmailTemplateRequest;
use App\Http\Requests\Organizer\EmailTemplate\SendTestEmailRequest;
use App\Models\User;
use App\Support\Agency\AgencyEmailTemplates;
use App\Support\Messaging\RenderEmailTemplate;
use App\Support\Messaging\SendTestEmail;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', [EmailTemplate::class, $this->currentOrganization()]);

        $templates = EmailTemplate::query()
            ->orderBy('name')
            ->get(['id', 'name', 'subject', 'updated_at'])
            ->map(fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'updated_at' => $template->updated_at->toIso8601String(),
            ]);

        return Inertia::render('EmailTemplates/Index', [
            'templates' => $templates,
            // Modèles que l'agence partage avec ce compte (D10).
            'agencyTemplates' => app(AgencyEmailTemplates::class)->shared($this->currentOrganization()),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', [EmailTemplate::class, $this->currentOrganization()]);

        return Inertia::render('EmailTemplates/Editor', ['template' => null, 'contacts' => $this->contactOptions(), 'events' => $this->eventOptions()]);
    }

    public function store(SaveEmailTemplateRequest $request, CreateEmailTemplate $action): RedirectResponse
    {
        $template = $action->handle(
            $this->currentOrganization(),
            $request->user(),
            $request->validated('name'),
            $request->validated('subject'),
            $request->validated('blocks'),
        );

        return redirect()->route('email-templates.edit', $template);
    }

    public function edit(int $emailTemplate): Response
    {
        $template = $this->findTemplate($emailTemplate);
        Gate::authorize('update', $template);

        return Inertia::render('EmailTemplates/Editor', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'blocks' => $template->blocks,
                'is_shared_with_clients' => (bool) $template->is_shared_with_clients,
            ],
            'contacts' => $this->contactOptions(),
            'events' => $this->eventOptions(),
            // Le partage ne se propose qu'à une agence.
            'isAgency' => (bool) $this->currentOrganization()->is_agency,
        ]);
    }

    public function update(SaveEmailTemplateRequest $request, int $emailTemplate, UpdateEmailTemplate $action): RedirectResponse
    {
        $template = $action->handle(
            $this->findTemplate($emailTemplate),
            $request->user(),
            $request->validated('name'),
            $request->validated('subject'),
            $request->validated('blocks'),
            $request->has('is_shared_with_clients') ? $request->boolean('is_shared_with_clients') : null,
        );

        return redirect()->route('email-templates.edit', $template);
    }

    /**
     * Le client reprend chez lui un modèle partagé par son agence (D10) :
     * la copie lui appartient, il l'adapte sans toucher à l'original.
     */
    public function importFromAgency(Request $request, int $emailTemplate, AgencyEmailTemplates $agencyEmailTemplates): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $template = $agencyEmailTemplates->import($this->currentOrganization(), $user, $emailTemplate);

        return redirect()
            ->route('email-templates.edit', $template)
            ->with('success', "Le modèle « {$template->name} » est maintenant le vôtre.");
    }

    public function destroy(Request $request, int $emailTemplate, DeleteEmailTemplate $action): RedirectResponse
    {
        $action->handle($this->findTemplate($emailTemplate), $request->user());

        return redirect()->route('email-templates.index');
    }

    public function preview(PreviewEmailTemplateRequest $request, int $emailTemplate, RenderEmailTemplate $action): JsonResponse
    {
        $template = $this->findTemplate($emailTemplate);
        Gate::authorize('update', $template);

        $contact = Contact::query()->findOrFail($request->validated('contact_id'));
        $event = $request->validated('event_id') !== null ? Event::query()->find($request->validated('event_id')) : null;
        $organization = $this->currentOrganization();

        return response()->json([
            'subject' => $action->renderSubject($template, $contact, $event),
            // La même enveloppe que l'envoi réel (emails.generic), jamais le
            // seul fragment de blocs : celui-ci n'a ni fond ni marges — dans
            // une <iframe> sans habillage, le texte sombre sur fond
            // transparent devient quasi invisible sur cette interface en
            // thème sombre.
            'html' => view('emails.generic', [
                'bodyHtml' => $action->render($template, $contact, $event),
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => $organization->logo_path !== null ? Storage::disk('public')->url($organization->logo_path) : null,
                'organizationPrimaryColor' => $organization->primary_color,
            ])->render(),
        ]);
    }

    public function sendTest(SendTestEmailRequest $request, int $emailTemplate, SendTestEmail $action): JsonResponse
    {
        $template = $this->findTemplate($emailTemplate);
        Gate::authorize('update', $template);

        $contact = Contact::query()->findOrFail($request->validated('contact_id'));
        $event = $request->validated('event_id') !== null ? Event::query()->find($request->validated('event_id')) : null;

        $action->handle($this->currentOrganization(), $template, $contact, $event, $request->validated('to_email'));

        return response()->json(['sent' => true]);
    }

    private function findTemplate(int $id): EmailTemplate
    {
        return EmailTemplate::query()->findOrFail($id);
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
