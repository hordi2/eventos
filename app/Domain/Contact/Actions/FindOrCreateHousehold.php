<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Household;
use App\Domain\Contact\Models\HouseholdType;
use App\Domain\Organization\Models\Organization;

final class FindOrCreateHousehold
{
    public function handle(Organization $organization, string $name, HouseholdType $type = HouseholdType::Family): Household
    {
        return Household::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'name' => $name],
            ['type' => $type],
        );
    }
}
