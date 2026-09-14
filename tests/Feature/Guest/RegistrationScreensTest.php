<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\PlanTier;
use App\Mail\OrganizerRegistrationNotificationMail;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Segments\ComputeEventSegmentContacts;
use App\Support\Segments\EventSegment;
use Illuminate\Support\Facades\Mail;

/**
 * @param  array<string, array<string, mixed>>  $settings
 */
function applyGuestFormSettings(Organization $organization, Event $event, array $settings): void
{
    app(CurrentOrganization::class)->set($organization);
    Form::query()->where('event_id', $event->id)->firstOrFail()->update(['settings' => $settings]);
    app(CurrentOrganization::class)->clear();
}

function latestGuestToken(Event $event): string
{
    return RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
}

it('ouvre le parcours sur l\'écran de bienvenue quand il est activé', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);
    applyGuestFormSettings($organization, $event, [
        'welcome' => ['enabled' => true, 'title' => 'Bienvenue au gala', 'message' => 'Merci de prendre une minute.', 'button_label' => 'Répondre'],
    ]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $start = $this->get("{$base}/commencer");
    $token = latestGuestToken($event);

    $start->assertRedirect("{$base}/{$token}/accueil");
    $this->get("{$base}/{$token}/accueil")
        ->assertOk()
        ->assertSee('Bienvenue au gala')
        ->assertSee('Merci de prendre une minute.')
        ->assertSee('Répondre')
        ->assertSee("{$base}/{$token}/identite", false);
});

it('demande si l\'invité vient quand la réponse « Je ne peux pas venir » est proposée', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);
    applyGuestFormSettings($organization, $event, ['rsvp' => ['decline_enabled' => true]]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = latestGuestToken($event);

    $this->get("{$base}/{$token}/identite")->assertSee('Je serai présent(e)')->assertSee('Je ne peux pas venir');
    $this->post("{$base}/{$token}/identite", ['email' => 'hesitant@example.com'])->assertSessionHasErrors('attending');
});

it('enregistre un refus sans prendre de place et affiche l\'écran de refus', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(
        [['key' => 'menu', 'type' => 'short_text', 'label' => 'Choix du menu', 'is_required' => true]],
        ['type' => EventType::Conference, 'capacity' => 1, 'allow_waitlist' => false],
    );
    applyGuestFormSettings($organization, $event, [
        'rsvp' => ['decline_enabled' => true],
        'decline_screen' => ['title' => 'Dommage, à une prochaine fois', 'message' => 'Merci de nous avoir prévenus.'],
    ]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = latestGuestToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'absent@example.com', 'attending' => '0'])->assertRedirect("{$base}/{$token}/reponses");
    // Le menu obligatoire s'adresse aux invités présents : un refus passe sans y répondre.
    $this->post("{$base}/{$token}/reponses", [])->assertRedirect("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/recap")->assertSee('Je ne peux pas venir')->assertSee('Envoyer ma réponse');
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    expect(Registration::withoutGlobalScopes()->where('email', 'absent@example.com')->firstOrFail()->status)->toBe(RegistrationStatus::Declined);
    $this->get("{$base}/{$token}/confirmation")
        ->assertOk()
        ->assertSee('Dommage, à une prochaine fois')
        ->assertDontSee('Modifier mon inscription')
        ->assertDontSee('Annuler mon inscription');
    Mail::assertQueued(OrganizerRegistrationNotificationMail::class, fn (OrganizerRegistrationNotificationMail $mail): bool => str_starts_with($mail->envelope()->subject, 'Réponse négative'));

    app(CurrentOrganization::class)->set($organization);
    expect(app(ComputeEventSegmentContacts::class)->query($event, EventSegment::Declines)->pluck('email')->all())->toBe(['absent@example.com']);
    app(CurrentOrganization::class)->clear();

    // L'unique place reste libre pour un invité qui vient.
    $this->get("{$base}/commencer");
    $secondToken = latestGuestToken($event);
    $this->post("{$base}/{$secondToken}/identite", ['email' => 'present@example.com', 'attending' => '1']);
    $this->post("{$base}/{$secondToken}/reponses", ['menu' => 'Poisson'])->assertRedirect("{$base}/{$secondToken}/recap");
    $this->post("{$base}/{$secondToken}/recap");

    expect(Registration::withoutGlobalScopes()->where('email', 'present@example.com')->firstOrFail()->status)->toBe(RegistrationStatus::Confirmed);
});

it('applique le thème et la confirmation personnalisée du formulaire', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: ['type' => EventType::Conference]);
    applyGuestFormSettings($organization, $event, [
        'confirmation' => ['title' => 'Rendez-vous le 3 octobre', 'message' => 'Votre place est réservée.'],
        // Une valeur non conforme enregistrée hors formulaire ne doit jamais
        // atteindre la feuille de style.
        'theme' => ['background_color' => '#f3efe6', 'accent_color' => '#0f766e', 'button_color' => '#b45309', 'heading_font' => 'grotesk', 'text_color' => 'red; } body { display:none'],
    ]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = latestGuestToken($event);

    $this->get("{$base}/{$token}/identite")
        ->assertOk()
        ->assertSee('--form-background: #f3efe6;', false)
        ->assertSee('--color-accent: #0f766e;', false)
        ->assertSee('--form-button: #b45309;', false)
        ->assertSee("--font-serif: 'Space Grotesk'", false)
        ->assertDontSee('display:none', false)
        ->assertDontSee('name="attending"', false);

    $this->post("{$base}/{$token}/identite", ['email' => 'theme@example.com']);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    $this->get("{$base}/{$token}/confirmation")
        ->assertSee('Rendez-vous le 3 octobre')
        ->assertSee('Votre place est réservée.');
});

it('valide et affiche les nouveaux types de questions dans le parcours', function (): void {
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent([
        ['key' => 'ville', 'type' => 'dropdown', 'label' => 'Ville de départ', 'options' => [
            ['value' => 'kinshasa', 'label' => 'Kinshasa'],
            ['value' => 'goma', 'label' => 'Goma'],
        ]],
        ['key' => 'adresse', 'type' => 'postal_address', 'label' => 'Adresse de livraison', 'is_required' => true],
    ], ['type' => EventType::Conference], ['plan' => PlanTier::PersonalEssential]);
    $base = "/r/{$organization->slug}/{$event->slug}";

    $this->get("{$base}/commencer");
    $token = latestGuestToken($event);
    $this->post("{$base}/{$token}/identite", ['email' => 'types@example.com']);

    $this->get("{$base}/{$token}/reponses")
        ->assertSee('<select', false)
        ->assertSee('name="adresse[city]"', false);
    $this->post("{$base}/{$token}/reponses", ['ville' => 'bukavu', 'adresse' => ['line1' => '12 avenue du Commerce']])
        ->assertSessionHasErrors(['ville', 'adresse.city']);

    $this->post("{$base}/{$token}/reponses", ['ville' => 'goma', 'adresse' => ['line1' => '12 avenue du Commerce', 'city' => 'Goma']])
        ->assertRedirect("{$base}/{$token}/recap");
    $this->get("{$base}/{$token}/recap")->assertSee('Goma')->assertSee('12 avenue du Commerce, Goma');
});
