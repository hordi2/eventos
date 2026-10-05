<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FormVersion;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Form\Models\TravelMode;
use App\Domain\Organization\Models\MembershipRole;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Sustainability\CarpoolBoard;
use App\Support\Sustainability\GetEventCarbonFootprint;
use Illuminate\Support\Facades\URL;

/**
 * Empreinte carbone d'un événement (D12) : déplacements déclarés, repas,
 * impressions, covoiturage et rapport RSE.
 *
 * @return array{organization: object, event: object, me: Registration, admin: User}
 */
function carbonEvent(): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent(eventOverrides: [
        'type' => EventType::Conference,
        'has_carbon_report' => true,
        'meals_served' => 100,
        'printed_pages' => 400,
    ]);

    app(CurrentOrganization::class)->set($organization);
    $version = FormVersion::query()->where('organization_id', $organization->id)->sole();

    $make = fn (string $first, ?TravelMode $mode, ?int $km, ?string $role): Registration => Registration::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'form_version_id' => $version->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => $first,
        'travel_mode' => $mode,
        'travel_distance_km' => $km,
        'travel_city' => $mode === null ? null : 'Gombe',
        'carpool_role' => $role,
        'travel_declared_at' => $mode === null ? null : now(),
    ]);

    $me = $make('Awa', TravelMode::Car, 20, 'offers');
    $make('Jean', TravelMode::PublicTransport, 10, null);
    $make('Lucie', TravelMode::Car, 30, 'seeks');
    // Celui qui n'a rien déclaré ne compte pas dans le total.
    $make('Paul', null, null, null);

    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'me' => $me, 'admin' => $admin];
}

it('additionne déplacements, repas et impressions', function (): void {
    ['organization' => $organization, 'event' => $event] = carbonEvent();

    app(CurrentOrganization::class)->set($organization);
    $footprint = app(GetEventCarbonFootprint::class)->handle($event);
    app(CurrentOrganization::class)->clear();

    // Aller-retour : 20 km déclarés valent 40 km parcourus.
    $travel = (40 * 0.192) + (20 * 0.03) + (60 * 0.192);

    expect($footprint->travelKilograms)->toBe(round($travel, 1))
        ->and($footprint->mealKilograms)->toBe(200.0)
        ->and($footprint->printKilograms)->toBe(2.0)
        ->and($footprint->totalKilograms)->toBe(round($travel + 202.0, 1))
        // Quatre inscrits, trois déclarations.
        ->and($footprint->attendeeCount)->toBe(4)
        ->and($footprint->declaredCount)->toBe(3)
        ->and($footprint->declarationRate())->toBe(75);
});

it('chiffre ce que le covoiturage ferait gagner', function (): void {
    ['organization' => $organization, 'event' => $event] = carbonEvent();

    app(CurrentOrganization::class)->set($organization);
    $service = app(GetEventCarbonFootprint::class);
    $footprint = $service->handle($event);
    app(CurrentOrganization::class)->clear();

    // 100 km en voiture seul contre les mêmes partagés.
    expect($service->carpoolSaving($footprint))->toBe(round(100 * 0.192 - 100 * 0.064, 1));
});

it('ne publie au covoiturage que ceux qui l\'ont demandé', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me] = carbonEvent();

    app(CurrentOrganization::class)->set($organization);
    $board = app(CarpoolBoard::class)->handle($event, $me);
    app(CurrentOrganization::class)->clear();

    // Awa propose, mais c'est elle qui regarde : elle ne se voit pas.
    expect($board['offers'])->toBe([])
        ->and($board['seekers'])->toHaveCount(1)
        ->and($board['seekers'][0]['name'])->toBe('Lucie')
        ->and($board['seekers'][0]['city'])->toBe('Gombe')
        ->and($board['alone'])->toBe(2);
});

it('laisse le participant déclarer son déplacement', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me] = carbonEvent();

    $url = URL::temporarySignedRoute('guest.registration.travel', now()->addDay(), [
        $organization->slug, $event->slug, $me->id,
    ]);

    $this->get($url)->assertOk()->assertSee('Mon déplacement');

    $this->post($url, [
        'travel_mode' => TravelMode::Bicycle->value,
        'travel_distance_km' => 5,
        'travel_city' => 'Lingwala',
        'carpool_role' => null,
    ])->assertRedirect()->assertSessionHas('status', 'travel-declared');

    app(CurrentOrganization::class)->set($organization);
    expect($me->refresh()->travel_mode)->toBe(TravelMode::Bicycle)
        ->and($me->travel_distance_km)->toBe(5)
        ->and($me->carpool_role)->toBeNull()
        // Le vélo ne pèse rien.
        ->and(app(GetEventCarbonFootprint::class)->handle($event)->travelKilograms)->toBe(round((20 * 0.03) + (60 * 0.192), 1));
    app(CurrentOrganization::class)->clear();
});

it('ferme la déclaration quand l\'organisateur ne mesure pas l\'empreinte', function (): void {
    ['organization' => $organization, 'event' => $event, 'me' => $me] = carbonEvent();

    app(CurrentOrganization::class)->set($organization);
    $event->update(['has_carbon_report' => false]);
    app(CurrentOrganization::class)->clear();

    $url = URL::temporarySignedRoute('guest.registration.travel', now()->addDay(), [
        $organization->slug, $event->slug, $me->id,
    ]);

    $this->get($url)->assertNotFound();
});

it('édite le rapport de durabilité et retient ce que l\'organisateur saisit', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = carbonEvent();
    $session = ['current_organization_id' => $organization->id];

    $this->actingAs($admin)->withSession($session)->get("/events/{$event->id}/empreinte")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/CarbonFootprint')
            ->where('footprint.declarationRate', 75));

    $this->actingAs($admin)->withSession($session)
        ->patch("/events/{$event->id}/empreinte", ['meals_served' => 120, 'printed_pages' => 0])
        ->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    expect($event->refresh()->meals_served)->toBe(120)
        ->and(app(GetEventCarbonFootprint::class)->handle($event)->mealKilograms)->toBe(240.0);
    app(CurrentOrganization::class)->clear();

    $pdf = $this->actingAs($admin)->withSession($session)->get("/events/{$event->id}/empreinte.pdf")->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');
});
