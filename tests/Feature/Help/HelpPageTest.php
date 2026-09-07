<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;

it('affiche la page Aide à tout membre authentifié, quel que soit son rôle', function (): void {
    ['organization' => $organization, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $this->actingAs($viewer)->get('/help')->assertOk();
});
