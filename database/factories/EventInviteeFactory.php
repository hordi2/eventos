<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contact\Models\EventInvitee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * organization_id, event_id et contact_id sont toujours fournis par le
 * test : chaque niveau doit partager la même organisation, sans quoi la RLS
 * rejetterait l'écriture.
 *
 * @extends Factory<EventInvitee>
 */
final class EventInviteeFactory extends Factory
{
    protected $model = EventInvitee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_key' => null,
            'companions_allowed' => 0,
            'cc_email' => null,
        ];
    }

    public function inGroup(string $groupKey): self
    {
        return $this->state(fn (): array => ['group_key' => $groupKey]);
    }

    public function withUnlimitedCompanions(): self
    {
        return $this->state(fn (): array => ['companions_allowed' => null]);
    }
}
