<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Écran « Inscriptions à valider » (M1.2) : ce qu'il faut pour décider —
 * qui répond, avec combien de personnes, quand, ce qu'il a répondu et les
 * sessions qu'il a choisies. Traverse Form et Event, d'où Support.
 */
final class PresentPendingRegistrations
{
    public function __construct(
        private readonly CollectRegistrationAnswers $collectRegistrationAnswers,
    ) {}

    /**
     * @return list<array{id: int, name: string, email: string, phone: ?string, people: int, companions: list<string>, registeredAt: string, answers: array<string, string>, sessions: list<string>}>
     */
    public function handle(Event $event): array
    {
        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->whereNull('parent_registration_id')
            ->where('status', RegistrationStatus::Pending)
            ->with(['attendees', 'allAnswers.formField.options'])
            ->orderBy('registered_at')
            ->get();

        $sessions = $this->sessionsByRegistration($registrations);

        return $registrations->map(fn (Registration $registration): array => [
            'id' => $registration->id,
            'name' => $this->name($registration),
            'email' => $registration->email,
            'phone' => $registration->phone_e164,
            'people' => $registration->attendees->count(),
            'companions' => $registration->attendees
                ->where('is_primary', false)
                ->map(fn ($companion): string => trim("{$companion->first_name} {$companion->last_name}"))
                ->values()
                ->all(),
            'registeredAt' => CarbonImmutable::parse($registration->registered_at ?? $registration->created_at)
                ->setTimezone($event->timezone)
                ->translatedFormat('j M Y \à H\hi'),
            'answers' => $this->collectRegistrationAnswers->handle($registration->allAnswers, ' · '),
            'sessions' => $sessions[$registration->id] ?? [],
        ])->values()->all();
    }

    /**
     * Sessions retenues par chaque inscription : une inscription rattachée
     * par sous-événement (T-013).
     *
     * @param  Collection<int, Registration>  $registrations
     * @return array<int, list<string>>
     */
    private function sessionsByRegistration(Collection $registrations): array
    {
        $children = Registration::query()
            ->whereIn('parent_registration_id', $registrations->pluck('id'))
            ->whereIn('status', [RegistrationStatus::Pending->value, RegistrationStatus::Confirmed->value, RegistrationStatus::Waitlisted->value])
            ->get(['parent_registration_id', 'event_id', 'status']);

        $titles = Event::query()->whereIn('id', $children->pluck('event_id'))->pluck('title', 'id');
        $sessions = [];

        foreach ($children as $child) {
            $title = (string) $titles->get($child->event_id, 'Session');
            $sessions[(int) $child->parent_registration_id][] = $child->status === RegistrationStatus::Waitlisted
                ? "{$title} (liste d'attente)"
                : $title;
        }

        return $sessions;
    }

    private function name(Registration $registration): string
    {
        $name = trim("{$registration->first_name} {$registration->last_name}");

        return $name !== '' ? $name : ($registration->email !== '' ? $registration->email : (string) $registration->phone_e164);
    }
}
