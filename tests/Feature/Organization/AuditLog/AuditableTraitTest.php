<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;

it('journalise un changement de rôle sur une adhésion', function (): void {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::Editor,
    ]);

    $this->actingAs($admin);
    $membership->update(['role' => MembershipRole::Admin]);

    $log = AuditLog::query()->where('action', 'membership.updated')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->causer_id)->toBe($admin->id);
    expect($log->subject_id)->toBe($membership->id);
    expect($log->metadata['changes']['role'])->toBe(MembershipRole::Admin->value);
});

it('journalise la suppression d\'une adhésion', function (): void {
    $membership = Membership::factory()->create();

    $membership->delete();

    expect(AuditLog::query()->where('action', 'membership.deleted')->where('subject_id', $membership->id)->exists())
        ->toBeTrue();
});

it('ne journalise rien quand aucun attribut métier n\'a changé', function (): void {
    $membership = Membership::factory()->create();
    $countBefore = AuditLog::query()->count();

    $membership->touch();

    expect(AuditLog::query()->count())->toBe($countBefore);
});

it('journalise un changement d\'e-mail sans jamais enregistrer sa valeur en clair (T-075, RGPD)', function (): void {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['email' => 'ancien@example.com', 'first_name' => 'Jean']);

    $contact->update(['email' => 'nouveau@example.com', 'first_name' => 'Paul']);

    $log = AuditLog::query()->where('action', 'contact.updated')->latest('id')->first();
    app(CurrentOrganization::class)->clear();

    expect($log)->not->toBeNull();
    expect($log->metadata['changes']['email'])->toBe('[redacted]');
    expect($log->metadata['changes']['first_name'])->toBe('[redacted]');
    expect(json_encode($log->metadata))->not->toContain('nouveau@example.com')->not->toContain('Paul');
});
