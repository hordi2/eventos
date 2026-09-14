<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorEventPermission;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Les factories imbriquées créeraient chacune leur propre organisation,
 * rejetée par la RLS : fournir organization_id, collaborator_id et event_id
 * explicitement (voir makeEventCollaborator dans tests/Pest.php).
 *
 * @extends Factory<CollaboratorEventPermission>
 */
final class CollaboratorEventPermissionFactory extends Factory
{
    protected $model = CollaboratorEventPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'collaborator_id' => Collaborator::factory(),
            'event_id' => Event::factory(),
            'permission' => CollaboratorPermission::CheckIn,
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (): array => ['permission' => CollaboratorPermission::Administrator]);
    }
}
