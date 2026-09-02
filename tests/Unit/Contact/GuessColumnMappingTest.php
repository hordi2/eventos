<?php

declare(strict_types=1);

use App\Domain\Contact\Support\GuessColumnMapping;

it('reconnaît les en-têtes français courants', function (): void {
    $mapping = app(GuessColumnMapping::class)->handle([
        'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Consentement WhatsApp', 'Colonne inconnue',
    ]);

    expect($mapping['Prénom'])->toBe('first_name');
    expect($mapping['Nom'])->toBe('last_name');
    expect($mapping['E-mail'])->toBe('email');
    expect($mapping['Téléphone'])->toBe('phone_e164');
    expect($mapping['Consentement WhatsApp'])->toBe('whatsapp_consent');
    expect($mapping['Colonne inconnue'])->toBeNull();
});

it('reconnaît les en-têtes anglais et ignore la casse', function (): void {
    $mapping = app(GuessColumnMapping::class)->handle(['FIRST NAME', 'Email', 'Company']);

    expect($mapping['FIRST NAME'])->toBe('first_name');
    expect($mapping['Email'])->toBe('email');
    expect($mapping['Company'])->toBe('company');
});

it('reconnaît un synonyme combiné à d\'autres mots dans un en-tête', function (): void {
    // Découvert en testant le mappage dans le navigateur : une comparaison
    // exacte sur toute la chaîne ratait « Foyer / groupe » (deux synonymes
    // à la fois) malgré une correspondance mot à mot évidente.
    $mapping = app(GuessColumnMapping::class)->handle(['Foyer / groupe', 'Numéro de téléphone']);

    expect($mapping['Foyer / groupe'])->toBe('household_name');
    expect($mapping['Numéro de téléphone'])->toBe('phone_e164');
});

it('ne fait pas de faux positif sur un mot qui contient un synonyme court', function (): void {
    $mapping = app(GuessColumnMapping::class)->handle(['Hôtel']);

    expect($mapping['Hôtel'])->toBeNull();
});

it('privilégie le consentement e-mail au champ e-mail lui-même', function (): void {
    // Découvert en testant dans le navigateur : « Consentement e-mail »
    // matchait email_consent trop tard, après que « mail » (issu du
    // découpage naïf de « e-mail » sur le trait d'union) ait déjà fait
    // gagner le champ email par erreur.
    $mapping = app(GuessColumnMapping::class)->handle(['Consentement e-mail', 'E-mail']);

    expect($mapping['Consentement e-mail'])->toBe('email_consent');
    expect($mapping['E-mail'])->toBe('email');
});
