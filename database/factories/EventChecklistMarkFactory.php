<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventChecklistMark;
use App\Domain\Event\Models\EventChecklistStep;
use App\Domain\Organization\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fournir organization_id et event_id explicitement : des factories
 * imbriquées créeraient chacune leur organisation, rejetée par la RLS.
 *
 * @extends Factory<EventChecklistMark>
 */
final class EventChecklistMarkFactory extends Factory
{
    protected $model = EventChecklistMark::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'step' => EventChecklistStep::Preview,
            'marked_at' => CarbonImmutable::now(),
        ];
    }
}
