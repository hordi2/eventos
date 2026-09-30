<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\EventType;
use App\Domain\Messaging\Models\FollowUpMessage;
use App\Domain\Messaging\Models\WhatsappFollowUpTemplate;
use App\Domain\Messaging\Models\WhatsappMessage;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\RegistrationDecisionMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Organisation dont les messages de suivi partent sur le canal donné, avec
 * un modèle WhatsApp approuvé pour chaque cas.
 */
function organizationWithFollowUp(Organization $organization, string $channel): WhatsappTemplate
{
    app(CurrentOrganization::class)->set($organization);
    $organization->update(['follow_up_channel' => $channel]);

    $template = WhatsappTemplate::factory()->create(['organization_id' => $organization->id]);

    foreach (FollowUpMessage::cases() as $message) {
        WhatsappFollowUpTemplate::query()->create([
            'organization_id' => $organization->id,
            'purpose' => $message,
            'whatsapp_template_id' => $template->id,
        ]);
    }
    app(CurrentOrganization::class)->clear();

    return $template;
}

it('prévient par WhatsApp un invité dont la demande est acceptée', function (): void {
    Mail::fake();
    Queue::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent();
    organizationWithFollowUp($organization, 'both');

    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');

    app(CurrentOrganization::class)->set($organization);
    // L'invité est un contact connu : c'est lui qui reçoit le WhatsApp.
    Contact::query()->findOrFail($registration->fresh()->contact_id)->update([
        'phone_e164' => '+243970000001',
        'whatsapp_consent' => true,
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve")->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappMessage::query()->count())->toBe(1)
        ->and(WhatsappMessage::query()->sole()->to_phone_e164)->toBe('+243970000001');
    app(CurrentOrganization::class)->clear();

    // Canal « les deux » : l'e-mail part aussi.
    Mail::assertQueued(RegistrationDecisionMail::class);
});

it('n\'envoie plus l\'e-mail quand l\'organisation a choisi WhatsApp seulement', function (): void {
    Mail::fake();
    Queue::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent();
    organizationWithFollowUp($organization, 'whatsapp');

    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');

    app(CurrentOrganization::class)->set($organization);
    Contact::query()->findOrFail($registration->fresh()->contact_id)->update(['phone_e164' => '+243970000002', 'whatsapp_consent' => true]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/registrations/{$registration->id}/reject", ['reason' => 'Complet.']);

    // Les organisateurs sont prévenus des inscriptions par ailleurs : seul
    // le message de décision ne doit pas partir par e-mail.
    Mail::assertNotQueued(RegistrationDecisionMail::class);
    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappMessage::query()->count())->toBe(1);
});

it('ne part pas en WhatsApp sans accord de l\'invité ni modèle désigné', function (): void {
    Mail::fake();
    Queue::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent();
    organizationWithFollowUp($organization, 'both');

    $registration = answerAsGuest($this, $event, $base, 'awa@example.com');

    // Un numéro, mais aucun accord WhatsApp enregistré.
    app(CurrentOrganization::class)->set($organization);
    Contact::query()->findOrFail($registration->fresh()->contact_id)->update(['phone_e164' => '+243970000003', 'whatsapp_consent' => false]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve");

    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappMessage::query()->count())->toBe(0);
    // L'e-mail, lui, part toujours.
    Mail::assertQueued(RegistrationDecisionMail::class);
});

it('règle le canal et les modèles des messages de suivi', function (): void {
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $template = WhatsappTemplate::factory()->create(['organization_id' => $organization->id]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->get('/whatsapp-templates')->assertInertia(fn ($page) => $page
        ->where('followUp.channel', 'email')
        ->has('followUp.messages', 4));

    $this->actingAs($owner)->post('/whatsapp-templates/follow-up', [
        'channel' => 'both',
        'templates' => [
            'registration_approved' => $template->id,
            'donation_receipt' => '',
        ],
    ])->assertSessionHas('status', 'follow-up-saved');

    app(CurrentOrganization::class)->set($organization);
    expect($organization->fresh()->follow_up_channel)->toBe('both')
        ->and(WhatsappFollowUpTemplate::query()->count())->toBe(1)
        ->and(WhatsappFollowUpTemplate::query()->sole()->purpose)->toBe(FollowUpMessage::RegistrationApproved);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($owner)->post('/whatsapp-templates/follow-up', ['channel' => 'fumée'])->assertSessionHasErrors('channel');
});

it('réserve ce réglage aux membres qui gèrent les communications', function (): void {
    [$organization] = organizationWithContactRole(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->post('/whatsapp-templates/follow-up', ['channel' => 'both'])->assertForbidden();
});

it('prévient par WhatsApp un invité sans adresse e-mail', function (): void {
    Mail::fake();
    Queue::fake();
    ['event' => $event, 'base' => $base, 'admin' => $admin, 'organization' => $organization] = makeApprovalEvent([], ['type' => EventType::Conference]);
    organizationWithFollowUp($organization, 'both');

    $registration = answerAsGuest($this, $event, $base, 'moussa@example.com');

    app(CurrentOrganization::class)->set($organization);
    Contact::query()->findOrFail($registration->fresh()->contact_id)->update(['phone_e164' => '+243970000004', 'whatsapp_consent' => true]);
    // Invité connu par son seul numéro : plus d'adresse sur l'inscription.
    $registration->update(['email' => '']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/registrations/{$registration->id}/approve");

    Mail::assertNotQueued(RegistrationDecisionMail::class);
    app(CurrentOrganization::class)->set($organization);
    expect(WhatsappMessage::query()->count())->toBe(1);
});
