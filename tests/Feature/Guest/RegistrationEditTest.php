<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\FieldOption;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Form\Models\RegistrationRevision;
use App\Domain\Organization\Models\Organization;
use App\Support\Capacity\Actions\ReserveCapacity;
use App\Support\Capacity\Models\CapacityHold;
use App\Support\Capacity\Models\CapacityHoldStatus;
use App\Support\Capacity\Models\WaitlistEntry;
use App\Support\Capacity\Models\WaitlistEntryStatus;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * @return array{organization: Organization, event: Event, registration: Registration}
 */
function registerGuestFor(TestCase $test, array $fields, array $eventOverrides, array $answers = [], string $email = 'invite@gmail.com'): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent($fields, $eventOverrides);

    $test->get("/r/{$organization->slug}/{$event->slug}");
    $token = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $test->post("/r/{$organization->slug}/{$event->slug}/{$token}/identite", ['email' => $email]);
    $test->post("/r/{$organization->slug}/{$event->slug}/{$token}/reponses", $answers);
    $test->post("/r/{$organization->slug}/{$event->slug}/{$token}/recap");

    $registration = Registration::withoutGlobalScopes()->where('email', $email)->latest('id')->firstOrFail();

    return ['organization' => $organization, 'event' => $event, 'registration' => $registration];
}

function signedEditUrl($organization, $event, Registration $registration): string
{
    return URL::temporarySignedRoute('guest.registration.edit', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);
}

function signedCancelUrl($organization, $event, Registration $registration): string
{
    return URL::temporarySignedRoute('guest.registration.cancel', now()->addDay(), [$organization->slug, $event->slug, $registration->id]);
}

it('modifie une inscription et historise l\'état précédent', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = registerGuestFor($this,
        [['key' => 'allergies', 'type' => 'short_text', 'label' => 'Allergies']],
        ['allow_guest_edit' => true],
        ['allergies' => 'Aucune'],
    );

    $url = signedEditUrl($organization, $event, $registration);

    $show = $this->get($url);
    $show->assertOk();
    $show->assertSee('Aucune');

    $update = $this->post($url, [
        'email' => 'invite@gmail.com',
        'first_name' => 'Nouveau',
        'allergies' => 'Arachides',
    ]);
    $update->assertOk();

    $updated = $registration->fresh();
    expect($updated->first_name)->toBe('Nouveau');
    expect($updated->answers()->first()->value)->toBe('Arachides');

    $revision = RegistrationRevision::withoutGlobalScopes()->where('registration_id', $registration->id)->first();
    expect($revision)->not->toBeNull();
    expect($revision->snapshot['answers']['allergies'])->toBe('Aucune');
});

it('refuse une modification avec une signature invalide', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = registerGuestFor($this, [], []);

    $tampered = signedEditUrl($organization, $event, $registration).'&tampered=1';

    $this->get($tampered)->assertForbidden();
});

it('libère la place et promeut la liste d\'attente lors d\'une annulation', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = registerGuestFor($this,
        [], ['capacity' => 1, 'allow_waitlist' => true, 'allow_guest_edit' => true],
    );

    // Une deuxième personne passe en liste d'attente derrière la première.
    $this->get("/r/{$organization->slug}/{$event->slug}");
    $secondToken = RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail()->resume_token;
    $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/identite", ['email' => 'attente@gmail.com']);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/reponses", []);
    $this->post("/r/{$organization->slug}/{$event->slug}/{$secondToken}/recap");

    $waitlisted = Registration::withoutGlobalScopes()->where('email', 'attente@gmail.com')->firstOrFail();
    expect($waitlisted->status->value)->toBe('waitlisted');

    $cancelUrl = signedCancelUrl($organization, $event, $registration);
    $this->post($cancelUrl)->assertOk();

    expect($registration->fresh()->status->value)->toBe('cancelled');

    app(CurrentOrganization::class)->set($organization);
    $heldCount = CapacityHold::query()->where('holder_type', 'event')->where('holder_id', (string) $event->id)->where('status', CapacityHoldStatus::Held)->count();
    expect($heldCount)->toBe(1);
    expect(WaitlistEntry::query()->where('status', WaitlistEntryStatus::Waiting)->count())->toBe(0);
    expect($waitlisted->fresh()->status->value)->toBe('confirmed');
    app(CurrentOrganization::class)->clear();
});

it('bloque la modification quand l\'organisateur ne l\'autorise pas', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = registerGuestFor($this,
        [], ['allow_guest_edit' => false],
    );

    $this->get(signedEditUrl($organization, $event, $registration))->assertSee("n'est plus possible", false);
});

it('refuse une modification qui ferait dépasser le quota d\'une option', function (): void {
    ['organization' => $organization, 'event' => $event, 'registration' => $registration] = registerGuestFor($this,
        [[
            'key' => 'menu', 'type' => 'single_choice', 'label' => 'Menu',
            'options' => [
                ['value' => 'viande', 'label' => 'Viande'],
                ['value' => 'vegetarien', 'label' => 'Végétarien', 'quota' => 1],
            ],
        ]],
        ['allow_guest_edit' => true],
        ['menu' => 'viande'],
    );

    // Quelqu'un d'autre prend la seule place végétarienne.
    app(CurrentOrganization::class)->set($organization);
    $option = FieldOption::query()->where('value', 'vegetarien')->firstOrFail();
    app(ReserveCapacity::class)->handle($organization->id, 'form_field_option', (string) $option->id, 1, 'occupe-le-quota');
    app(CurrentOrganization::class)->clear();

    $url = signedEditUrl($organization, $event, $registration);
    $response = $this->post($url, ['email' => 'invite@gmail.com', 'menu' => 'vegetarien']);

    $response->assertSessionHasErrors('submission');
    expect($registration->fresh()->answers()->first()->value)->toBe('viande');
});
