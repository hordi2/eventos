<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Support\Invitation\BuildInvitationPdf;
use App\Support\MultiTenancy\CurrentOrganization;
use Database\Seeders\DemoEventSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * L'invitation de démonstration (§10 du CLAUDE.md) doit se monter d'une
 * seule commande, sur une base vide, et donner les deux supports complets.
 */
it('compose une invitation de démonstration complète, page web et faire-part', function (): void {
    Storage::fake('public');

    $this->seed(DemoEventSeeder::class);

    $html = $this->get('/r/michel-allegria/mariage-michel-allegria')->assertOk()->getContent();

    // Couverture, L'essentiel, les six blocs composés, puis le code d'entrée
    // et la réponse : dix feuillets.
    expect(substr_count($html, 'id="feuillet-'))->toBe(10)
        ->and($html)->toContain('10 / 10')
        ->and($html)->toContain('Save the date')
        ->and($html)->toContain('Great+Vibes')
        ->and($html)->toContain('Je confirme ma présence');

    app(CurrentOrganization::class)->set(Organization::query()->where('slug', 'michel-allegria')->sole());
    $event = Event::query()->where('slug', 'mariage-michel-allegria')->sole();
    $pdf = app(BuildInvitationPdf::class)->data($event);
    app(CurrentOrganization::class)->clear();

    // Le faire-part porte les mêmes feuillets, dans le même ordre.
    expect(array_column($pdf->blocks, 'type'))
        ->toBe(['save_the_date', 'program', 'details', 'full_photo', 'gallery', 'welcome_message', 'entry_qr', 'rsvp'])
        ->and($pdf->headingFamily)->toBe("'Cormorant Garamond', serif");
});

it('se rejoue sans rien créer deux fois', function (): void {
    Storage::fake('public');

    $this->seed(DemoEventSeeder::class);
    $this->seed(DemoEventSeeder::class);

    // La sécurité au niveau des lignes masque tout hors contexte : c'est
    // l'organisation de la démonstration qui ouvre la lecture.
    app(CurrentOrganization::class)->set(Organization::query()->where('slug', 'michel-allegria')->sole());

    expect(Organization::query()->where('slug', 'michel-allegria')->count())->toBe(1)
        ->and(Event::query()->where('slug', 'mariage-michel-allegria')->count())->toBe(1)
        ->and(Page::query()->count())->toBe(1);

    app(CurrentOrganization::class)->clear();
});
