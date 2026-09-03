<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Messaging\WhatsappProvider;

it('affiche la liste des modèles WhatsApp', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id, 'name' => 'Rappel J-1']);

    $response = $this->actingAs($admin)->get(route('whatsapp-templates.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('templates.0.name', 'Rappel J-1'));
});

it('déclare un modèle WhatsApp déjà approuvé', function (): void {
    [, $admin] = organizationWithContactRole(MembershipRole::Admin);

    $response = $this->actingAs($admin)->post(route('whatsapp-templates.store'), [
        'name' => 'Invitation gala',
        'provider_template_sid' => 'HXabc123',
        'language' => 'fr',
        'category' => 'marketing',
        'variable_mapping' => ['first_name', 'event_date'],
    ]);

    $response->assertRedirect(route('whatsapp-templates.index'));
    $template = WhatsappTemplate::query()->firstOrFail();
    expect($template->name)->toBe('Invitation gala');
    expect($template->provider_template_sid)->toBe('HXabc123');
    expect($template->variable_mapping)->toBe(['first_name', 'event_date']);
});

it('modifie et supprime un modèle WhatsApp', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->patch(route('whatsapp-templates.update', $template), [
        'name' => 'Nouveau nom',
        'provider_template_sid' => 'HXnouveau',
        'language' => 'fr',
        'category' => 'utility',
        'variable_mapping' => ['last_name'],
    ])->assertRedirect(route('whatsapp-templates.index'));
    expect($template->fresh()->name)->toBe('Nouveau nom');
    expect($template->fresh()->provider_template_sid)->toBe('HXnouveau');

    $this->actingAs($admin)->delete(route('whatsapp-templates.destroy', $template))->assertRedirect(route('whatsapp-templates.index'));
    expect(WhatsappTemplate::query()->find($template->id))->toBeNull();
});

it('refuse la gestion des modèles WhatsApp à un rôle sans capacité sendCommunications', function (): void {
    [, $doorStaff] = organizationWithContactRole(MembershipRole::DoorStaff);

    $this->actingAs($doorStaff)->post(route('whatsapp-templates.store'), [
        'name' => 'Invitation',
        'provider_template_sid' => 'HXabc',
        'language' => 'fr',
        'variable_mapping' => [],
    ])->assertForbidden();
});

it('aperçoit les variables numérotées résolues pour un contact choisi', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'variable_mapping' => ['first_name', 'last_name'],
    ]);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace', 'last_name' => 'Mbuyi']);

    $response = $this->actingAs($admin)->getJson(route('whatsapp-templates.preview', $template).'?contact_id='.$contact->id);

    $response->assertOk();
    expect($response->json('content_variables'))->toBe(['1' => 'Grace', '2' => 'Mbuyi']);
});

it('inclut l\'événement choisi dans l\'aperçu quand il est fourni', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'variable_mapping' => ['event_date'],
    ]);
    $contact = Contact::factory()->for($organization)->create();

    $response = $this->actingAs($admin)->getJson(
        route('whatsapp-templates.preview', $template).'?contact_id='.$contact->id.'&event_id='.$event->id,
    );

    $response->assertOk();
    expect($response->json('content_variables.1'))->not->toBe('date à confirmer');
});

it('envoie un message WhatsApp de test au numéro indiqué', function (): void {
    $fake = new class implements WhatsappProvider
    {
        /** @var list<array{to: string, contentSid: string}> */
        public array $sent = [];

        public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string
        {
            $this->sent[] = ['to' => $toPhoneE164, 'contentSid' => $contentSid];

            return 'SM'.bin2hex(random_bytes(16));
        }
    };
    $this->app->bind(WhatsappProvider::class, fn () => $fake);

    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $template = WhatsappTemplate::factory()->for($organization)->create([
        'created_by' => $admin->id,
        'provider_template_sid' => 'HXtest',
        'variable_mapping' => ['first_name'],
    ]);
    $contact = Contact::factory()->for($organization)->create(['first_name' => 'Grace']);

    $response = $this->actingAs($admin)->postJson(route('whatsapp-templates.test-send', $template), [
        'contact_id' => $contact->id,
        'to_phone_e164' => '+243899999999',
    ]);

    $response->assertOk();
    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0]['to'])->toBe('+243899999999');
    expect($fake->sent[0]['contentSid'])->toBe('HXtest');
});
