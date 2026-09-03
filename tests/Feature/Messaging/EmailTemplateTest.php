<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Mail\GenericMail;
use Illuminate\Support\Facades\Mail;

it('crée un modèle d\'e-mail', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $blocks = [['type' => 'heading', 'text' => 'Bonjour {{first_name}}'], ['type' => 'divider']];
    $response = $this->actingAs($admin)->post(route('email-templates.store'), [
        'name' => 'Invitation gala',
        'subject' => 'Vous êtes invité, {{first_name}}',
        'blocks' => $blocks,
    ]);

    $template = EmailTemplate::query()->firstOrFail();
    $response->assertRedirect(route('email-templates.edit', $template));
    expect($template->name)->toBe('Invitation gala');
    expect($template->blocks)->toHaveCount(2);
});

it('modifie et supprime un modèle d\'e-mail', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->patch(route('email-templates.update', $template), [
        'name' => 'Nouveau nom',
        'subject' => 'Nouveau sujet',
        'blocks' => [['type' => 'text', 'html' => '<p>Salut</p>']],
    ])->assertRedirect(route('email-templates.edit', $template));
    expect($template->fresh()->name)->toBe('Nouveau nom');

    $this->actingAs($admin)->delete(route('email-templates.destroy', $template))->assertRedirect(route('email-templates.index'));
    expect(EmailTemplate::query()->find($template->id))->toBeNull();
});

it('refuse la gestion des modèles à un rôle sans capacité sendCommunications', function (): void {
    [, $doorStaff] = organizationWithContactRole(MembershipRole::DoorStaff);

    $this->actingAs($doorStaff)->post(route('email-templates.store'), [
        'name' => 'Invitation',
        'subject' => 'Sujet',
        'blocks' => [],
    ])->assertForbidden();
});

it('aperçoit un modèle avec les données réelles d\'un contact choisi', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = EmailTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'subject' => 'Bonjour {{first_name}}',
        'blocks' => [['type' => 'heading', 'text' => 'Salut {{first_name}} !']],
    ]);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace']);

    $response = $this->actingAs($admin)->getJson(route('email-templates.preview', $template).'?contact_id='.$contact->id);

    $response->assertOk();
    expect($response->json('subject'))->toBe('Bonjour Grace');
    expect($response->json('html'))->toContain('Salut Grace !');
});

it('envoie un e-mail de test transactionnel, sans lien de désabonnement', function (): void {
    Mail::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = EmailTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'blocks' => [['type' => 'text', 'html' => '<p>Bonjour {{first_name}}</p>']],
    ]);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace']);

    $response = $this->actingAs($admin)->postJson(route('email-templates.test-send', $template), [
        'contact_id' => $contact->id,
        'to_email' => 'organisateur@example.org',
    ]);

    $response->assertOk();
    Mail::assertSent(GenericMail::class, function (GenericMail $mail): bool {
        return $mail->hasTo('organisateur@example.org') && $mail->unsubscribeUrl === null;
    });
});

it('inclut l\'événement choisi dans l\'aperçu quand il est fourni', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['title' => 'Gala annuel']);
    $template = EmailTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'blocks' => [['type' => 'text', 'html' => '<p>Le {{event_date}}</p>']],
    ]);
    $contact = Contact::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->getJson(
        route('email-templates.preview', $template).'?contact_id='.$contact->id.'&event_id='.$event->id,
    );

    $response->assertOk();
    expect($response->json('html'))->not->toContain('date à confirmer');
});
