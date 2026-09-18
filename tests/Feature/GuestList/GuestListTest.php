<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Contact\Support\SpreadsheetToCsv;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\RegistrationStatus;
use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\Gdpr\AnonymizeContact;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @return array{0: Organization, 1: User, 2: Event}
 */
function guestListFixture(bool $agreementAccepted = true): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['title' => 'Gala des partenaires']);

    if ($agreementAccepted) {
        $organization->forceFill(['sender_agreement_accepted_at' => now(), 'sender_agreement_accepted_by' => $admin->id])->save();
    }

    return [$organization, $admin, $event];
}

/**
 * @param  list<list<string|int>>  $rows  première ligne = en-têtes
 */
function guestListWorkbook(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'invites').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return new UploadedFile($path, 'invites.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('demande l\'accord d\'envoi une fois, le date, l\'attribue et le journalise', function (): void {
    [$organization, $admin, $event] = guestListFixture(agreementAccepted: false);

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")
        ->assertInertia(fn ($page) => $page->component('GuestList/Index')->where('needsAgreement', true)->where('canEdit', true));

    $this->actingAs($admin)->post('/sender-agreement')->assertRedirect();
    $acceptedAt = $organization->fresh()->sender_agreement_accepted_at;

    expect($acceptedAt)->not->toBeNull();
    expect($organization->fresh()->sender_agreement_accepted_by)->toBe($admin->id);
    expect(AuditLog::query()->where('action', 'organization.sender_agreement_accepted')->count())->toBe(1);

    // Une seconde acceptation ne change ni la date ni son auteur.
    $this->travel(1)->hour();
    $this->actingAs($admin)->post('/sender-agreement');
    expect($organization->fresh()->sender_agreement_accepted_at->equalTo($acceptedAt))->toBeTrue();

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")->assertInertia(fn ($page) => $page->where('needsAgreement', false));
});

it('laisse consulter la liste sans accord à un membre en lecture seule, qui ne peut pas l\'accepter', function (): void {
    [$organization, , $event] = guestListFixture(agreementAccepted: false);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);

    $this->actingAs($viewer)->get("/events/{$event->id}/guest-list")
        ->assertInertia(fn ($page) => $page->where('needsAgreement', false)->where('canEdit', false));
    $this->actingAs($viewer)->post('/sender-agreement')->assertForbidden();
});

it('refuse d\'ajouter ou d\'importer des invités tant que l\'accord n\'est pas accepté', function (): void {
    Storage::fake('local');
    [, $admin, $event] = guestListFixture(agreementAccepted: false);

    $this->actingAs($admin)->from("/events/{$event->id}/guest-list")
        ->post("/events/{$event->id}/guest-list", ['first_name' => 'Grace', 'last_name' => 'Mbuyi'])
        ->assertSessionHasErrors('agreement');

    $this->actingAs($admin)->from("/events/{$event->id}/guest-list/import")
        ->post("/events/{$event->id}/guest-list/import", ['file' => guestListWorkbook([['Prénom', 'Nom'], ['Grace', 'Mbuyi']])])
        ->assertSessionHasErrors('agreement');

    expect(EventInvitee::query()->count())->toBe(0);
});

it('ajoute un invité à la main, avec groupe, accompagnants, copie et tags', function (): void {
    [$organization, $admin, $event] = guestListFixture();

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", [
        'first_name' => 'Grace',
        'last_name' => 'Mbuyi',
        'email' => 'Grace.Mbuyi@Exemple.cd',
        'phone' => '+243 81 234 5678',
        'group_key' => 'Famille Mbuyi',
        'companions' => 'illimité',
        'cc_email' => 'Assistante@Exemple.cd',
        'tags' => ['VIP', 'Dîner'],
    ])->assertRedirect()->assertSessionHas('status', 'invitee-added');

    $invitee = EventInvitee::query()->with('contact.tags')->sole();
    expect($invitee->event_id)->toBe($event->id);
    expect($invitee->group_key)->toBe('Famille Mbuyi');
    expect($invitee->companions_allowed)->toBeNull();
    expect($invitee->cc_email)->toBe('assistante@exemple.cd');
    expect($invitee->contact->email)->toBe('grace.mbuyi@exemple.cd');
    expect($invitee->contact->phone_e164)->toBe('+243812345678');
    expect($invitee->contact->tags->pluck('name')->sort()->values()->all())->toBe(['Dîner', 'VIP']);
});

it('reprend un contact connu par son e-mail et met l\'invité à jour au lieu de le dupliquer', function (): void {
    [$organization, $admin, $event] = guestListFixture();
    $connu = Contact::factory()->for($organization)->create(['first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@exemple.sn']);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['first_name' => 'A.', 'last_name' => 'D.', 'email' => 'awa@exemple.sn', 'companions' => '1']);
    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['email' => 'awa@exemple.sn', 'companions' => '2']);

    expect(Contact::query()->count())->toBe(1);
    expect($connu->fresh()->first_name)->toBe('Awa');
    expect(EventInvitee::query()->sole()->companions_allowed)->toBe(2);
});

it('exige un nom complet ou un e-mail, et refuse un nombre d\'accompagnants ou un numéro invalides', function (): void {
    [, $admin, $event] = guestListFixture();

    $this->actingAs($admin)->from("/events/{$event->id}/guest-list")
        ->post("/events/{$event->id}/guest-list", ['first_name' => 'Grace', 'companions' => '25', 'phone' => '12'])
        ->assertSessionHasErrors(['first_name', 'companions', 'phone']);

    expect(EventInvitee::query()->count())->toBe(0);
});

it('modifie un invité et refuse de lui donner l\'e-mail d\'un autre contact', function (): void {
    [$organization, $admin, $event] = guestListFixture();
    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@exemple.cd']);
    Contact::factory()->for($organization)->create(['email' => 'paul@exemple.cd']);
    $invitee = EventInvitee::query()->sole();

    $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/{$invitee->id}", [
        'first_name' => 'Grâce', 'last_name' => 'Mbuyi', 'email' => 'grace@exemple.cd', 'group_key' => '7', 'companions' => '3', 'tags' => ['Presse'],
    ])->assertSessionHas('status', 'invitee-updated');

    $fresh = $invitee->fresh(['contact.tags']);
    expect($fresh->contact->first_name)->toBe('Grâce');
    expect($fresh->group_key)->toBe('7');
    expect($fresh->companions_allowed)->toBe(3);
    expect($fresh->contact->tags->pluck('name')->all())->toBe(['Presse']);

    $this->actingAs($admin)->from("/events/{$event->id}/guest-list")
        ->patch("/events/{$event->id}/guest-list/{$invitee->id}", ['first_name' => 'Grâce', 'last_name' => 'Mbuyi', 'email' => 'paul@exemple.cd'])
        ->assertSessionHasErrors('email');
});

it('retire un invité de la liste sans supprimer son contact', function (): void {
    [, $admin, $event] = guestListFixture();
    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['first_name' => 'Grace', 'last_name' => 'Mbuyi']);
    $invitee = EventInvitee::query()->sole();

    $this->actingAs($admin)->delete("/events/{$event->id}/guest-list/{$invitee->id}")->assertSessionHas('status', 'invitee-removed');

    expect(EventInvitee::query()->count())->toBe(0);
    expect(EventInvitee::withTrashed()->find($invitee->id)->trashed())->toBeTrue();
    expect(Contact::query()->count())->toBe(1);
});

it('liste les invités par groupe avec leur réponse, cherche, et compte', function (): void {
    [$organization, $admin, $event] = guestListFixture();
    foreach ([['Kyle', 'Krane', '1'], ['Tasha', 'Krane', '1'], ['Allie', 'Prakash', null]] as [$first, $last, $group]) {
        $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['first_name' => $first, 'last_name' => $last, 'email' => mb_strtolower($first).'@exemple.com', 'group_key' => $group]);
    }
    registerContactForEvent($organization, $event, Contact::query()->where('email', 'tasha@exemple.com')->sole(), RegistrationStatus::Confirmed);

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")->assertInertia(fn ($page) => $page
        ->where('stats', ['invitees' => 3, 'groups' => 1, 'responded' => 1])
        ->where('invitees.data.0.groupKey', '1')
        ->where('invitees.data.1.response.label', 'Confirmé')
        ->where('invitees.data.2.fullName', 'Allie Prakash')
        ->where('invitees.data.2.response', null)
        ->where('groups', ['1']));

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list?q=prak")->assertInertia(fn ($page) => $page
        ->has('invitees.data', 1)
        ->where('invitees.data.0.lastName', 'Prakash'));
});

it('fournit un modèle Excel dont les colonnes sont reconnues telles quelles', function (): void {
    [, $admin, $event] = guestListFixture();

    $response = $this->actingAs($admin)->get("/events/{$event->id}/guest-list/template")->assertOk();
    $csv = app(SpreadsheetToCsv::class)->handle($response->getFile()->getPathname());

    expect(trim($csv))->toBe('Prénom,Nom,E-mail,Téléphone,Groupe,"Accompagnants autorisés",Tags,"E-mail en copie"');
});

it('importe un classeur Excel dans la liste d\'invités, puis le réimporte sans doublon', function (): void {
    Storage::fake('local');
    [$organization, $admin, $event] = guestListFixture();
    // En-têtes du modèle RSVPify, pour une migration.
    $classeur = fn (): UploadedFile => guestListWorkbook([
        ['FIRST NAME', 'LAST NAME', 'EMAIL ADDRESS', 'GROUP ID', "ADDITIONAL GUEST(S) ALLOWED (+1's)", 'TAGS', 'CC EMAIL RECIPIENT'],
        ['Kyle', 'Krane', 'kyle.krane@mail.com', 1, 1, 'VIP, Brunch', ''],
        ['Tasha', 'Krane', 'tasha.krane@mail.com', 1, '', '', ''],
        ['Tina', 'Faller', '', '', 'Unlimited', '', 'assistant@mail.com'],
        ['Karen', 'Altio', '', 2, 25, '', ''],
    ]);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/import", ['file' => $classeur()])->assertRedirect();
    $import = ContactImport::query()->sole();
    expect($import->event_id)->toBe($event->id);
    // jsonb réordonne les clés : on compare les paires, pas leur ordre.
    expect($import->column_mapping)->toEqual([
        'FIRST NAME' => 'first_name',
        'LAST NAME' => 'last_name',
        'EMAIL ADDRESS' => 'email',
        'GROUP ID' => 'group_key',
        "ADDITIONAL GUEST(S) ALLOWED (+1's)" => 'companions_allowed',
        'TAGS' => 'tags',
        'CC EMAIL RECIPIENT' => 'cc_email',
    ]);

    $this->actingAs($admin)->get("/contact-imports/{$import->id}/mapping")->assertInertia(fn ($page) => $page
        ->where('event.id', $event->id)
        ->has('mappableFields.companions_allowed'));

    $this->actingAs($admin)->post("/contact-imports/{$import->id}/mapping", ['mapping' => $import->column_mapping, 'duplicate_strategy' => 'merge']);

    $invites = EventInvitee::query()->with('contact.tags')->get()->keyBy(fn (EventInvitee $invitee): string => $invitee->contact->first_name);
    expect($invites)->toHaveCount(3);
    expect($invites['Kyle']->group_key)->toBe('1');
    expect($invites['Kyle']->companions_allowed)->toBe(1);
    expect($invites['Kyle']->contact->tags->pluck('name')->sort()->values()->all())->toBe(['Brunch', 'VIP']);
    expect($invites['Tasha']->companions_allowed)->toBe(0);
    expect($invites['Tina']->companions_allowed)->toBeNull();
    expect($invites['Tina']->cc_email)->toBe('assistant@mail.com');
    // 25 accompagnants dépasse la limite : ligne refusée, aucun contact créé.
    expect(Contact::query()->where('last_name', 'Altio')->exists())->toBeFalse();
    expect($import->fresh()->rejected_count)->toBe(1);

    $this->actingAs($admin)->post("/events/{$event->id}/guest-list/import", ['file' => $classeur()]);
    $second = ContactImport::query()->latest('id')->first();
    $this->actingAs($admin)->post("/contact-imports/{$second->id}/mapping", ['mapping' => $second->column_mapping, 'duplicate_strategy' => 'merge']);

    expect(EventInvitee::query()->count())->toBe(3);
    expect(Contact::query()->where('last_name', 'Krane')->count())->toBe(2);
});

it('ne laisse pas modifier l\'invité d\'une autre organisation', function (): void {
    [, $admin, $event] = guestListFixture();
    [$autre, $autreAdmin, $autreEvent] = guestListFixture();
    $this->actingAs($autreAdmin)->post("/events/{$autreEvent->id}/guest-list", ['first_name' => 'Paul', 'last_name' => 'Kasongo']);
    app(CurrentOrganization::class)->set($autre);
    $invitee = EventInvitee::query()->sole();
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->patch("/events/{$event->id}/guest-list/{$invitee->id}", ['first_name' => 'X', 'last_name' => 'Y'])->assertNotFound();
});

it('efface la copie e-mail et retire l\'invitation à l\'effacement RGPD du contact', function (): void {
    [$organization, $admin, $event] = guestListFixture();
    $this->actingAs($admin)->post("/events/{$event->id}/guest-list", ['first_name' => 'Grace', 'last_name' => 'Mbuyi', 'email' => 'grace@exemple.cd', 'cc_email' => 'assistante@exemple.cd']);
    app(CurrentOrganization::class)->set($organization);
    $contact = Contact::query()->sole();

    app(AnonymizeContact::class)->handle($contact, $admin);

    $invitee = EventInvitee::withTrashed()->sole();
    expect($invitee->cc_email)->toBeNull();
    expect($invitee->trashed())->toBeTrue();
});

it('fait mener « Invités » et « Importation » du menu à la liste de l\'événement', function (): void {
    [, $admin, $event] = guestListFixture();

    $this->actingAs($admin)->get("/events/{$event->id}/guest-list")->assertInertia(fn ($page) => $page
        ->where('eventNav.links.guests', route('events.guest-list.index', $event->id))
        ->where('eventNav.links.import', route('events.guest-list.import', $event->id)));
});
