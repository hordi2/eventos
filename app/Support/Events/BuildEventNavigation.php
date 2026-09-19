<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormField;
use App\Domain\Organization\Services\CollaboratorAccess;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Auth\Access\Gate as AccessGate;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;

/**
 * Menu latéral et barre du haut communs à toutes les pages d'un événement
 * (EventLayout.tsx). Chaque lien vaut null quand l'utilisateur ne peut pas
 * ouvrir la page : le menu ne montre jamais un lien qui mènerait à un 403.
 *
 * Évalué à la construction de la réponse, quand CurrentEvent est déjà posé
 * pour un collaborateur : ses capacités sont celles de sa permission sur
 * l'événement.
 */
final class BuildEventNavigation
{
    public function __construct(
        private readonly ResolveRouteEventId $resolveRouteEventId,
        private readonly CollaboratorAccess $collaboratorAccess,
        private readonly EventPublicLinks $eventPublicLinks,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Request $request): ?array
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user instanceof User || ! $route instanceof Route || app(CurrentOrganization::class)->id() === null) {
            return null;
        }

        $eventId = $this->resolveRouteEventId->handle($route);
        $event = $eventId !== null ? Event::query()->with('organization:id,name,slug')->find($eventId) : null;

        if ($event === null) {
            return null;
        }

        $gate = Gate::forUser($user);
        $canUpdate = $gate->allows('update', $event);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'status' => $event->status->value,
            'organizationName' => $event->organization->name,
            'publicUrl' => $event->status === EventStatus::Published ? $this->eventPublicLinks->publicUrl($event) : null,
            'previewUrl' => $canUpdate && $event->status === EventStatus::Draft ? $this->eventPublicLinks->previewUrl($event) : null,
            'canChangeStatus' => $canUpdate && $event->status !== EventStatus::Archived,
            'links' => $this->links($gate, $user, $event, $canUpdate),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function links(AccessGate $gate, User $user, Event $event, bool $canUpdate): array
    {
        $organization = $event->organization;
        $isCollaborator = $this->collaboratorAccess->isCollaborator($user, $organization->id);
        $link = fn (bool $allowed, string $url): ?string => $allowed ? $url : null;

        return [
            'checklist' => $link($gate->allows('viewGuests', $organization), route('events.show', $event->id)),
            'responses' => $link($gate->allows('viewGuests', $organization), route('events.dashboard.index', $event->id)),
            // Un seul niveau de hiérarchie : une session n'a pas ses propres sessions.
            'subEvents' => $link($canUpdate && ! $event->isSubEvent(), route('events.sub-events.index', $event->id)),
            'answers' => $link($gate->allows('viewGuests', $organization), route('events.answers.index', $event->id)),
            'files' => $link($gate->allows('viewGuests', $organization) && $this->asksFor($event->id, FieldType::FileUpload), route('events.files.index', $event->id)),
            // Rapports : proposés seulement quand le formulaire pose la question.
            'meals' => $link($gate->allows('viewGuests', $organization) && $this->asksFor($event->id, FieldType::MealChoice), route('events.meals.index', $event->id)),
            'donations' => $link($gate->allows('viewGuests', $organization) && $this->asksFor($event->id, FieldType::Donation), route('events.donations.index', $event->id)),
            'guests' => $link($gate->allows('viewGuests', $organization), route('events.guest-list.index', $event->id)),
            // Les formulaires de l'événement : un par public, chacun son lien.
            'form' => $link($canUpdate, route('forms.index', $event->id)),
            'website' => $link($canUpdate, route('events.page.edit', $event->id)),
            'settings' => $link($canUpdate, route('events.edit', $event->id)),
            'import' => $link(! $isCollaborator && $gate->allows('create', [Contact::class, $organization]) && $gate->allows('updateGuests', $organization), route('events.guest-list.import', $event->id)),
            'communications' => $link($gate->allows('sendCommunications', $organization), route('events.automations.index', $event->id)),
            'collaborators' => $link(! $isCollaborator && $gate->allows('inviteMembers', $organization), route('settings.event-sharing.index')),
            'seating' => $link($gate->allows('viewGuests', $organization), route('events.seating.index', $event->id)),
            'checkIn' => $link($gate->allows('checkIn', $organization), route('events.check-in.index', $event->id)),
            'badges' => $link($gate->allows('checkIn', $organization), route('events.badges.index', $event->id)),
            'tickets' => $link($gate->allows('manageTicketing', $organization), route('events.ticket-types.index', $event->id)),
            'exports' => $link($gate->allows('exportData', $organization), route('events.exports.index', $event->id)),
            'referral' => $link(! $isCollaborator && $gate->allows('manageBilling', $organization), route('settings.referral.edit')),
        ];
    }

    /**
     * « Fichiers reçus », « Préférences alimentaires » et « Dons et cadeaux »
     * n'ont de sens que si l'un des formulaires pose, ou a posé dans l'une de
     * ses versions, la question correspondante.
     */
    private function asksFor(int $eventId, FieldType $type): bool
    {
        return FormField::query()
            ->where('type', $type)
            ->whereHas('formVersion', fn ($query) => $query->whereIn('form_id', Form::query()->where('event_id', $eventId)->select('id')))
            ->exists();
    }
}
