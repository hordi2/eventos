<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Mail\SpeakerPortalInvitationMail;
use App\Models\User;
use App\Support\Antivirus\FileScanner;
use App\Support\Antivirus\FileScanStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Page\GetEventPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeFileScanner;

beforeEach(function (): void {
    config(['filesystems.registration_files_disk' => 'local']);
    Storage::fake('local');

    $this->scanner = new FakeFileScanner;
    app()->instance(FileScanner::class, $this->scanner);
});

/**
 * Conférence avec une session en salle, un intervenant qui y parle, et les
 * informations pratiques de l'organisateur.
 *
 * @return array{organization: Organization, event: Event, admin: User, speaker: Speaker, portal: string}
 */
function eventWithSpeakerPortal(): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $start = CarbonImmutable::parse('2026-12-10 18:00', 'UTC');

    $event = Event::factory()->for($organization)->create([
        'title' => 'Conférence Itaza',
        'timezone' => 'UTC',
        'start_at' => $start,
        'end_at' => $start->addHours(6),
        'status' => EventStatus::Published,
        'speaker_briefing' => "Accueil dès 17h au 2e étage. Demandez Awa à l'entrée.",
    ]);

    $session = Event::factory()->for($organization)->subEventOf($event)->create([
        'title' => 'Ouverture',
        'timezone' => 'UTC',
        'start_at' => $start,
        'end_at' => $start->addHour(),
        'room' => 'Grand amphithéâtre',
    ]);

    $speaker = Speaker::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'name' => 'Moussa Kabila',
        'email' => 'moussa@example.com',
    ]);
    $speaker->sessions()->attach($session->id, ['organization_id' => $organization->id]);

    $portal = "/intervenant/{$organization->slug}/{$speaker->portal_token}";
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'speaker' => $speaker, 'portal' => $portal];
}

function portalSpeaker(Organization $organization): Speaker
{
    app(CurrentOrganization::class)->set($organization);
    $speaker = Speaker::query()->sole();
    app(CurrentOrganization::class)->clear();

    return $speaker;
}

it('ouvre le portail depuis le lien personnel, avec le créneau et les informations pratiques', function (): void {
    ['portal' => $portal] = eventWithSpeakerPortal();

    $this->get($portal)
        ->assertOk()
        ->assertSee('Moussa Kabila')
        ->assertSee('Ouverture')
        ->assertSee('Grand amphithéâtre')
        ->assertSee('Accueil dès 17h au 2e étage')
        ->assertSee('Conférence Itaza');
});

it('refuse un jeton inventé et un événement archivé', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithSpeakerPortal();

    $this->get("/intervenant/{$organization->slug}/jeton-invente")->assertNotFound();

    app(CurrentOrganization::class)->set($organization);
    $event->update(['status' => EventStatus::Archived]);
    $token = Speaker::query()->sole()->portal_token;
    app(CurrentOrganization::class)->clear();

    $this->get("/intervenant/{$organization->slug}/{$token}")->assertGone();
});

it('confirme le créneau, puis laisse revenir sur sa réponse', function (): void {
    ['organization' => $organization, 'admin' => $admin, 'event' => $event, 'portal' => $portal] = eventWithSpeakerPortal();

    $this->post("{$portal}/reponse", ['response' => 'accept', 'note' => 'Je serai là dès 17h.'])
        ->assertSessionHas('status', 'speaker-response-saved');

    $speaker = portalSpeaker($organization);
    expect($speaker->confirmed_at)->not->toBeNull()
        ->and($speaker->declined_at)->toBeNull()
        ->and($speaker->response_note)->toBe('Je serai là dès 17h.');

    // L'organisateur voit la réponse sur son écran.
    $this->actingAs($admin)->get("/events/{$event->id}/intervenants")->assertInertia(fn ($page) => $page
        ->where('speakers.0.status', 'confirmed')
        ->where('speakers.0.responseNote', 'Je serai là dès 17h.'));

    $this->post("{$portal}/reponse", ['response' => 'decline']);

    $speaker = portalSpeaker($organization);
    expect($speaker->confirmed_at)->toBeNull()
        ->and($speaker->declined_at)->not->toBeNull();
});

it('refuse une réponse vide', function (): void {
    ['portal' => $portal] = eventWithSpeakerPortal();

    $this->post("{$portal}/reponse", [])->assertSessionHasErrors('response');
});

it('met le support en quarantaine, le déclare sain, puis l\'organisateur le télécharge', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'portal' => $portal] = eventWithSpeakerPortal();

    $this->post("{$portal}/support", ['support' => UploadedFile::fake()->create('Mes diapos.pdf', 120, 'application/pdf')])
        ->assertSessionHasNoErrors();

    $speaker = portalSpeaker($organization);
    expect($speaker->support_scan_status)->toBe(FileScanStatus::Clean)
        ->and($speaker->support_original_name)->toBe('Mes diapos.pdf')
        ->and($speaker->support_path)->toStartWith("speaker-supports/{$organization->id}/");
    Storage::disk('local')->assertExists($speaker->support_path);
    expect(Storage::disk('local')->allFiles(Speaker::SUPPORT_QUARANTINE_DIRECTORY))->toBe([]);

    $this->get($portal)->assertSee('Mes diapos.pdf');

    $this->actingAs($admin)
        ->get("/events/{$event->id}/intervenants/{$speaker->id}/support")
        ->assertOk()
        ->assertDownload('Mes diapos.pdf');
});

it('refuse un support infecté et ne le propose jamais au téléchargement', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'portal' => $portal] = eventWithSpeakerPortal();
    $this->scanner->infection = 'Eicar-Test-Signature';

    $this->post("{$portal}/support", ['support' => UploadedFile::fake()->create('diapos.pdf', 120, 'application/pdf')]);

    $speaker = portalSpeaker($organization);
    expect($speaker->support_scan_status)->toBe(FileScanStatus::Infected);
    Storage::disk('local')->assertMissing($speaker->support_path);

    $this->get($portal)->assertSee("refusé par l'analyse antivirus");
    $this->actingAs($admin)->get("/events/{$event->id}/intervenants/{$speaker->id}/support")->assertNotFound();
});

it('refuse un format qui n\'est pas un support de présentation', function (): void {
    ['portal' => $portal] = eventWithSpeakerPortal();

    $this->post("{$portal}/support", ['support' => UploadedFile::fake()->create('script.sh', 2, 'text/x-shellscript')])
        ->assertSessionHasErrors('support');
});

it('envoie le lien du portail par e-mail et le renouvelle sur demande', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'portal' => $portal] = eventWithSpeakerPortal();
    $speaker = portalSpeaker($organization);

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants/{$speaker->id}/lien")
        ->assertSessionHas('status', 'speaker-link-sent');

    Mail::assertQueued(SpeakerPortalInvitationMail::class, fn (SpeakerPortalInvitationMail $mail): bool => $mail->hasTo('moussa@example.com'));
    expect(portalSpeaker($organization)->portal_sent_at)->not->toBeNull();

    // Renouveler le lien coupe aussitôt l'ancien.
    $this->actingAs($admin)->post("/events/{$event->id}/intervenants/{$speaker->id}/lien/renouveler")
        ->assertSessionHas('status', 'speaker-link-renewed');

    $this->get($portal)->assertNotFound();
    $this->get("/intervenant/{$organization->slug}/".portalSpeaker($organization)->portal_token)->assertOk();
});

it('demande une adresse avant d\'envoyer le lien', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithSpeakerPortal();

    app(CurrentOrganization::class)->set($organization);
    $speaker = Speaker::query()->sole();
    $speaker->update(['email' => null]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants/{$speaker->id}/lien")->assertSessionHasErrors('email');
});

it('enregistre les informations pratiques et les réserve aux membres qui modifient l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithSpeakerPortal();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants/informations", ['speaker_briefing' => 'Régie ouverte dès 16h.'])
        ->assertSessionHas('status', 'speaker-briefing-saved');

    app(CurrentOrganization::class)->set($organization);
    expect($event->fresh()->speaker_briefing)->toBe('Régie ouverte dès 16h.');
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->post("/events/{$event->id}/intervenants/informations", ['speaker_briefing' => 'Non.'])->assertForbidden();
});

it('ne laisse jamais le lien personnel apparaître sur la page publique', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithSpeakerPortal();
    $token = portalSpeaker($organization)->portal_token;

    app(CurrentOrganization::class)->set($organization);
    // La page publique affiche bien les intervenants.
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [['id' => 'a', 'type' => 'speakers', 'title' => null]],
    ]);
    $page = app(GetEventPage::class)->handle($event->refresh());
    app(CurrentOrganization::class)->clear();

    expect($page->speakers)->toHaveCount(1)
        ->and($page->speakers[0])->not->toHaveKey('portalUrl')
        ->and($page->speakers[0])->not->toHaveKey('email')
        ->and(json_encode($page->speakers))->not->toContain($token);
});
