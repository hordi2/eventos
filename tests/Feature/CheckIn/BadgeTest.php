<?php

declare(strict_types=1);

use App\Domain\CheckIn\Models\BadgeBatch;
use App\Domain\CheckIn\Models\BadgeBatchStatus;
use App\Domain\CheckIn\Models\BadgeSettings;
use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\Tag;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\CheckIn\BuildBadgeContexts;
use App\Support\CheckIn\GetEventGuestList;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeTaggedAttendee(Organization $organization, Event $event, string $tagColor): Attendee
{
    app(CurrentOrganization::class)->set($organization);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::factory()->create(['organization_id' => $organization->id, 'form_id' => $form->id]);
    $contact = Contact::factory()->for($organization)->create();
    $tag = Tag::factory()->for($organization)->create(['color' => $tagColor]);
    $contact->tags()->attach($tag->id, ['organization_id' => $organization->id, 'created_at' => now()]);
    $registration = Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'contact_id' => $contact->id,
    ]);
    $attendee = Attendee::factory()->create([
        'organization_id' => $organization->id,
        'registration_id' => $registration->id,
        'is_primary' => true,
    ]);
    app(CurrentOrganization::class)->clear();

    return $attendee;
}

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
    Storage::fake('local');
});

it('génère un badge individuel en PDF pour un invité RSVP avec la couleur de son tag', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $attendee = makeTaggedAttendee($organization, $event, '#ff0000');

    $response = $this->actingAs($doorStaff)->get("/events/{$event->id}/badges/attendee/{$attendee->id}");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('génère un badge individuel en PDF pour un billet payé, avec QR', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    $ticket = makePaidTicket($organization, $event);

    $response = $this->actingAs($doorStaff)->get("/events/{$event->id}/badges/ticket/{$ticket->id}");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('la couleur de badge vient du tag du contact pour un invité RSVP, jamais pour un billet', function (): void {
    ['organization' => $organization, 'event' => $event] = makeCheckInEvent();
    $attendee = makeTaggedAttendee($organization, $event, '#00ff00');
    $ticket = makePaidTicket($organization, $event);

    app(CurrentOrganization::class)->set($organization);
    $guests = app(GetEventGuestList::class)->handle($event);
    $contexts = app(BuildBadgeContexts::class)->handle($event, $organization->name, $guests, null);
    app(CurrentOrganization::class)->clear();

    $attendeeContext = collect($contexts)->first(fn ($c) => $c->guestName === $attendee->first_name.' '.$attendee->last_name);
    $ticketContext = collect($contexts)->first(fn ($c) => $c->qrDataUri !== null);

    expect($attendeeContext->accentColor)->toBe('#00ff00');
    expect($ticketContext->accentColor)->toBeNull();
    expect($ticketContext->qrDataUri)->not->toBeNull();
});

it('téléverse un logo pour un événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();

    $response = $this->actingAs($doorStaff)->post("/events/{$event->id}/badges/logo", [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $response->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect(BadgeSettings::query()->where('event_id', $event->id)->exists())->toBeTrue();
    app(CurrentOrganization::class)->clear();
});

it('lance une génération en masse et la marque terminée', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $doorStaff] = makeCheckInEvent();
    makeTaggedAttendee($organization, $event, '#0000ff');
    makePaidTicket($organization, $event);

    $start = $this->actingAs($doorStaff)->postJson("/events/{$event->id}/badges/batches");
    $start->assertOk();
    $batchId = $start->json('id');

    app(CurrentOrganization::class)->set($organization);
    $batch = BadgeBatch::query()->findOrFail($batchId);
    expect($batch->status)->toBe(BadgeBatchStatus::Completed);
    expect($batch->guest_count)->toBe(2);
    app(CurrentOrganization::class)->clear();

    $download = $this->actingAs($doorStaff)->get("/events/{$event->id}/badges/batches/{$batchId}/download");
    $download->assertOk();
    $download->assertHeader('content-type', 'application/pdf');
});

it("refuse l'accès aux badges à un rôle sans la capacité checkIn", function (): void {
    ['event' => $event, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $response = $this->actingAs($viewer)->get("/events/{$event->id}/badges");

    $response->assertForbidden();
});
