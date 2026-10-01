<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\GuestBookMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestBookMessage>
 */
final class GuestBookMessageFactory extends Factory
{
    protected $model = GuestBookMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'author_name' => fake()->name(),
            'message' => 'Tous nos vœux de bonheur, et longue vie à vous deux.',
            'is_published' => true,
            'author_ip' => null,
        ];
    }

    public function hidden(): self
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
