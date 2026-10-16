<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use Illuminate\Support\Facades\URL;

/**
 * M2.4 du cahier des charges : « Option en rupture → griser l'option avec
 * la mention "Complet" » — explicitement pas de liste d'attente au niveau
 * d'une option (voir le docblock de ReserveFieldOptionCapacity), contrairement
 * à la capacité de l'événement.
 */
it('grise l\'option de menu complète, sans empêcher l\'invité qui l\'a déjà choisie de la garder', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        [
            'key' => 'menu',
            'type' => 'meal_choice',
            'label' => 'Choix du menu',
            'is_required' => true,
            'options' => [
                ['label' => 'Poisson', 'quota' => 1],
                ['label' => 'Poulet'],
            ],
        ],
    ], ['type' => EventType::Conference]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    // Awa prend la seule part de poisson disponible.
    answerAsGuest($this, $event, $base, 'awa@example.com', ['menu' => 'poisson']);
    $this->flushSession();

    // Moussa commence une inscription : son formulaire doit déjà montrer
    // l'option grisée, avant même qu'il ne tente de la choisir.
    $this->get("{$base}/commencer");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'moussa@example.com', 'first_name' => 'Moussa', 'last_name' => 'Kane']);

    $html = $this->get("{$base}/{$token}/reponses")->assertOk()->getContent();

    expect($html)->toContain('Complet');
    // L'option grisée est bien désactivée côté serveur, pas seulement par CSS.
    preg_match('/<input[^>]*value="poisson"[^>]*>/', $html, $matches);
    expect($matches)->not->toBeEmpty();
    expect($matches[0])->toContain('disabled');

    // Si l'option est malgré tout choisie (JavaScript désactivé, requête
    // forgée...), l'inscription finale la refuse toujours proprement
    // (déjà couvert par SubmitRegistrationTest) : le grisage n'est qu'un
    // confort d'affichage, jamais la seule barrière.
});

it('ne grise jamais, à la modification, l\'option déjà détenue par l\'invité lui-même', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        [
            'key' => 'menu',
            'type' => 'meal_choice',
            'label' => 'Choix du menu',
            'is_required' => true,
            'options' => [
                ['label' => 'Poisson', 'quota' => 1],
                ['label' => 'Poulet'],
            ],
        ],
    ], ['type' => EventType::Conference, 'allow_guest_edit' => true]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    answerAsGuest($this, $event, $base, 'awa@example.com', ['menu' => 'poisson']);
    $registration = Registration::withoutGlobalScopes()->where('event_id', $event->id)->where('email', 'awa@example.com')->sole();

    $editUrl = URL::temporarySignedRoute('guest.registration.edit', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);

    // Awa modifie sa propre inscription : « Poisson » reste à elle, pas grisé,
    // même si plus aucune part n'est disponible pour quelqu'un d'autre.
    $html = $this->get($editUrl)->assertOk()->getContent();

    preg_match('/<input[^>]*value="poisson"[^>]*>/', $html, $matches);
    expect($matches)->not->toBeEmpty();
    expect($matches[0])->not->toContain('disabled');
});
