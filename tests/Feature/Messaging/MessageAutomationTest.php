<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\MembershipRole;
use App\Jobs\SendEmailAutomationJob;
use App\Jobs\SendWhatsappAutomationJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

it('planifie une invitation par e-mail à l\'échéance choisie par l\'organisateur', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    $scheduledAt = now()->addDays(2)->toIso8601String();

    $response = $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'invitation',
        'scheduled_at' => $scheduledAt,
    ]);

    $response->assertRedirect(route('events.automations.index', $event));
    $automation = MessageAutomation::query()->firstOrFail();
    expect($automation->status)->toBe(MessageAutomationStatus::Scheduled);
    expect($automation->scheduled_at->toIso8601String())->toBe(now()->parse($scheduledAt)->toIso8601String());

    Queue::assertPushed(SendEmailAutomationJob::class, fn (SendEmailAutomationJob $job): bool => $job->automationId === $automation->id && $job->delay !== null);
});

it('planifie une invitation par WhatsApp à l\'échéance choisie par l\'organisateur', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    $scheduledAt = now()->addDays(2)->toIso8601String();

    $response = $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'whatsapp',
        'whatsapp_template_id' => $whatsappTemplate->id,
        'type' => 'invitation',
        'scheduled_at' => $scheduledAt,
    ]);

    $response->assertRedirect(route('events.automations.index', $event));
    $automation = MessageAutomation::query()->firstOrFail();
    expect($automation->channel->value)->toBe('whatsapp');
    expect($automation->whatsapp_template_id)->toBe($whatsappTemplate->id);
    expect($automation->email_template_id)->toBeNull();

    Queue::assertPushed(SendWhatsappAutomationJob::class, fn (SendWhatsappAutomationJob $job): bool => $job->automationId === $automation->id && $job->delay !== null);
    Queue::assertNotPushed(SendEmailAutomationJob::class);
});

it('interprète une échéance sans fuseau (datetime-local) dans le fuseau de l\'événement, jamais celui du serveur', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    // UTC+1 toute l'année (pas d'heure d'été) : un décalage constant, facile
    // à vérifier sans dépendre de la date du test.
    $event = Event::factory()->for($organization)->create(['timezone' => 'Africa/Kinshasa']);
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    $wallClock = now()->addDays(2)->format('Y-m-d').'T14:00';

    $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'invitation',
        // Chaîne "murale", sans offset — exactement ce qu'envoie un
        // <input type="datetime-local"> du navigateur.
        'scheduled_at' => $wallClock,
    ])->assertRedirect();

    $automation = MessageAutomation::query()->firstOrFail();
    // 14h murale à Kinshasa (UTC+1) doit devenir 13h UTC en base — jamais
    // 14h UTC, ce que donnerait une interprétation dans le fuseau du
    // serveur ou d'app.timezone plutôt que celui de l'événement.
    $expectedUtc = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $wallClock, 'Africa/Kinshasa')->utc();
    expect($automation->scheduled_at->format('Y-m-d H:i'))->toBe($expectedUtc->format('Y-m-d H:i'));
});

it('calcule automatiquement l\'échéance J-7 depuis la date de l\'événement', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create(['start_at' => now()->addDays(30), 'end_at' => now()->addDays(30)->addHours(3)]);
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'reminder_j7',
    ])->assertRedirect();

    $automation = MessageAutomation::query()->firstOrFail();
    expect((int) $automation->scheduled_at->diffInDays($event->start_at))->toBe(7);
    expect($automation->segment?->value)->toBe('confirmes');
});

it('refuse un rappel J-7 dont l\'échéance calculée est déjà passée', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    // Événement dans 2 jours : J-7 tomberait il y a 5 jours.
    $event = Event::factory()->for($organization)->create(['start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addHours(3)]);
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'reminder_j7',
    ])->assertSessionHasErrors('type');

    expect(MessageAutomation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('active une confirmation automatique sans échéance ni file d\'attente', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'confirmation',
    ])->assertRedirect();

    $automation = MessageAutomation::query()->firstOrFail();
    expect($automation->status)->toBe(MessageAutomationStatus::Active);
    expect($automation->scheduled_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('refuse une deuxième confirmation active pour le même événement, même sur un autre canal', function (): void {
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    $whatsappTemplate = WhatsappTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => 'confirmation',
        'scheduled_at' => null,
        'status' => MessageAutomationStatus::Active,
    ]);

    // Même en changeant de canal : une confirmation WhatsApp en double
    // enverrait quand même deux confirmations à chaque inscription.
    $this->actingAs($admin)->post(route('events.automations.store', $event), [
        'channel' => 'whatsapp',
        'whatsapp_template_id' => $whatsappTemplate->id,
        'type' => 'confirmation',
    ])->assertSessionHasErrors('type');

    expect(MessageAutomation::query()->count())->toBe(1);
});

it('annule une automatisation planifiée', function (): void {
    Queue::fake();
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $admin->id]);
    $automation = MessageAutomation::factory()->for($organization)->create([
        'event_id' => $event->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('message-automations.cancel', $automation))
        ->assertRedirect(route('events.automations.index', $event));

    expect($automation->fresh()->status)->toBe(MessageAutomationStatus::Cancelled);
});

it('refuse la création d\'une automatisation à un rôle sans capacité sendCommunications', function (): void {
    [$organization, $doorStaff] = organizationWithContactRole(MembershipRole::DoorStaff);
    $event = Event::factory()->for($organization)->create();
    $template = EmailTemplate::factory()->for($organization)->create(['created_by' => $doorStaff->id]);

    $this->actingAs($doorStaff)->post(route('events.automations.store', $event), [
        'channel' => 'email',
        'email_template_id' => $template->id,
        'type' => 'invitation',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertForbidden();
});
