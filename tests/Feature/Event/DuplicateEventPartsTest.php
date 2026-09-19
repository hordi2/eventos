<?php

declare(strict_types=1);

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Events\EventDuplicated;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\CreateForm;
use App\Domain\Form\Actions\PublishFormVersion;
use App\Domain\Form\Actions\ReviseForm;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Models\FormVersionStatus;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageAutomation;
use App\Domain\Messaging\Models\MessageAutomationStatus;
use App\Domain\Messaging\Models\MessageAutomationType;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Domain\Page\Models\Page;
use App\Domain\Ticketing\Models\PriceTier;
use App\Domain\Ticketing\Models\TicketType;
use App\Jobs\SendEmailAutomationJob;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un événement complet de l'an dernier : une session, un formulaire publié
 * avec une modification en cours, une page avec bannière, un billet, deux
 * invités (dont un effacé) et des envois programmés.
 *
 * @return array{0: Event, 1: User, 2: Organization}
 */
function makeFullEventToDuplicate(): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $source = Event::factory()->for($organization)->published()->create([
        'title' => 'Gala 2025',
        'start_at' => CarbonImmutable::parse('2025-12-06T18:00:00Z'),
        'end_at' => CarbonImmutable::parse('2025-12-06T23:00:00Z'),
        'password_hash' => bcrypt('secret-gala'),
        'registration_closed_message' => 'Merci, la salle est pleine.',
    ]);
    $workshop = Event::factory()->for($organization)->subEventOf($source)->create([
        'title' => 'Atelier',
        'start_at' => CarbonImmutable::parse('2025-12-06T16:00:00Z'),
        'end_at' => CarbonImmutable::parse('2025-12-06T17:00:00Z'),
    ]);

    $form = app(CreateForm::class)->handle($organization, $source->id, $admin, [
        'name' => 'Inscription',
        'fields' => [
            ['key' => 'diner', 'type' => 'yes_no', 'label' => 'Dînerez-vous ?'],
            ['key' => 'menu', 'type' => 'meal_choice', 'label' => 'Menu', 'options' => [['label' => 'Poisson', 'quota' => 10], ['label' => 'Poulet']]],
            ['key' => 'sessions', 'type' => 'sub_events', 'label' => 'Sessions', 'config' => ['sub_events' => [['id' => $workshop->id, 'title' => 'Atelier']]]],
        ],
        'rules' => [[
            'target_field_key' => 'menu',
            'action' => 'show',
            'condition_group' => ['combinator' => 'and', 'conditions' => [['field_key' => 'diner', 'operator' => 'is', 'value' => true]]],
        ]],
    ]);
    $form->update(['settings' => ['theme' => ['header_image_path' => 'organization-images/gala.jpg']]]);
    app(PublishFormVersion::class)->handle($form, $admin);
    app(ReviseForm::class)->handle($form->fresh(), $admin, [['key' => 'diner', 'type' => 'yes_no', 'label' => 'Dînerez-vous avec nous ?']], []);

    Storage::disk('public')->put('page-banners/gala.jpg', 'bannière');
    Page::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $source->id,
        'banner_path' => 'page-banners/gala.jpg',
        'program_items' => [['time' => '18h', 'title' => 'Accueil', 'description' => null]],
    ]);

    $ticketType = TicketType::factory()->for($organization)->create(['event_id' => $source->id, 'name' => 'Entrée']);
    PriceTier::factory()->for($organization)->create([
        'ticket_type_id' => $ticketType->id,
        'name' => 'Prévente',
        'starts_at' => CarbonImmutable::parse('2025-10-01T00:00:00Z'),
        'ends_at' => CarbonImmutable::parse('2025-11-30T23:00:00Z'),
    ]);

    $guest = Contact::factory()->create(['organization_id' => $organization->id]);
    $erased = Contact::factory()->create(['organization_id' => $organization->id]);
    foreach ([$guest, $erased] as $contact) {
        EventInvitee::factory()->create([
            'organization_id' => $organization->id,
            'event_id' => $source->id,
            'contact_id' => $contact->id,
            'invitation_token' => Str::random(40),
            'group_key' => 'famille-mbala',
            'companions_allowed' => 2,
            'last_invited_at' => CarbonImmutable::parse('2025-11-01T10:00:00Z'),
        ]);
    }
    $erased->delete();

    $template = EmailTemplate::factory()->create(['organization_id' => $organization->id]);
    $automation = fn (MessageAutomationType $type, ?string $at, MessageAutomationStatus $status): MessageAutomation => MessageAutomation::factory()->create([
        'organization_id' => $organization->id,
        'event_id' => $source->id,
        'email_template_id' => $template->id,
        'created_by' => $admin->id,
        'type' => $type,
        'scheduled_at' => $at === null ? null : CarbonImmutable::parse($at),
        'status' => $status,
    ]);
    $automation(MessageAutomationType::Confirmation, null, MessageAutomationStatus::Active);
    $automation(MessageAutomationType::Invitation, '2025-11-06T09:00:00Z', MessageAutomationStatus::Sent);
    $automation(MessageAutomationType::ReminderJ1, '2025-12-05T09:00:00Z', MessageAutomationStatus::Cancelled);

    return [$source, $admin, $organization];
}

it('reprend formulaires, page, billets, invités et messages, dates décalées', function (): void {
    Storage::fake('public');
    Queue::fake();
    CarbonImmutable::setTestNow('2026-09-19T10:00:00Z');
    [$source, $admin, $organization] = makeFullEventToDuplicate();

    // Un an plus tard, jour pour jour.
    $this->actingAs($admin)->post("/events/{$source->id}/duplicate", [
        'new_start_at' => '2026-12-06T18:00',
        'parts' => ['forms', 'page', 'tickets', 'guest_list', 'messages'],
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    $copy = Event::query()->whereNull('parent_event_id')->whereKeyNot($source->id)->sole();
    $workshopCopy = Event::query()->where('parent_event_id', $copy->id)->sole();
    $offset = (int) $source->start_at->diffInSeconds($copy->start_at);

    expect($copy->password_hash)->toBe($source->password_hash)
        ->and($copy->registration_closed_message)->toBe('Merci, la salle est pleine.');

    // Formulaire : la version publiée arrive publiée, la modification en cours en brouillon.
    $form = Form::query()->where('event_id', $copy->id)->with('currentVersion.fields.options')->sole();
    expect($form->is_default)->toBeTrue()
        ->and($form->slug)->toBe('inscription')
        ->and($form->settings['theme']['header_image_path'])->toBe('organization-images/gala.jpg')
        ->and($form->currentVersion->version_number)->toBe(1)
        ->and($form->latestVersion()->status)->toBe(FormVersionStatus::Draft)
        ->and($form->latestVersion()->version_number)->toBe(2);
    $fields = $form->currentVersion->fields->keyBy('key');
    expect($fields['menu']->options->pluck('quota', 'label')->all())->toBe(['Poisson' => 10, 'Poulet' => null])
        ->and($fields['sessions']->config['sub_events'])->toBe([['id' => $workshopCopy->id, 'title' => 'Atelier']])
        ->and($form->currentVersion->conditionalRules()->sole()->target_field_id)->toBe($fields['menu']->id);

    // Page : même contenu, bannière dupliquée pour ne jamais partager le fichier.
    $page = Page::query()->where('event_id', $copy->id)->sole();
    expect($page->program_items[0]['title'])->toBe('Accueil')
        ->and($page->banner_path)->not->toBe('page-banners/gala.jpg');
    Storage::disk('public')->assertExists($page->banner_path);

    // Billets : paliers de vente décalés du même écart que l'événement.
    $tier = PriceTier::query()->whereIn('ticket_type_id', TicketType::query()->where('event_id', $copy->id)->select('id'))->sole();
    expect($tier->name)->toBe('Prévente')
        ->and($tier->starts_at->equalTo(CarbonImmutable::parse('2025-10-01T00:00:00Z')->addSeconds($offset)))->toBeTrue();

    // Invités : le contact effacé ne revient pas, les liens sont neufs, l'historique ne suit pas.
    $invitees = EventInvitee::query()->where('event_id', $copy->id)->get();
    $original = EventInvitee::query()->where('event_id', $source->id)->first();
    expect($invitees)->toHaveCount(1)
        ->and($invitees[0]->group_key)->toBe('famille-mbala')
        ->and($invitees[0]->companions_allowed)->toBe(2)
        ->and($invitees[0]->invitation_token)->not->toBe($original->invitation_token)
        ->and($invitees[0]->last_invited_at)->toBeNull();

    // Messages : l'invitation déjà partie est reprogrammée un an plus tard, l'annulé ne revient pas.
    $automations = MessageAutomation::query()->where('event_id', $copy->id)->get()->keyBy(fn (MessageAutomation $automation): string => $automation->type->value);
    expect($automations->keys()->sort()->values()->all())->toBe(['confirmation', 'invitation'])
        ->and($automations['confirmation']->status)->toBe(MessageAutomationStatus::Active)
        ->and($automations['invitation']->status)->toBe(MessageAutomationStatus::Scheduled)
        ->and($automations['invitation']->scheduled_at->equalTo(CarbonImmutable::parse('2025-11-06T09:00:00Z')->addSeconds($offset)))->toBeTrue();
    Queue::assertPushed(SendEmailAutomationJob::class, 1);
});

it('ne reprend que ce que l\'organisateur a coché', function (): void {
    Storage::fake('public');
    Queue::fake();
    CarbonImmutable::setTestNow('2026-09-19T10:00:00Z');
    [$source, $admin, $organization] = makeFullEventToDuplicate();

    $this->actingAs($admin)->post("/events/{$source->id}/duplicate", [
        'new_start_at' => '2026-12-06T18:00',
        'parts' => ['page'],
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    $copy = Event::query()->whereNull('parent_event_id')->whereKeyNot($source->id)->sole();

    expect(Page::query()->where('event_id', $copy->id)->exists())->toBeTrue()
        ->and(Form::query()->where('event_id', $copy->id)->exists())->toBeFalse()
        ->and(TicketType::query()->where('event_id', $copy->id)->exists())->toBeFalse()
        ->and(EventInvitee::query()->where('event_id', $copy->id)->exists())->toBeFalse()
        ->and(MessageAutomation::query()->where('event_id', $copy->id)->exists())->toBeFalse();
});

it('ne programme pas un envoi dont la date décalée est déjà passée', function (): void {
    Storage::fake('public');
    Queue::fake();
    CarbonImmutable::setTestNow('2026-11-20T10:00:00Z');
    [$source, $admin, $organization] = makeFullEventToDuplicate();

    // L'invitation décalée tomberait le 6 novembre 2026 : trop tard.
    $this->actingAs($admin)->post("/events/{$source->id}/duplicate", [
        'new_start_at' => '2026-12-06T18:00',
        'parts' => ['messages'],
    ])->assertRedirect();

    app(CurrentOrganization::class)->set($organization);
    $copy = Event::query()->whereNull('parent_event_id')->whereKeyNot($source->id)->sole();
    expect(MessageAutomation::query()->where('event_id', $copy->id)->pluck('type')->all())->toBe([MessageAutomationType::Confirmation]);
    Queue::assertNothingPushed();
});

it('n\'enregistre rien si la copie d\'une partie échoue', function (): void {
    Storage::fake('public');
    Queue::fake();
    [$source, $admin, $organization] = makeFullEventToDuplicate();
    EventFacade::listen(EventDuplicated::class, fn () => throw new RuntimeException('Copie impossible'));

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($admin)->post("/events/{$source->id}/duplicate", [
        'new_start_at' => '2026-12-06T18:00',
        'parts' => ['forms', 'page'],
    ]))->toThrow(RuntimeException::class);

    app(CurrentOrganization::class)->set($organization);
    expect(Event::query()->count())->toBe(2)
        ->and(Form::query()->count())->toBe(1);
});
