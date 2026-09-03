<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\CancelEmailAutomation;
use App\Domain\Messaging\Actions\CreateEmailAutomation;
use App\Domain\Messaging\Models\EmailAutomation;
use App\Domain\Messaging\Models\EmailAutomationType;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\EmailAutomation\SaveEmailAutomationRequest;
use App\Support\Segments\EventSegment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class EmailAutomationController extends Controller
{
    public function index(int $event): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewAny', [EmailAutomation::class, $event->organization]);

        $automations = EmailAutomation::query()
            ->where('event_id', $event->id)
            ->with('emailTemplate')
            ->latest('created_at')
            ->get()
            ->map(fn (EmailAutomation $automation): array => [
                'id' => $automation->id,
                'type' => $automation->type->value,
                'type_label' => $automation->type->label(),
                'template_name' => $automation->emailTemplate->name,
                'segment' => $automation->segment?->value,
                'status' => $automation->status->value,
                'scheduled_at' => $automation->scheduled_at?->toIso8601String(),
                'sent_at' => $automation->sent_at?->toIso8601String(),
            ]);

        return Inertia::render('EmailAutomations/Index', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'automations' => $automations,
            'types' => array_map(fn (EmailAutomationType $type): array => ['value' => $type->value, 'label' => $type->label()], EmailAutomationType::cases()),
            'templates' => EmailTemplate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveEmailAutomationRequest $request, int $event, CreateEmailAutomation $action): RedirectResponse
    {
        $event = $this->findEvent($event);
        $template = EmailTemplate::query()->findOrFail($request->validated('email_template_id'));
        $type = EmailAutomationType::from($request->validated('type'));
        $segment = $request->validated('segment') !== null ? EventSegment::from($request->validated('segment')) : null;

        try {
            $action->handle(
                $event->organization,
                $request->user(),
                $event->id,
                $template,
                $type,
                $this->scheduledAt($event, $type, $request->validated('scheduled_at')),
                $segment,
            );
        } catch (LogicException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        return redirect()->route('events.automations.index', $event);
    }

    public function cancel(Request $request, int $emailAutomation, CancelEmailAutomation $action): RedirectResponse
    {
        $automation = EmailAutomation::query()->findOrFail($emailAutomation);

        try {
            $action->handle($automation, $request->user());
        } catch (LogicException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return redirect()->route('events.automations.index', $automation->event_id);
    }

    /**
     * "Respecte le fuseau du destinataire" (critère du ticket) est
     * impossible à tenir à la lettre : Contact ne porte aucune donnée de
     * fuseau. Accord explicite pour utiliser celui de l'événement à la
     * place — déjà la convention de toute l'app (règle 4.3 du CLAUDE.md).
     */
    private function scheduledAt(Event $event, EmailAutomationType $type, ?string $organizerProvided): ?CarbonImmutable
    {
        $scheduledAt = match ($type) {
            EmailAutomationType::Confirmation => null,
            EmailAutomationType::ReminderJ7 => $event->start_at->subDays(7),
            EmailAutomationType::ReminderJ1 => $event->start_at->subDay(),
            EmailAutomationType::ThankYou => $event->end_at->addDay(),
            // Le <input type="datetime-local"> du formulaire renvoie une
            // heure murale sans fuseau ("2026-12-01T14:00") : l'interpréter
            // sans préciser de fuseau la ferait tomber dans celui du serveur
            // (jamais admis, règle 4.3 du CLAUDE.md) — celui de l'événement
            // s'impose ici pour la même raison que les rappels J-7/J-1. Le
            // ->utc() est indispensable : sans lui, Eloquent formate l'heure
            // locale telle quelle pour la colonne (même piège documenté dans
            // CreateEvent::resolveDateTime()).
            EmailAutomationType::Invitation, EmailAutomationType::ReminderUnanswered => CarbonImmutable::parse($organizerProvided, $event->timezone)->utc(),
        };

        if ($scheduledAt !== null && $scheduledAt->isPast()) {
            throw ValidationException::withMessages([
                'type' => "L'échéance calculée ({$scheduledAt->format('d/m/Y à H:i')}) est déjà passée pour cet événement.",
            ]);
        }

        return $scheduledAt;
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
