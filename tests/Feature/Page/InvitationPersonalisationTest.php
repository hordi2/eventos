<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Page\Models\Page;
use App\Domain\Page\Models\PageMedia;
use App\Jobs\ScanPageMediaJob;
use App\Models\User;
use App\Support\Antivirus\FileScanner;
use App\Support\Antivirus\FileScanStatus;
use App\Support\Images\CropImageToRatio;
use App\Support\Invitation\BuildInvitationPdf;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Page\GetEventPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeFileScanner;

/**
 * Personnalisation de l'invitation (D1) : les polices, le texte des boutons
 * de réponse, l'illustration déposée pour un moment du programme, le mot
 * d'accueil déposé dans Itaza, et le faire-part qui ne coupe plus un bloc
 * trop long.
 */
function invitationAdmin(object $organization): User
{
    app(CurrentOrganization::class)->set($organization);
    $user = User::factory()->create();
    $user->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    return $user;
}

it('charge les polices choisies par l\'organisateur, et elles seules', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'heading_font' => 'cormorant',
        'body_font' => 'jost',
        'script_font' => 'great-vibes',
        'blocks' => [],
    ])->assertOk();

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('Cormorant+Garamond')
        ->and($html)->toContain('Jost')
        ->and($html)->toContain('Great+Vibes')
        // Les familles non choisies ne sont pas téléchargées.
        ->and($html)->not->toContain('Bodoni+Moda')
        ->and($html)->toContain("'Great Vibes', cursive");
});

it('ne télécharge aucune police quand l\'organisateur prend celles de l\'appareil', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'heading_font' => 'georgia',
        'body_font' => 'systeme',
        'script_font' => 'aucune',
        'blocks' => [],
    ])->assertOk();

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    expect($html)->not->toContain('fonts.googleapis.com')
        // « Aucune » : le mot manuscrit reprend la police des titres.
        ->and($html)->toContain('--font-script: Georgia');
});

it('refuse une police inconnue', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)
        ->patchJson("/events/{$event->id}/page", ['heading_font' => 'comic-sans', 'blocks' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('heading_font');
});

it('écrit sur les boutons de réponse le texte voulu par l\'organisateur', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [[
        'id' => 'r',
        'type' => 'rsvp',
        'title' => 'Votre réponse',
        'yesLabel' => 'Nous serons là',
        'laterLabel' => 'Nous répondrons bientôt',
    ]]])->assertOk();

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee('Nous serons là')
        ->assertSee('Nous répondrons bientôt')
        // Un libellé laissé vide garde celui d'origine.
        ->assertDontSee('Je confirme ma présence');
});

it('affiche l\'illustration déposée pour un moment du programme', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [[
        'id' => 'p',
        'type' => 'program',
        'title' => 'Programme',
        'showIcons' => true,
        'items' => [
            ['time' => '19 h', 'title' => 'Accueil', 'path' => 'organization-images/1/accueil.png'],
            ['time' => '20 h', 'title' => 'Dîner', 'icon' => 'repas'],
        ],
    ]]])->assertOk();

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('organization-images/1/accueil.png')
        // L'autre moment garde le dessin au trait.
        ->and($html)->toContain('<svg');
});

it('fait suivre un programme trop long sur une seconde feuille du faire-part', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $items = [];

    for ($hour = 1; $hour <= 20; $hour++) {
        $items[] = ['time' => "{$hour} h", 'title' => "Moment {$hour}"];
    }

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [[
        'id' => 'p',
        'type' => 'program',
        'title' => 'Programme',
        'items' => $items,
    ]]])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $data = app(BuildInvitationPdf::class)->data($event->refresh());
    app(CurrentOrganization::class)->clear();

    $sheets = array_values(array_filter($data->blocks, fn (array $block): bool => $block['type'] === 'program'));

    expect($sheets)->toHaveCount(3)
        ->and($sheets[0]['items'])->toHaveCount(9)
        ->and($sheets[0]['continued'] ?? false)->toBeFalse()
        ->and($sheets[2]['continued'])->toBeTrue()
        // Aucun moment n'est perdu en route.
        ->and(count($sheets[0]['items']) + count($sheets[1]['items']) + count($sheets[2]['items']))->toBe(20);
});

it('garde le mot d\'accueil déposé muet tant que l\'analyse antivirus n\'a pas conclu', function (): void {
    config(['filesystems.registration_files_disk' => 'local']);
    Storage::fake('local');
    Bus::fake();

    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $response = $this->actingAs($admin)->post("/events/{$event->id}/page/media", [
        'media' => UploadedFile::fake()->create('mot-accueil.mp3', 200, 'audio/mpeg'),
    ])->assertOk();

    $token = $response->json('token');
    Bus::assertDispatched(ScanPageMediaJob::class);

    app(CurrentOrganization::class)->set($organization);
    $media = PageMedia::query()->where('token', $token)->sole();
    expect($media->scan_status)->toBe(FileScanStatus::Pending)
        ->and($media->path)->toStartWith('page-media/quarantine/');
    Storage::disk('local')->assertExists($media->path);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [[
        'id' => 'w',
        'type' => 'welcome_message',
        'title' => "Notre mot d'accueil",
        'mediaToken' => $token,
        'mediaName' => 'mot-accueil.mp3',
    ]]])->assertOk();

    // En attente du verdict : aucun lecteur, et le fichier reste introuvable.
    $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->assertDontSee('Écouter le mot');
    $this->get("/r/{$organization->slug}/{$event->slug}/media/{$token}")->assertNotFound();
});

it('joue le mot d\'accueil dès que le fichier est déclaré sain', function (): void {
    config(['filesystems.registration_files_disk' => 'local']);
    Storage::fake('local');
    app()->instance(FileScanner::class, new FakeFileScanner);

    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $token = $this->actingAs($admin)->post("/events/{$event->id}/page/media", [
        'media' => UploadedFile::fake()->create('mot-accueil.mp3', 200, 'audio/mpeg'),
    ])->assertOk()->json('token');

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [[
        'id' => 'w',
        'type' => 'welcome_message',
        'title' => "Notre mot d'accueil",
        'mediaToken' => $token,
    ]]])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $media = PageMedia::query()->where('token', $token)->sole();
    expect($media->scan_status)->toBe(FileScanStatus::Clean)
        ->and($media->path)->toStartWith('page-media/'.$organization->id);
    app(CurrentOrganization::class)->clear();

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        ->assertSee('Écouter le mot')
        ->assertSee("media/{$token}", false);

    $this->get("/r/{$organization->slug}/{$event->slug}/media/{$token}")
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
});

it('refuse un mot d\'accueil qui n\'est ni audio ni vidéo', function (): void {
    config(['filesystems.registration_files_disk' => 'local']);
    Storage::fake('local');

    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)
        ->postJson("/events/{$event->id}/page/media", ['media' => UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('media');
});

it('garde les polices de l\'invitation d\'un événement à l\'autre', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'heading_font' => 'marcellus',
        'script_font' => 'parisienne',
        'blocks' => [],
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = Page::query()->where('event_id', $event->id)->sole();
    app(CurrentOrganization::class)->clear();

    expect($page->heading_font)->toBe('marcellus')
        ->and($page->script_font)->toBe('parisienne');

    $this->actingAs($admin)->get("/events/{$event->id}/page")
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('page.heading_font', 'marcellus')
            ->where('page.script_font', 'parisienne')
            ->has('fonts.heading')
            ->has('invitationPdfUrl'));
});

it('présente les mêmes feuillets sur la page web et sur le faire-part', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    // L'organisateur place la confirmation en premier : elle n'en finira
    // pas moins l'invitation, sur l'écran comme sur le papier.
    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [
        ['id' => 'r', 'type' => 'rsvp', 'title' => 'Votre réponse'],
        ['id' => 't', 'type' => 'text', 'title' => 'Le mot des mariés', 'body' => 'Merci d\'être là.'],
        ['id' => 'p', 'type' => 'program', 'title' => 'Programme', 'items' => [['time' => '18 h', 'title' => 'Accueil']]],
    ]])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = app(GetEventPage::class)->handle($event->refresh());
    $pdf = app(BuildInvitationPdf::class)->data($event->refresh());
    app(CurrentOrganization::class)->clear();

    $types = array_column($page->blocks, 'type');

    expect($types)->toBe(['text', 'program', 'entry_qr', 'rsvp'])
        // Le papier suit la page, feuillet pour feuillet.
        ->and(array_column($pdf->blocks, 'type'))->toBe($types);

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    // Couverture, « L'essentiel », puis un feuillet par bloc.
    expect(substr_count($html, 'id="feuillet-'))->toBe(6)
        ->and($html)->toContain('06 / 06')
        // La confirmation est bien le dernier feuillet.
        ->and(strpos($html, 'Votre réponse'))->toBeGreaterThan(strpos($html, 'Le mot des mariés'))
        ->and(strpos($html, 'Votre réponse'))->toBeGreaterThan(strpos($html, 'Votre entrée'));
});

it('ferme toute invitation par le code d\'entrée puis la confirmation, même sans bloc composé', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('Votre entrée')
        ->and($html)->toContain('Confirmez votre présence');
});

it('n\'ouvre pas un feuillet pour un bloc resté vide', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", ['blocks' => [
        ['id' => 'p', 'type' => 'program', 'title' => 'Programme', 'items' => []],
        ['id' => 'g', 'type' => 'gallery', 'title' => 'Nos photos', 'items' => [[]]],
        ['id' => 'w', 'type' => 'welcome_message', 'title' => "Mot d'accueil"],
        ['id' => 't', 'type' => 'text', 'title' => 'Le mot des mariés', 'body' => 'Merci d\'être là.'],
    ]])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $page = app(GetEventPage::class)->handle($event->refresh());
    app(CurrentOrganization::class)->clear();

    // Seul le bloc qui a quelque chose à dire ouvre un feuillet.
    expect(array_column($page->blocks, 'type'))->toBe(['text', 'entry_qr', 'rsvp']);
});

it('imprime le faire-part dans les polices de la page web', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'heading_font' => 'cormorant',
        'body_font' => 'jost',
        'script_font' => 'great-vibes',
        'blocks' => [],
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $data = app(BuildInvitationPdf::class)->data($event->refresh());
    app(CurrentOrganization::class)->clear();

    expect($data->headingFamily)->toBe("'Cormorant Garamond', serif")
        ->and($data->bodyFamily)->toBe("'Jost', sans-serif")
        ->and($data->scriptFamily)->toBe("'Great Vibes', serif")
        // Les fichiers voyagent avec l'application : rien à chercher sur le
        // réseau au moment d'imprimer.
        ->and($data->fontFaces)->toContain('cormorant-regular.ttf')
        ->and($data->fontFaces)->toContain('cormorant-italic.ttf')
        ->and($data->fontFaces)->toContain('great-vibes-regular.ttf')
        ->and($data->fontFaces)->not->toContain('bodoni');
});

it('retombe sur les polices d\'origine du PDF quand l\'organisateur prend celles de l\'appareil', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent();
    $admin = invitationAdmin($organization);

    $this->actingAs($admin)->patchJson("/events/{$event->id}/page", [
        'heading_font' => 'georgia',
        'body_font' => 'systeme',
        'script_font' => 'aucune',
        'blocks' => [],
    ])->assertOk();

    app(CurrentOrganization::class)->set($organization);
    $data = app(BuildInvitationPdf::class)->data($event->refresh());
    app(CurrentOrganization::class)->clear();

    expect($data->headingFamily)->toBe('serif')
        ->and($data->bodyFamily)->toBe('sans-serif')
        // « Aucune » : le mot manuscrit prend la police des titres.
        ->and($data->scriptFamily)->toBe('serif')
        ->and($data->fontFaces)->toBe('');
});

it('propose les trois réponses sur une invitation personnelle', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Wedding]);

    $this->get("/r/{$organization->slug}/{$event->slug}")
        ->assertOk()
        // Les trois réponses sont formulées à la première personne : c'est
        // l'invité qui parle.
        ->assertSee('Je confirme ma présence')
        ->assertSee('Je ne pourrai pas répondre présent')
        ->assertSee('Je vais confirmer plus tard');
});

it('ne propose pas de refus sur une inscription professionnelle', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([], ['type' => EventType::Conference]);

    $html = $this->get("/r/{$organization->slug}/{$event->slug}")->assertOk()->getContent();

    expect($html)->toContain('Je confirme ma présence')
        ->and($html)->toContain('Je vais confirmer plus tard')
        // Sans refus accepté par le formulaire, le bouton mènerait à une
        // inscription : tout le contraire de ce qu'il promet.
        ->and($html)->not->toContain('Je ne pourrai pas répondre présent');
});

it('recadre les photos du faire-part au format de leur cadre', function (): void {
    $crop = app(CropImageToRatio::class);

    // Une photo portrait de 600 × 900, posée dans un cadre paysage.
    $source = imagecreatetruecolor(600, 900);
    ob_start();
    imagejpeg($source);
    $portrait = (string) ob_get_clean();
    imagedestroy($source);

    $cropped = $crop->handle($portrait, 70, 52);
    expect($cropped)->not->toBeNull();

    $image = imagecreatefromstring((string) $cropped);
    expect($image)->not->toBeFalse();
    // Le cadre impose son format : la photo est rognée, jamais étirée.
    expect(round(imagesx($image) / imagesy($image), 2))->toBe(round(70 / 52, 2))
        ->and(imagesx($image))->toBe(600);
    imagedestroy($image);

    // Un contenu qui n'est pas une image ne fait pas tomber le faire-part.
    expect($crop->handle('ceci n\'est pas une image', 70, 52))->toBeNull();
});
