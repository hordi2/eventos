<?php

declare(strict_types=1);

it('affiche chaque page statique du pied de page sans authentification', function (string $url, string $needle): void {
    $this->get($url)->assertOk()->assertSee($needle, false);
})->with([
    ['/support', "Besoin d'aide"],
    ['/community', 'Communauté'],
    ['/feedback', 'Votre avis nous intéresse'],
    ['/terms', "Conditions d'utilisation"],
    ['/privacy', 'Politique de confidentialité'],
    ['/affiliates', "Programme d'affiliation"],
]);

it('génère le tableau des traitements sur la page de confidentialité publique', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('Inscription et gestion des invités (RSVP)')
        ->assertSee('Billetterie et paiement');
});
