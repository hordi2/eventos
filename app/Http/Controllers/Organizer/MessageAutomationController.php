<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\CancelMessageAutomation;
use App\Domain\Messaging\Actions\CreateMessageAutomation;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\MessageAutomation\SaveMessageAutomationRequest;
use App\Support\Segments\EventSegment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class MessageAutomationController extends Controller
{
    public function index(int $event): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewAny', [MessageAutomation::class, $event->organization]);

        $automations = MessageAutomation::query()
            ->where('event_id', $event->id)
            ->with(['emailTemplate', 'whatsappTemplate'])
            ->latest('created_at')
            ->get()
            ->map(fn (MessageAutomation $automation): array => [
                'id' => $automation->id,
                'type' => $automation->type->value,
                'type_label' => $automation->type->label(),
                'channel' => $automation->channel->value,
                'channel_label' => $automation->channel->label(),
                'template_name' => ($automation->emailTemplate ?? $automation->whatsappTemplate)->name,
                'segment' => $automation->segment?->value,
                'status' => $automation->status->value,
                'scheduled_at' => $automation->scheduled_at?->toIso8601String(),
                'sent_at' => $automation->sent_at?->toIso8601String(),
            ]);

        return Inertia::render('MessageAutomations/Index', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'automations' => $automations,
            'types' => array_map(fn (MessageAutomationType $type): array => ['value' => $type->value, 'label' => $type->label()], MessageAutomationType::cases()),
            'channels' => array_map(fn (MessageChannel $channel): array => ['value' => $channel->value, 'label' => $channel->label()], MessageChannel::cases()),
            'emailTemplates' => EmailTemplate::query()->orderBy('name')->get(['id', 'name']),
            'whatsappTemplates' => WhatsappTemplate::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveMessageAutomationRequest $request, int $event, CreateMessageAutomation $action): RedirectResponse
    {
        $event = $this->findEvent($event);
        $channel = MessageChannel::from($request->validated('channel'));
        $type = MessageAutomationType::from($request->validated('type'));
        $segment = $request->validated('segment') !== null ? EventSegment::from($request->validated('segment')) : null;
        $templateId = (int) ($channel === MessageChannel::Email
            ? $request->validated('email_template_id')
            : $request->validated('whatsapp_template_id'));

        try {
            $action->handle(
                $event->organization,
                $request->user(),
                $event->id,
                $channel,
                $templateId,
                $type,
                $this->scheduledAt($event, $type, $request->validated('scheduled_at')),
                $segment,
            );
        } catch (LogicException $e) {
            throw ValidationException::withMessages(['type' => $e->getMessage()]);
        }

        return redirect()->route('events.automations.index', $event);
    }

    public function cancel(Request $request, int $messageAutomation, CancelMessageAutomation $action): RedirectResponse
    {
        $automation = MessageAutomation::query()->findOrFail($messageAutomation);

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
    private function scheduledAt(Event $event, MessageAutomationType $type, ?string $organizerProvided): ?CarbonImmutable
    {
        $scheduledAt = match ($type) {
            MessageAutomationType::Confirmation => null,
            MessageAutomationType::ReminderJ7 => $event->start_at->subDays(7),
            MessageAutomationType::ReminderJ1 => $event->start_at->subDay(),
            MessageAutomationType::ThankYou => $event->end_at->addDay(),
            // Le <input type="datetime-local"> du formulaire renvoie une
            // heure murale sans fuseau ("2026-12-01T14:00") : l'interpréter
            // sans préciser de fuseau la ferait tomber dans celui du serveur
            // (jamais admis, règle 4.3 du CLAUDE.md) — celui de l'événement
            // s'impose ici pour la même raison que les rappels J-7/J-1. Le
            // ->utc() est indispensable : sans lui, Eloquent formate l'heure
            // locale telle quelle pour la colonne (même piège documenté dans
            // CreateEvent::resolveDateTime()).
            MessageAutomationType::Invitation, MessageAutomationType::ReminderUnanswered => CarbonImmutable::parse($organizerProvided, $event->timezone)->utc(),
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
