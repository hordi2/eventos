<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\Speaker;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Événement avec deux sessions le même jour, dont une dans une salle
 * nommée, et l'administrateur qui les gère.
 *
 * @return array{organization: Organization, event: Event, admin: User, dinner: Event, workshop: Event}
 */
function eventWithProgramme(): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $start = CarbonImmutable::parse('2026-12-10 18:00', 'UTC');
    $event = Event::factory()->for($organization)->create(['title' => 'Conférence', 'timezone' => 'UTC', 'start_at' => $start, 'end_at' => $start->addHours(6)]);

    $dinner = Event::factory()->for($organization)->subEventOf($event)->create([
        'title' => 'Ouverture',
        'timezone' => 'UTC',
        'start_at' => $start,
        'end_at' => $start->addHour(),
        'room' => 'Grand amphithéâtre',
    ]);
    $workshop = Event::factory()->for($organization)->subEventOf($event)->create([
        'title' => 'Atelier photo',
        'timezone' => 'UTC',
        'start_at' => $start->addHours(2),
        'end_at' => $start->addHours(3),
        'room' => 'Salle B',
    ]);

    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'dinner' => $dinner, 'workshop' => $workshop];
}

it('ajoute un intervenant et le rattache à ses sessions', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'dinner' => $dinner] = eventWithProgramme();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants", [
        'name' => 'Awa Diallo',
        'role' => 'Directrice des opérations',
        'company' => 'Itaza',
        'bio' => 'Vingt ans de terrain.',
        'website_url' => 'https://awa.example',
        'session_ids' => [$dinner->id],
    ])->assertSessionHas('status', 'speaker-saved');

    app(CurrentOrganization::class)->set($organization);
    $speaker = Speaker::query()->sole();
    expect($speaker->name)->toBe('Awa Diallo')
        ->and($speaker->sessions()->pluck('events.id')->all())->toBe([$dinner->id]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/intervenants")->assertInertia(fn ($page) => $page
        ->component('Events/Speakers')
        ->where('speakers.0.name', 'Awa Diallo')
        ->where('speakers.0.sessions.0', 'Ouverture')
        ->where('sessions.0.room', 'Grand amphithéâtre')
        ->where('sessions.0.title', 'Ouverture'));
});

it('refuse une adresse web incomplète et une session d\'un autre événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithProgramme();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants", ['name' => 'Awa', 'website_url' => 'awa.example'])
        ->assertSessionHasErrors('website_url');

    app(CurrentOrganization::class)->set($organization);
    $other = Event::factory()->for($organization)->create();
    $foreignSession = Event::factory()->for($organization)->subEventOf($other)->create();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants", ['name' => 'Awa', 'session_ids' => [$foreignSession->id]]);

    app(CurrentOrganization::class)->set($organization);
    expect(Speaker::query()->sole()->sessions()->count())->toBe(0);
});

it('téléverse la photo de l\'intervenant et l\'affiche sur la page publique', function (): void {
    Storage::fake('public');
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'dinner' => $dinner] = eventWithProgramme();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants", ['name' => 'Awa Diallo', 'session_ids' => [$dinner->id]]);

    app(CurrentOrganization::class)->set($organization);
    $speaker = Speaker::query()->sole();
    // Une page publique n'existe qu'avec un formulaire publié.
    $form = app(CreateForm::class)->handle($organization, $event->id, $admin, ['name' => 'Inscription', 'fields' => []]);
    app(PublishFormVersion::class)->handle($form, $admin);
    $event->update(['status' => EventStatus::Published]);
    // La page affiche les intervenants et le programme.
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'blocks' => [
            ['id' => 'a', 'type' => 'speakers', 'title' => null],
            ['id' => 'b', 'type' => 'sessions', 'title' => null],
        ],
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)
        ->post("/events/{$event->id}/intervenants/{$speaker->id}/photo", ['photo' => UploadedFile::fake()->image('awa.jpg', 800, 800)])
        ->assertOk();
    auth()->logout();

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee('Awa Diallo')
        ->assertSee('Ouverture')
        ->assertSee('Grand amphithéâtre')
        ->assertSee('Atelier photo');
});

it('signale deux sessions qui se chevauchent dans la même salle', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'dinner' => $dinner] = eventWithProgramme();

    app(CurrentOrganization::class)->set($organization);
    // Une session ailleurs dans le temps, mais dans la même salle que l'ouverture.
    Event::factory()->for($organization)->subEventOf($event)->create([
        'title' => 'Répétition',
        'timezone' => 'UTC',
        'start_at' => $dinner->start_at->addMinutes(30),
        'end_at' => $dinner->end_at->addMinutes(30),
        'room' => 'grand amphithéâtre',
    ]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/sub-events")->assertInertia(fn ($page) => $page
        ->where('subEvents.0.conflicts', fn ($conflicts): bool => in_array('Répétition (même salle)', collect($conflicts)->all(), true)));
});

it('retire un intervenant du programme', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'dinner' => $dinner] = eventWithProgramme();

    $this->actingAs($admin)->post("/events/{$event->id}/intervenants", ['name' => 'Awa Diallo', 'session_ids' => [$dinner->id]]);

    app(CurrentOrganization::class)->set($organization);
    $speaker = Speaker::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->delete("/events/{$event->id}/intervenants/{$speaker->id}")->assertSessionHas('status', 'speaker-removed');

    app(CurrentOrganization::class)->set($organization);
    expect(Speaker::query()->count())->toBe(0)
        ->and(Speaker::withTrashed()->count())->toBe(1);
});

it('réserve les intervenants aux membres qui peuvent modifier l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithProgramme();

    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$event->id}/intervenants")->assertForbidden();
    $this->actingAs($viewer)->post("/events/{$event->id}/intervenants", ['name' => 'Awa'])->assertForbidden();
});
