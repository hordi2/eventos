<?php

declare(strict_types=1);

use App\Domain\Organization\Models\MembershipRole;
use App\Mail\ReferralInvitationMail;
use Illuminate\Support\Facades\Mail;

it('affiche le lien de parrainage, en générant un code s\'il manque', function (): void {
    ['organization' => $organization, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    expect($organization->referral_code)->toBeNull();

    $response = $this->actingAs($owner)->get('/settings/referral');

    $response->assertOk();
    expect($organization->fresh()->referral_code)->not->toBeNull();
});

it('refuse le parrainage à un rôle sans manageBilling', function (): void {
    ['doorStaff' => $editor] = makeCheckInEvent(MembershipRole::Editor);

    $response = $this->actingAs($editor)->get('/settings/referral');

    $response->assertForbidden();
});

it('envoie une invitation de parrainage par e-mail', function (): void {
    Mail::fake();
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/referral/invitations', [
        'email' => 'ami@example.test',
    ]);

    $response->assertRedirect();
    Mail::assertQueued(ReferralInvitationMail::class, fn (ReferralInvitationMail $mail): bool => $mail->hasTo('ami@example.test'));
});

it('refuse une invitation de parrainage sans adresse e-mail valide', function (): void {
    Mail::fake();
    ['doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->post('/settings/referral/invitations', ['email' => 'pas-une-adresse']);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingQueued();
});
