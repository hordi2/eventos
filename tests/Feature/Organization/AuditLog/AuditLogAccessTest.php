<?php

declare(strict_types=1);

use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;

it('permet au owner et à l\'admin de consulter le journal d\'audit de leur organisation', function (): void {
    foreach ([MembershipRole::Owner, MembershipRole::Admin] as $role) {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

        $this->actingAs($user)->get('/audit-log')->assertOk();
    }
});

it('refuse l\'accès au journal d\'audit à un membre qui n\'est ni owner ni admin', function (): void {
    foreach ([MembershipRole::Editor, MembershipRole::DoorStaff, MembershipRole::Viewer] as $role) {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $user->memberships()->create(['organization_id' => $organization->id, 'role' => $role]);

        $this->actingAs($user)->get('/audit-log')->assertForbidden();
    }
});

it('refuse l\'accès au journal d\'audit à un visiteur non authentifié', function (): void {
    $this->get('/audit-log')->assertRedirect(route('login'));
});

it('permet au owner d\'exporter le journal en csv et journalise l\'export', function (): void {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();
    $owner->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Owner]);

    $response = $this->actingAs($owner)->get('/audit-log/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect(AuditLog::query()->where('action', 'audit_log.exported')->where('causer_id', $owner->id)->exists())
        ->toBeTrue();
});

it('un journal d\'une organisation n\'apparaît jamais dans celui d\'une autre', function (): void {
    $ownerA = User::factory()->create();
    $organizationA = Organization::factory()->create();
    $ownerA->memberships()->create(['organization_id' => $organizationA->id, 'role' => MembershipRole::Owner]);

    $organizationB = Organization::factory()->create();
    AuditLog::factory()->create(['organization_id' => $organizationB->id, 'action' => 'action-de-b']);

    $response = $this->actingAs($ownerA)->get('/audit-log');

    $response->assertInertia(fn ($page) => $page
        ->where('logs.data', fn ($logs) => collect($logs)->pluck('action')->doesntContain('action-de-b')));
});
