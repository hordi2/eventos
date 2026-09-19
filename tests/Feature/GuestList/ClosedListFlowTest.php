<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventAccessMode;
use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\Attendee;
use App\Domain\Form\Models\Registration;
use App\Domain\Form\Models\RegistrationDraft;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\MultiTenancy\CurrentOrganization;

beforeEach(function (): void {
    config(['services.ticket_qr.secret' => 'test-qr-secret-au-moins-256-bits-pour-hs256']);
});

/**
 * Grace et Patrick forment le groupe « 1 » (Grace peut amener une personne
 * de plus) ; Awa est seule, avec des accompagnants illimités.
 *
 * @param  array<int, array<string, mixed>>  $fields
 * @return array{organization: Organization, event: Event, base: string, admin: User, grace: EventInvitee, patrick: EventInvitee, awa: EventInvitee, fatou: EventInvitee}
 */
function closedListEvent(EventAccessMode $mode = EventAccessMode::ClosedList, array $fields = []): array
{
    ['organization' => $organization, 'event' => $event] = makeGuestReadyEvent($fields, ['type' => EventType::Conference, 'access_mode' => $mode]);

    app(CurrentOrganization::class)->set($organization);
    $admin = User::factory()->create();
    $admin->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Admin]);

    $invite = function (array $contact, array $invitation) use ($organization, $event): EventInvitee {
        return EventInvitee::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'contact_id' => Contact::factory()->for($organization)->create($contact)->id,
            ...$invitation,
        ]);
    };

    $invitees = [
        'grace' => $invite(['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@exemple.cd', 'phone_e164' => '+243812345678'], ['group_key' => '1', 'companions_allowed' => 1]),
        'patrick' => $invite(['first_name' => 'Patrick', 'last_name' => 'Mbuyi', 'email' => 'patrick@exemple.cd'], ['group_key' => '1', 'companions_allowed' => 0]),
        'awa' => $invite(['first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@exemple.sn'], ['companions_allowed' => null]),
        // Connue par son seul numéro WhatsApp.
        'fatou' => $invite(['first_name' => 'Fatou', 'last_name' => 'Sow', 'email' => null, 'phone_e164' => '+221771234567'], []),
    ];
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'base' => "/r/{$organization->slug}/{$event->slug}", 'admin' => $admin, ...$invitees];
}

function closedListDraft(Event $event): RegistrationDraft
{
    return RegistrationDraft::withoutGlobalScopes()->where('event_id', $event->id)->latest('id')->firstOrFail();
}

it('réserve l\'événement à sa liste : sans invitation, on est invité à la retrouver', function (): void {
    ['base' => $base] = closedListEvent();

    $this->get("{$base}/commencer")->assertRedirect("{$base}/retrouver-mon-invitation");
    $this->get($base)->assertRedirect("{$base}/retrouver-mon-invitation");
    $this->get("{$base}/retrouver-mon-invitation")->assertOk()->assertSee('réservé aux personnes invitées');
    // La feuille de style du thème reste servie.
    $this->get("{$base}/theme.css")->assertOk();
});

it('ouvre l\'invitation par le lien personnel et préremplit la réponse, groupe compris', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}")->assertRedirect($base);
    $this->get("{$base}/commencer")->assertRedirect();
    $draft = closedListDraft($event);
    expect($draft->event_invitee_id)->toBe($grace->id);

    $this->get("{$base}/{$draft->resume_token}/identite")
        ->assertOk()
        ->assertSee('Bonjour Grace')
        ->assertSee('value="grace@exemple.cd"', false)
        ->assertSee('Patrick Mbuyi vient')
        ->assertSee("value=\"{$patrick->id}\"", false)
        ->assertSee('1 personne de plus');
});

it('refuse un lien personnel inconnu', function (): void {
    ['base' => $base] = closedListEvent();

    $this->get("{$base}/invitation/pas-un-vrai-jeton")
        ->assertRedirect("{$base}/retrouver-mon-invitation")
        ->assertSessionHasErrors('identifier');
});

it('retrouve l\'invitation par l\'e-mail ou le numéro WhatsApp, jamais par le nom', function (string $identifier, bool $found): void {
    ['base' => $base] = closedListEvent();

    $response = $this->from("{$base}/retrouver-mon-invitation")->post("{$base}/retrouver-mon-invitation", ['identifier' => $identifier]);

    $found ? $response->assertRedirect($base)->assertSessionHasNoErrors() : $response->assertSessionHasErrors('identifier');
})->with([
    'e-mail, casse libre' => ['Grace@Exemple.CD', true],
    'numéro avec espaces' => ['+243 81 234 5678', true],
    'numéro sans +' => ['243812345678', true],
    'nom complet' => ['Grace Mbuyi', false],
    'inconnu' => ['inconnu@exemple.cd', false],
]);

it('inscrit le membre du groupe avec l\'invité, chacun relié à son contact', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base, 'admin' => $admin, 'grace' => $grace, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->post("{$base}/{$token}/identite", [
        'email' => 'grace@exemple.cd',
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        '_group_members' => [$patrick->id],
        '_companions' => [['first_name' => 'Jean', 'last_name' => 'Kabeya']],
    ])->assertRedirect("{$base}/{$token}/reponses");
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->where('event_id', $event->id)->sole();
    expect($registration->contact_id)->toBe($grace->contact_id);

    $attendees = Attendee::query()->where('registration_id', $registration->id)->orderBy('position')->get();
    expect($attendees->pluck('first_name')->all())->toBe(['Grace', 'Patrick', 'Jean']);
    expect($attendees->pluck('contact_id')->all())->toBe([$grace->contact_id, $patrick->contact_id, null]);
    app(CurrentOrganization::class)->clear();

    // Sur la liste, Patrick apparaît comme ayant répondu, avec Grace.
    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")->assertInertia(fn ($page) => $page
        ->where('stats.responded', 2)
        ->where('invitees.data', fn ($rows): bool => collect($rows)->firstWhere('firstName', 'Patrick')['response']['label'] === 'Confirmé · avec Grace Mbuyi'));
});

it('applique la limite d\'accompagnants de chaque invité, les membres du groupe à part', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace, 'awa' => $awa, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->from("{$base}/{$token}/identite")->post("{$base}/{$token}/identite", [
        'email' => 'grace@exemple.cd',
        '_group_members' => [$patrick->id],
        '_companions' => [['first_name' => 'Jean'], ['first_name' => 'Marie']],
    ])->assertSessionHasErrors('_companions');

    // Awa : « illimité », donc la limite de la plateforme.
    $this->get("{$base}/invitation/{$awa->invitation_token}");
    $this->get("{$base}/commencer");
    $awaToken = closedListDraft($event)->resume_token;

    $this->post("{$base}/{$awaToken}/identite", [
        'email' => 'awa@exemple.sn',
        '_companions' => [['first_name' => 'Fatou'], ['first_name' => 'Moussa'], ['first_name' => 'Aminata']],
    ])->assertRedirect("{$base}/{$awaToken}/reponses");
});

it('ne laisse pas répondre deux fois : un membre déjà inscrit par son groupe est reconnu', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $graceToken = closedListDraft($event)->resume_token;
    $this->post("{$base}/{$graceToken}/identite", ['email' => 'grace@exemple.cd', '_group_members' => [$patrick->id]]);
    $this->post("{$base}/{$graceToken}/reponses", []);
    $this->post("{$base}/{$graceToken}/recap");

    // Patrick, déjà venu avec Grace, ouvre son propre lien.
    $this->get("{$base}/invitation/{$patrick->invitation_token}");
    $this->get("{$base}/commencer");
    $patrickToken = closedListDraft($event)->resume_token;
    $this->post("{$base}/{$patrickToken}/identite", ['email' => 'autre-adresse@exemple.cd']);
    $this->post("{$base}/{$patrickToken}/reponses", []);
    $this->post("{$base}/{$patrickToken}/recap")->assertRedirect("{$base}/{$patrickToken}/deja-inscrit");
});

it('montre comme ayant déjà répondu un membre qui a répondu seul, et refuse de le réinscrire', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$patrick->invitation_token}");
    $this->get("{$base}/commencer");
    $patrickToken = closedListDraft($event)->resume_token;
    $this->post("{$base}/{$patrickToken}/identite", ['email' => 'patrick@exemple.cd']);
    $this->post("{$base}/{$patrickToken}/reponses", []);
    $this->post("{$base}/{$patrickToken}/recap");

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $graceToken = closedListDraft($event)->resume_token;

    $this->get("{$base}/{$graceToken}/identite")->assertSee('A déjà répondu')->assertDontSee('Patrick Mbuyi vient');
    $this->from("{$base}/{$graceToken}/identite")
        ->post("{$base}/{$graceToken}/identite", ['email' => 'grace@exemple.cd', '_group_members' => [$patrick->id]])
        ->assertSessionHasErrors('_group_members.0');
});

it('reprend un brouillon ouvert sur un autre appareil grâce à son lien de reprise', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->flushSession();

    $this->get("{$base}/{$token}/identite")->assertOk()->assertSee('Bonjour Grace');
});

it('se règle depuis la liste d\'invités, par qui peut modifier l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = closedListEvent(EventAccessMode::Public);

    $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/access", ['closed' => true])->assertSessionHas('status', 'guest-list-closed');
    expect($event->fresh()->access_mode)->toBe(EventAccessMode::ClosedList);

    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    $this->actingAs($viewer)->patch("/events/{$event->id}/guest-list/access", ['closed' => false])->assertForbidden();

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")->assertInertia(fn ($page) => $page
        ->where('accessClosed', true)
        ->where('invitees.data', fn ($rows): bool => str_contains(collect($rows)->firstWhere('firstName', 'Grace')['whatsappUrl'], 'wa.me/243812345678')
            && collect($rows)->firstWhere('firstName', 'Patrick')['whatsappUrl'] === null));
});

it('personnalise aussi la réponse d\'un événement ouvert à tous', function (): void {
    ['event' => $event, 'base' => $base, 'grace' => $grace] = closedListEvent(EventAccessMode::Public);

    $this->get("{$base}/commencer")->assertRedirect();
    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");

    $this->get("{$base}/".closedListDraft($event)->resume_token.'/identite')->assertSee('Bonjour Grace');
});

it('efface le nom d\'un membre de groupe dans l\'inscription d\'un autre invité, à son effacement RGPD', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base, 'admin' => $admin, 'grace' => $grace, 'patrick' => $patrick] = closedListEvent();

    $this->get("{$base}/invitation/{$grace->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;
    $this->post("{$base}/{$token}/identite", ['email' => 'grace@exemple.cd', '_group_members' => [$patrick->id]]);
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap");

    app(CurrentOrganization::class)->set($organization);
    app(AnonymizeContact::class)->handle(Contact::query()->findOrFail($patrick->contact_id), $admin);

    expect(Attendee::query()->where('contact_id', $patrick->contact_id)->sole()->first_name)->toBe('Invité');
});

it('laisse un invité connu par WhatsApp répondre sans adresse e-mail', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base, 'fatou' => $fatou] = closedListEvent();

    $this->get("{$base}/invitation/{$fatou->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->get("{$base}/{$token}/identite")->assertSee('Facultative si vous indiquez votre numéro WhatsApp');

    $this->post("{$base}/{$token}/identite", ['first_name' => 'Fatou', 'last_name' => 'Sow', 'phone' => '+221771234567'])
        ->assertRedirect("{$base}/{$token}/reponses");
    $this->post("{$base}/{$token}/reponses", []);
    $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");

    app(CurrentOrganization::class)->set($organization);
    $registration = Registration::query()->where('event_id', $event->id)->sole();
    expect($registration->email)->toBe('');
    expect($registration->contact_id)->toBe($fatou->contact_id);
    expect(Attendee::query()->where('registration_id', $registration->id)->sole()->email)->toBeNull();
    app(CurrentOrganization::class)->clear();

    $this->get("{$base}/{$token}/confirmation")->assertSee('avec le numéro +221771234567')->assertDontSee("avec l'adresse");
});

it('demande au moins un e-mail ou un numéro WhatsApp à un invité', function (): void {
    ['event' => $event, 'base' => $base, 'fatou' => $fatou] = closedListEvent();

    $this->get("{$base}/invitation/{$fatou->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->from("{$base}/{$token}/identite")
        ->post("{$base}/{$token}/identite", ['first_name' => 'Fatou'])
        ->assertSessionHasErrors(['email' => 'Indiquez votre adresse e-mail ou votre numéro WhatsApp.', 'phone']);
});

it('garde l\'e-mail obligatoire pour qui répond sans invitation', function (): void {
    ['event' => $event, 'base' => $base] = closedListEvent(EventAccessMode::Public);

    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;

    $this->from("{$base}/{$token}/identite")
        ->post("{$base}/{$token}/identite", ['first_name' => 'Anonyme', 'phone' => '+221771234567'])
        ->assertSessionHasErrors('email');
});

it('ne confond pas deux réponses données sans e-mail', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base, 'fatou' => $fatou] = closedListEvent();
    app(CurrentOrganization::class)->set($organization);
    $moussa = EventInvitee::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'contact_id' => Contact::factory()->for($organization)->create(['first_name' => 'Moussa', 'email' => null, 'phone_e164' => '+221781234567'])->id,
    ]);
    app(CurrentOrganization::class)->clear();

    foreach ([[$fatou, '+221771234567'], [$moussa, '+221781234567']] as [$invitee, $phone]) {
        $this->get("{$base}/invitation/{$invitee->invitation_token}");
        $this->get("{$base}/commencer");
        $token = closedListDraft($event)->resume_token;
        $this->post("{$base}/{$token}/identite", ['phone' => $phone]);
        $this->post("{$base}/{$token}/reponses", []);
        $this->post("{$base}/{$token}/recap")->assertRedirect("{$base}/{$token}/confirmation");
    }

    app(CurrentOrganization::class)->set($organization);
    expect(Registration::query()->where('event_id', $event->id)->count())->toBe(2);
});

it('refuse un don en ligne à qui répond sans e-mail', function (): void {
    ['event' => $event, 'base' => $base, 'fatou' => $fatou] = closedListEvent(fields: [[
        'key' => 'don',
        'type' => 'donation',
        'label' => 'Un don pour le projet ?',
        'config' => ['show_if' => 'always', 'currency' => 'XAF', 'amounts' => [5000], 'allow_custom' => false],
    ]]);

    $this->get("{$base}/invitation/{$fatou->invitation_token}");
    $this->get("{$base}/commencer");
    $token = closedListDraft($event)->resume_token;
    $this->post("{$base}/{$token}/identite", ['phone' => '+221771234567']);

    $this->from("{$base}/{$token}/reponses")
        ->post("{$base}/{$token}/reponses", ['don' => ['choice' => '5000']])
        ->assertSessionHasErrors('don');

    // Sans don, la réponse passe.
    $this->post("{$base}/{$token}/reponses", ['don' => ['choice' => '']])->assertRedirect("{$base}/{$token}/recap");
});

it('renouvelle un lien personnel : l\'ancien n\'ouvre plus rien, même dans un navigateur qui l\'avait suivi', function (): void {
    ['organization' => $organization, 'event' => $event, 'base' => $base, 'admin' => $admin, 'grace' => $grace] = closedListEvent();
    $ancien = $grace->invitation_token;

    // Quelqu'un a suivi le lien et commencé à répondre.
    $this->get("{$base}/invitation/{$ancien}");
    $this->get("{$base}/commencer");
    $brouillon = closedListDraft($event);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/{$grace->id}/renew-link")->assertSessionHas('status', 'invitation-link-renewed');
    auth()->logout();

    $nouveau = $grace->fresh()->invitation_token;
    expect($nouveau)->not->toBe($ancien);
    expect($brouillon->fresh()->event_invitee_id)->toBeNull();

    $this->get("{$base}/commencer")->assertRedirect("{$base}/retrouver-mon-invitation");
    $this->get("{$base}/{$brouillon->resume_token}/identite")->assertRedirect("{$base}/retrouver-mon-invitation");
    $this->get("{$base}/invitation/{$ancien}")->assertRedirect("{$base}/retrouver-mon-invitation")->assertSessionHasErrors('identifier');

    $this->get("{$base}/invitation/{$nouveau}")->assertRedirect($base);
    $this->get("{$base}/commencer")->assertRedirect();
    expect(closedListDraft($event)->event_invitee_id)->toBe($grace->id);
});

it('réserve le renouvellement d\'un lien à qui peut modifier la liste', function (): void {
    ['organization' => $organization, 'event' => $event, 'grace' => $grace] = closedListEvent();
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->post("/events/{$event->id}/guest-list/{$grace->id}/renew-link")->assertForbidden();

    expect($grace->fresh()->invitation_token)->toBe($grace->invitation_token);
});
