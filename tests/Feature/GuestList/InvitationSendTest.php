<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\GenericMail;
use App\Models\User;
use App\Support\Messaging\WhatsappProvider;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class GuestListFakeWhatsappProvider implements WhatsappProvider
{
    /** @var list<array{to: string, contentSid: string, contentVariables: array<int, string>}> */
    public array $sent = [];

    public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
    {
        $this->sent[] = ['to' => $toPhoneE164, 'contentSid' => $contentSid, 'contentVariables' => $contentVariables];

        return 'SM'.bin2hex(random_bytes(16));
    }
}

/**
 * Grace et Patrick forment un groupe ; Awa s'est désabonnée ; Fatou n'a que
 * WhatsApp (avec son accord) ; Moussa a un numéro sans accord WhatsApp.
 *
 * @return array{organization: Organization, admin: User, event: Event, email: EmailTemplate, whatsapp: WhatsappTemplate, grace: EventInvitee, patrick: EventInvitee, awa: EventInvitee, fatou: EventInvitee, moussa: EventInvitee}
 */
function invitationSendFixture(): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $organization->forceFill(['sender_agreement_accepted_at' => now(), 'sender_agreement_accepted_by' => $admin->id])->save();
    $event = Event::factory()->for($organization)->published()->create(['title' => 'Gala des partenaires']);

    $invite = fn (array $contact, array $invitation = []): EventInvitee => EventInvitee::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'contact_id' => Contact::factory()->for($organization)->create($contact)->id,
        ...$invitation,
    ]);

    return [
        'organization' => $organization,
        'admin' => $admin,
        'event' => $event,
        'email' => EmailTemplate::factory()->create(['organization_id' => $organization->id, 'created_by' => $admin->id, 'name' => 'Invitation', 'subject' => 'Vous êtes invité, {{first_name}}']),
        'whatsapp' => WhatsappTemplate::factory()->create(['organization_id' => $organization->id, 'created_by' => $admin->id, 'variable_mapping' => ['first_name', 'rsvp_link']]),
        'grace' => $invite(['first_name' => 'Grace', 'email' => 'grace@exemple.cd', 'phone_e164' => null], ['group_key' => '1', 'cc_email' => 'assistante@exemple.cd']),
        'patrick' => $invite(['first_name' => 'Patrick', 'email' => 'patrick@exemple.cd', 'phone_e164' => null], ['group_key' => '1']),
        'awa' => $invite(['first_name' => 'Awa', 'email' => 'awa@exemple.sn', 'phone_e164' => null, 'unsubscribed_at' => now()]),
        'fatou' => $invite(['first_name' => 'Fatou', 'email' => null, 'phone_e164' => '+221771234567', 'whatsapp_consent' => true]),
        'moussa' => $invite(['first_name' => 'Moussa', 'email' => null, 'phone_e164' => '+221781234567', 'whatsapp_consent' => false]),
    ];
}

/**
 * @param  array<string, mixed>  $options
 * @return array<string, mixed>
 */
function sendOptions(array $options): array
{
    return ['audience' => 'all', 'mode' => 'per_invitee', 'send_key' => (string) Str::uuid(), ...$options];
}

it('annonce avant l\'envoi qui recevra le message, et pourquoi les autres n\'en recevront pas', function (): void {
    ['admin' => $admin, 'event' => $event, 'email' => $email] = invitationSendFixture();
    $url = "/events/{$event->id}/guest-list/send/preview";

    $this->actingAs($admin)->getJson($url.'?'.http_build_query(['channel' => 'email', 'template_id' => $email->id, 'audience' => 'all', 'mode' => 'per_invitee']))
        ->assertOk()
        ->assertExactJson(['recipients' => 2, 'missing' => 2, 'excluded' => 1, 'coveredByGroup' => 0]);

    // Un message par groupe : Patrick est couvert par Grace.
    $this->actingAs($admin)->getJson($url.'?'.http_build_query(['channel' => 'email', 'template_id' => $email->id, 'audience' => 'all', 'mode' => 'per_group']))
        ->assertExactJson(['recipients' => 1, 'missing' => 2, 'excluded' => 1, 'coveredByGroup' => 1]);
});

it('envoie un e-mail par groupe, avec le lien personnel, la copie et l\'agenda, puis le note sur la liste', function (): void {
    Mail::fake();
    ['organization' => $organization, 'admin' => $admin, 'event' => $event, 'email' => $email, 'grace' => $grace, 'patrick' => $patrick] = invitationSendFixture();

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id, 'mode' => 'per_group']))
        ->assertSessionHas('status', 'invitations-sending');

    Mail::assertSentCount(1);
    Mail::assertSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('grace@exemple.cd')
        && $mail->hasCc('assistante@exemple.cd')
        && str_contains($mail->bodyHtml, "/invitation/{$grace->invitation_token}")
        && $mail->icsAttachment !== null
        // Envoi de masse : lien de désabonnement.
        && $mail->unsubscribeUrl !== null);

    app(CurrentOrganization::class)->set($organization);
    expect($grace->fresh()->last_invited_via)->toBe('email');
    expect($grace->fresh()->last_invited_at)->not->toBeNull();
    expect($patrick->fresh()->last_invited_at)->toBeNull();
    expect(AuditLog::query()->where('action', 'guest_list.invitations_sent')->sole()->metadata)
        ->toMatchArray(['channel' => 'email', 'mode' => 'per_group', 'recipients' => 1, 'coveredByGroup' => 1]);
});

it('n\'envoie rien deux fois quand la même confirmation arrive deux fois', function (): void {
    Mail::fake();
    ['admin' => $admin, 'event' => $event, 'email' => $email] = invitationSendFixture();
    $options = sendOptions(['channel' => 'email', 'template_id' => $email->id]);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", $options);
    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", $options);

    Mail::assertSentCount(2);
});

it('écrit seulement aux invités sans réponse, ou aux invités cochés', function (): void {
    Mail::fake();
    ['organization' => $organization, 'admin' => $admin, 'event' => $event, 'email' => $email, 'grace' => $grace, 'patrick' => $patrick] = invitationSendFixture();
    registerContactForEvent($organization, $event, Contact::query()->findOrFail($grace->contact_id), RegistrationStatus::Confirmed);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id, 'audience' => 'unanswered']));
    Mail::assertSentCount(1);
    Mail::assertSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('patrick@exemple.cd'));

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id, 'audience' => 'selected', 'invitee_ids' => [$grace->id]]));
    Mail::assertSent(GenericMail::class, fn (GenericMail $mail): bool => $mail->hasTo('grace@exemple.cd'));
});

it('envoie par WhatsApp aux seuls invités qui l\'ont accepté, avec leur lien personnel', function (): void {
    $fake = new GuestListFakeWhatsappProvider;
    $this->app->bind(WhatsappProvider::class, fn () => $fake);
    ['admin' => $admin, 'event' => $event, 'whatsapp' => $whatsapp, 'fatou' => $fatou] = invitationSendFixture();

    $this->actingAs($admin)->getJson("/events/{$event->id}/guest-list/send/preview?".http_build_query(['channel' => 'whatsapp', 'template_id' => $whatsapp->id, 'audience' => 'all', 'mode' => 'per_invitee']))
        ->assertExactJson(['recipients' => 1, 'missing' => 3, 'excluded' => 1, 'coveredByGroup' => 0]);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'whatsapp', 'template_id' => $whatsapp->id]));

    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0]['to'])->toBe('+221771234567');
    expect($fake->sent[0]['contentVariables'][2])->toContain("/invitation/{$fatou->invitation_token}");
});

it('refuse d\'envoyer sans destinataire, sans accord d\'envoi, ou sans le droit d\'écrire aux invités', function (): void {
    Mail::fake();
    ['organization' => $organization, 'admin' => $admin, 'event' => $event, 'email' => $email] = invitationSendFixture();

    $this->actingAs($admin)->from("/events/{$event->id}/guest-list")
        ->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id, 'audience' => 'selected', 'invitee_ids' => [999999]]))
        ->assertSessionHasErrors('send');

    $organization->forceFill(['sender_agreement_accepted_at' => null])->save();
    $this->actingAs($admin)->from("/events/{$event->id}/guest-list")
        ->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id]))
        ->assertSessionHasErrors('agreement');

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    $this->actingAs($viewer)->post("/events/{$event->id}/guest-list/send", sendOptions(['channel' => 'email', 'template_id' => $email->id]))->assertForbidden();

    Mail::assertNothingSent();
});
