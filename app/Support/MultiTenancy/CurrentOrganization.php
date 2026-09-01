<?php

declare(strict_types=1);

namespace App\Support\MultiTenancy;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;

final class CurrentOrganization
{
    private ?int $organizationId = null;

    public function set(Organization|int $organization): void
    {
        $this->organizationId = $organization instanceof Organization ? $organization->id : $organization;

        DB::statement('select set_config(?, ?, false)', [
            'app.current_organization_id',
            (string) $this->organizationId,
        ]);
    }

    public function clear(): void
    {
        $this->organizationId = null;

        // set_config('', ...) écrirait une chaîne vide, que le cast ::bigint
        // de la policy RLS ferait échouer. reset() supprime la valeur de
        // session : current_setting(..., true) redevient NULL proprement.
        DB::statement('reset app.current_organization_id');
    }

    public function id(): ?int
    {
        return $this->organizationId;
    }

    public function requireId(): int
    {
        return $this->organizationId ?? throw new MissingOrganizationContextException;
    }

    public function has(): bool
    {
        return $this->organizationId !== null;
    }
}
