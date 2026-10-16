<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Messaging\Models\WhatsappWebhookEvent;
use App\Domain\Organization\Models\Organization;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Testing\TestResponse;
use Twilio\Security\RequestValidator;

beforeEach(function (): void {
    config(['services.twilio.auth_token' => 'test-auth-token']);
});

/**
 * @param  array<string, string>  $payload
 */
function postSignedTwilioInboundWebhook(array $payload): TestResponse
{
    $url = route('webhooks.twilio-whatsapp.inbound');
    $signature = (new RequestValidator('test-auth-token'))->computeSignature($url, $payload);

    return test()->withHeader('X-Twilio-Signature', $signature)->post($url, $payload);
}

/**
 * @return array{organization: Organization, event: Event, invitee: EventInvitee, phone: string}
 */
function makeWhatsappInvitee(array $fields = [], array $eventOverrides = []): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent($fields, [
        'type' => EventType::Wedding,
        ...$eventOverrides,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['phone_e164' => '+243800000001']);
    $invitee = EventInvitee::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'contact_id' => $contact->id,
    ]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => '+243800000001'];
}

it('refuse une requête sans signature Twilio valide', function (): void {
    test()->post(route('webhooks.twilio-whatsapp.inbound'), [
        'MessageSid' => 'SM1',
        'From' => 'whatsapp:+243800000001',
        'ButtonPayload' => 'rsvp_yes:1:un-jeton',
    ])->assertForbidden();
});

it('confirme la présence quand l\'invité touche « Je viens » sur un formulaire sans question obligatoire', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee();

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-yes',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_yes:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->sole();
    expect($registration->status)->toBe(RegistrationStatus::Confirmed);
    app(CurrentOrganization::class)->clear();
});

it('enregistre un refus quand l\'invité touche « Je ne viens pas »', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee();

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-no',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_no:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->sole();
    expect($registration->status)->toBe(RegistrationStatus::Declined);
    app(CurrentOrganization::class)->clear();
});

it('n\'inscrit personne quand le formulaire exige une réponse et qu\'aucune n\'est fournie', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee([
        ['key' => 'allergies', 'type' => 'short_text', 'label' => 'Allergies', 'is_required' => true],
    ]);

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-blocked',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_yes:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('ne traite un même clic qu\'une seule fois', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee();

    $payload = [
        'MessageSid' => 'SM-dup',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_yes:{$organization->id}:{$invitee->invitation_token}",
    ];
    postSignedTwilioInboundWebhook($payload)->assertOk();
    postSignedTwilioInboundWebhook($payload)->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->count())->toBe(1);
    app(CurrentOrganization::class)->clear();

    expect(WhatsappWebhookEvent::query()->where('event_id', 'inbound:SM-dup')->count())->toBe(1);
});

it('ignore un second clic, même sur l\'autre bouton, sans changer la réponse déjà enregistrée', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee();

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-first',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_yes:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    // Changement d'avis par un second clic : hors périmètre (voir le
    // docblock de RecordWhatsappRsvpReply) — la clé d'idempotence de
    // SubmitRegistration retombe sur l'inscription déjà créée.
    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-second',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_no:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->sole();
    expect($registration->status)->toBe(RegistrationStatus::Confirmed);
    app(CurrentOrganization::class)->clear();
});

it('ignore un clic venu d\'un autre numéro que celui de l\'invité', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee] = makeWhatsappInvitee();

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-wrong-number',
        'From' => 'whatsapp:+243899999999',
        'ButtonPayload' => "rsvp_yes:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('ignore un message texte libre, sans bouton reconnu', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee();

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-freetext',
        'From' => "whatsapp:{$phone}",
        'Body' => 'Oui je viens avec plaisir !',
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});

it('refuse un « je ne viens pas » quand l\'événement ne propose pas cette réponse', function (): void {
    ['organization' => $organization, 'event' => $event, 'invitee' => $invitee, 'phone' => $phone] = makeWhatsappInvitee([], ['type' => EventType::Conference]);

    postSignedTwilioInboundWebhook([
        'MessageSid' => 'SM-no-decline',
        'From' => "whatsapp:{$phone}",
        'ButtonPayload' => "rsvp_no:{$organization->id}:{$invitee->invitation_token}",
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->where('contact_id', $invitee->contact_id)->exists())->toBeFalse();
    app(CurrentOrganization::class)->clear();
});
