<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

/**
 * Couvre intégralement la matrice M0.3 du cahier des charges : chaque
 * capacité, pour chacun des 5 rôles. viewFinancials et exportData sont
 * testées ici avec le réglage par défaut (accès éditeur désactivé) ; le
 * comportement "⚙️ paramétrable par le propriétaire" est couvert par un
 * test dédié plus bas.
 */
dataset('matrice_m03', [
    'gérer la facturation' => ['manageBilling', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => false,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'inviter des membres' => ['inviteMembers', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'créer un événement' => ['createEvents', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'supprimer un événement' => ['deleteEvents', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'modifier un événement assigné' => ['updateEvents', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => true,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'voir la liste des invités' => ['viewGuests', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => true,
        MembershipRole::DoorStaff->value => true,
        MembershipRole::Viewer->value => true,
    ]],
    'modifier la liste des invités' => ['updateGuests', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => true,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'envoyer des communications' => ['sendCommunications', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => true,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'effectuer un check-in' => ['checkIn', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => true,
        MembershipRole::DoorStaff->value => true,
        MembershipRole::Viewer->value => false,
    ]],
    'voir les données financières (réglage par défaut)' => ['viewFinancials', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'exporter les données (réglage par défaut)' => ['exportData', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'rembourser un billet' => ['refundTickets', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
    'voir le journal d\'audit' => ['viewAuditLog', [
        MembershipRole::Owner->value => true,
        MembershipRole::Admin->value => true,
        MembershipRole::Editor->value => false,
        MembershipRole::DoorStaff->value => false,
        MembershipRole::Viewer->value => false,
    ]],
]);

it('respecte la matrice de rôles M0.3', function (string $ability, array $expectedByRole): void {
    $organization = Organization::factory()->create();

    foreach ($expectedByRole as $roleValue => $expected) {
        $role = MembershipRole::from($roleValue);
        $user = User::factory()->create();
        $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

        expect($user->can($ability, $organization))
            ->toBe($expected, "Rôle {$role->value} : {$ability} devrait être ".($expected ? 'autorisé' : 'refusé'));
    }
})->with('matrice_m03');

it('un utilisateur sans adhésion à l\'organisation n\'a aucune capacité', function (): void {
    $organization = Organization::factory()->create();
    $stranger = User::factory()->create();

    expect($stranger->can('viewGuests', $organization))->toBeFalse();
    expect($stranger->can('checkIn', $organization))->toBeFalse();
});

it('door_staff ne peut ni modifier la liste des invités ni voir les montants', function (): void {
    $organization = Organization::factory()->create();
    $doorStaff = User::factory()->create();
    $doorStaff->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::DoorStaff]);

    expect($doorStaff->can('updateGuests', $organization))->toBeFalse();
    expect($doorStaff->can('viewFinancials', $organization))->toBeFalse();
});

it('viewer ne peut effectuer aucune écriture', function (): void {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $writeAbilities = [
        'inviteMembers',
        'createEvents',
        'deleteEvents',
        'updateEvents',
        'updateGuests',
        'sendCommunications',
        'checkIn',
        'refundTickets',
        'manageBilling',
        'exportData',
    ];

    foreach ($writeAbilities as $ability) {
        expect($viewer->can($ability, $organization))->toBeFalse("{$ability} devrait être refusé au viewer.");
    }
});

it('l\'accès financier de l\'éditeur suit le réglage de l\'organisation', function (): void {
    $organization = Organization::factory()->create(['allow_editor_financial_access' => false]);
    $editor = User::factory()->create();
    $editor->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Editor]);

    expect($editor->can('viewFinancials', $organization))->toBeFalse();
    expect($editor->can('exportData', $organization))->toBeFalse();

    $organization->update(['allow_editor_financial_access' => true]);

    expect($editor->can('viewFinancials', $organization->fresh()))->toBeTrue();
    expect($editor->can('exportData', $organization->fresh()))->toBeTrue();
});

it('une tentative non autorisée renvoie 403, jamais 404 ni erreur serveur', function (): void {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $response = $this->actingAs($viewer)->get('/audit-log');

    $response->assertForbidden();
    $response->assertStatus(403);
});
