<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\EventTemplate;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventTemplate>
 */
final class EventTemplateFactory extends Factory
{
    protected $model = EventTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Conférence annuelle';

        return [
            'organization_id' => Organization::factory(),
            'published_by' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'summary' => 'Inscription, badges et programme pour une conférence d’une journée.',
            'category' => EventType::Conference,
            'payload' => ['fields' => [], 'blocks' => [], 'settings' => []],
            'is_published' => true,
        ];
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
