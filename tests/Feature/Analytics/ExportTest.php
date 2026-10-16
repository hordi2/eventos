<?php

declare(strict_types=1);

use App\Domain\Analytics\Models\Export;
use App\Domain\CheckIn\Models\CheckIn;
use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Models\FieldType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Ticketing\Models\Order;
use App\Domain\Ticketing\Models\OrderStatus;
use App\Models\User;
use App\Support\Money;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('local');
});

it('exporte les invités en CSV, filtrés par segment, et journalise la demande', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $confirmed = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi']);
    $cancelled = Contact::factory()->for($organization)->create(['first_name' => 'Paul', 'last_name' => 'Kanku']);
    registerContactForEvent($organization, $event, $confirmed, RegistrationStatus::Confirmed);
    registerContactForEvent($organization, $event, $cancelled, RegistrationStatus::Cancelled);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'contacts',
        'columns' => ['first_name', 'last_name', 'status'],
        'segment' => 'confirmes',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('row_count', 1);
    $exportId = $response->json('id');

    $download = $this->actingAs($owner)->get("/events/{$event->id}/exports/{$exportId}/download");
    $download->assertOk();
    $download->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($download->getContent())->toContain('Grace')->not->toContain('Paul');

    expect(AuditLog::query()->where('action', 'export.requested')->where('subject_id', $exportId)->exists())->toBeTrue();
});

it('neutralise une valeur de contact qui ressemble à une formule, pour empêcher l\'injection CSV à l\'ouverture', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::factory()->for($organization)->create(['first_name' => '=1+1', 'last_name' => 'Kanku']);
    registerContactForEvent($organization, $event, $contact, RegistrationStatus::Confirmed);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'contacts',
        'columns' => ['first_name', 'last_name', 'status'],
    ]);
    $response->assertCreated();

    $download = $this->actingAs($owner)->get("/events/{$event->id}/exports/{$response->json('id')}/download");
    expect($download->getContent())->toContain('\'=1+1');
});

it('exporte les commandes payées en CSV', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    Order::factory()->for($organization)->create([
        'event_id' => $event->id,
        'status' => OrderStatus::Paid,
        'buyer_name' => 'Jean Test',
        'total' => Money::fromMinorUnits(5000, 'EUR'),
    ]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'orders',
        'columns' => ['buyer_name', 'status', 'total'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('row_count', 1);

    $download = $this->actingAs($owner)->get("/events/{$event->id}/exports/{$response->json('id')}/download");
    expect($download->getContent())->toContain('Jean Test')->toContain('Payée');
});

it('conserve dans l\'export la réponse à une question retirée depuis une version plus récente du formulaire', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
        ['key' => 'diner', 'type' => 'yes_no', 'label' => 'Présent au dîner ?'],
    ]);

    answerAsGuest($this, $event, "/r/{$organization->slug}/{$event->slug}", 'presente@example.com', [
        'nom_complet' => 'Awa Diallo',
        'diner' => '1',
    ]);

    app(CurrentOrganization::class)->set($organization);
    $owner = User::factory()->create();
    Membership::factory()->for($organization)->for($owner)->create(['role' => MembershipRole::Owner]);
    $form = Form::query()->where('event_id', $event->id)->firstOrFail();
    // "diner" retiré dans cette nouvelle version : la réponse déjà
    // collectée ne doit pas pour autant disparaître de l'export (§4.7).
    app(ReviseForm::class)->handle($form, $owner, [
        ['key' => 'nom_complet', 'type' => FieldType::ShortText, 'label' => 'Nom complet', 'is_required' => true],
    ]);
    app(PublishFormVersion::class)->handle($form->fresh(), $owner);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'registrations',
        'columns' => ['first_name', 'last_name', 'question:diner'],
    ]);

    $response->assertCreated();
    $download = $this->actingAs($owner)->get("/events/{$event->id}/exports/{$response->json('id')}/download");
    expect($download->getContent())->toContain('Awa')->toContain('Oui');
});

it('exporte les invités enregistrés (check-ins) en CSV', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    $attendee = makeCheckedInAttendee($organization, $event);

    app(CurrentOrganization::class)->set($organization);
    CheckIn::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'attendee_id' => $attendee->id,
        'device_local_id' => (string) Str::uuid(),
        'direction' => 'check_in',
        'status' => 'accepted',
        'recorded_at' => now(),
    ]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'checkins',
        'columns' => ['name', 'checked_in_at'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('row_count', 1);
});

it('refuse une demande d\'export à un rôle sans la capacité exportData', function (): void {
    ['event' => $event, 'doorStaff' => $viewer] = makeCheckInEvent(MembershipRole::Viewer);

    $response = $this->actingAs($viewer)->postJson("/events/{$event->id}/exports", [
        'type' => 'contacts',
        'columns' => ['first_name'],
    ]);

    $response->assertForbidden();
});

it('refuse une colonne inconnue pour le type d\'export demandé', function (): void {
    ['event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);

    $response = $this->actingAs($owner)->postJson("/events/{$event->id}/exports", [
        'type' => 'contacts',
        'columns' => ['buyer_name'],
    ]);

    $response->assertUnprocessable();
});

it('empêche le téléchargement d\'un export expiré', function (): void {
    ['organization' => $organization, 'event' => $event, 'doorStaff' => $owner] = makeCheckInEvent(MembershipRole::Owner);
    app(CurrentOrganization::class)->set($organization);
    $export = Export::factory()->for($organization)->expired()->create(['event_id' => $event->id, 'requested_by' => $owner->id]);
    app(CurrentOrganization::class)->clear();

    $response = $this->actingAs($owner)->get("/events/{$event->id}/exports/{$export->id}/download");

    $response->assertNotFound();
});
