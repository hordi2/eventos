<?php

declare(strict_types=1);

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Event\Models\Proposal;
use App\Domain\Event\Models\ProposalCall;
use App\Domain\Event\Models\ProposalStatus;
use App\Domain\Event\Models\Speaker;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\ProposalDecisionMail;
use App\Mail\ProposalReceivedMail;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * Conférence dont l'appel à contributions est ouvert, et l'administrateur
 * qui évalue les sujets reçus.
 *
 * @return array{organization: Organization, event: Event, admin: User, call: ProposalCall, public: string}
 */
function eventWithProposalCall(bool $open = true): array
{
    [$organization, $admin] = organizationWithContactRole(MembershipRole::Admin);
    $start = CarbonImmutable::parse('2026-12-10 18:00', 'UTC');

    $event = Event::factory()->for($organization)->create([
        'title' => 'Conférence Itaza',
        'timezone' => 'UTC',
        'start_at' => $start,
        'end_at' => $start->addHours(6),
        'status' => EventStatus::Published,
    ]);

    $call = ProposalCall::query()->create([
        'organization_id' => $organization->id,
        'event_id' => $event->id,
        'is_open' => $open,
        'intro' => 'Trente minutes par sujet, en français ou en lingala.',
        'closes_at' => $open ? CarbonImmutable::now()->addMonth() : null,
    ]);

    $public = "/contributions/{$organization->slug}/{$event->slug}";
    app(CurrentOrganization::class)->clear();

    return ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'call' => $call, 'public' => $public];
}

/**
 * @return array<string, mixed>
 */
function proposalPayload(array $overrides = []): array
{
    return [
        'proposer_name' => 'Moussa Kabila',
        'proposer_email' => 'moussa@example.com',
        'proposer_role' => 'Chercheur',
        'proposer_company' => 'Université de Kinshasa',
        'proposer_bio' => 'Dix ans de recherche sur les paiements mobiles.',
        'title' => 'Payer en Mobile Money sans réseau',
        'summary' => 'Comment encaisser quand la 3G tombe, et réconcilier ensuite.',
        'format' => 'talk',
        'duration_minutes' => 30,
        ...$overrides,
    ];
}

function submittedProposal(Organization $organization): Proposal
{
    app(CurrentOrganization::class)->set($organization);
    $proposal = Proposal::query()->sole();
    app(CurrentOrganization::class)->clear();

    return $proposal;
}

it('affiche l\'appel ouvert et enregistre un sujet proposé', function (): void {
    Mail::fake();
    ['organization' => $organization, 'public' => $public] = eventWithProposalCall();

    $this->get($public)
        ->assertOk()
        ->assertSee('Appel à contributions')
        ->assertSee('Trente minutes par sujet')
        ->assertSee('Proposer mon sujet');

    $this->post($public, proposalPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect($public);

    $proposal = submittedProposal($organization);
    expect($proposal->title)->toBe('Payer en Mobile Money sans réseau')
        ->and($proposal->status)->toBe(ProposalStatus::Pending)
        ->and($proposal->duration_minutes)->toBe(30);

    // Accusé de réception au proposant.
    Mail::assertQueued(ProposalReceivedMail::class, fn (ProposalReceivedMail $mail): bool => $mail->hasTo('moussa@example.com'));
});

it('refuse un sujet incomplet', function (): void {
    ['public' => $public] = eventWithProposalCall();

    $this->post($public, proposalPayload(['title' => '', 'proposer_email' => 'pas-une-adresse', 'format' => 'fumée']))
        ->assertSessionHasErrors(['title', 'proposer_email', 'format']);
});

it('cache la page tant que l\'appel n\'est pas ouvert, et la ferme passée la date limite', function (): void {
    ['organization' => $organization, 'call' => $call, 'public' => $public] = eventWithProposalCall(open: false);

    $this->get($public)->assertNotFound();

    app(CurrentOrganization::class)->set($organization);
    $call->update(['is_open' => true, 'closes_at' => CarbonImmutable::now()->subDay()]);
    app(CurrentOrganization::class)->clear();

    // La page reste lisible, mais n'accepte plus rien.
    $this->get($public)->assertOk()->assertSee("L'appel à contributions est clos");
    $this->post($public, proposalPayload())->assertGone();
});

it('retient un sujet, crée la fiche intervenant et prévient le proposant', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'public' => $public] = eventWithProposalCall();
    $this->post($public, proposalPayload());
    $proposal = submittedProposal($organization);

    $this->actingAs($admin)->post("/events/{$event->id}/appel-a-contributions/{$proposal->id}/decision", [
        'status' => 'accepted',
        'review_note' => 'Le seul sujet vraiment nouveau.',
        'decision_message' => 'Votre sujet ouvrira la matinée.',
    ])->assertSessionHas('status', 'proposal-decided');

    app(CurrentOrganization::class)->set($organization);
    $proposal->refresh();
    $speaker = Speaker::query()->sole();
    expect($proposal->status)->toBe(ProposalStatus::Accepted)
        ->and($proposal->speaker_id)->toBe($speaker->id)
        ->and($speaker->name)->toBe('Moussa Kabila')
        ->and($speaker->email)->toBe('moussa@example.com')
        ->and($speaker->portal_token)->not->toBeNull();
    app(CurrentOrganization::class)->clear();

    Mail::assertQueued(ProposalDecisionMail::class, fn (ProposalDecisionMail $mail): bool => $mail->accepted
        && $mail->note === 'Votre sujet ouvrira la matinée.'
        && $mail->hasTo('moussa@example.com'));
});

it('ne crée jamais deux fiches quand la décision est reprise', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'public' => $public] = eventWithProposalCall();
    $this->post($public, proposalPayload());
    $proposal = submittedProposal($organization);
    $decision = "/events/{$event->id}/appel-a-contributions/{$proposal->id}/decision";

    $this->actingAs($admin)->post($decision, ['status' => 'accepted']);
    $this->actingAs($admin)->post($decision, ['status' => 'rejected']);
    $this->actingAs($admin)->post($decision, ['status' => 'accepted']);

    app(CurrentOrganization::class)->set($organization);
    expect(Speaker::query()->count())->toBe(1)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::Accepted);
});

it('ne transmet jamais la note interne au proposant', function (): void {
    Mail::fake();
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'public' => $public] = eventWithProposalCall();
    $this->post($public, proposalPayload());
    $proposal = submittedProposal($organization);

    $this->actingAs($admin)->post("/events/{$event->id}/appel-a-contributions/{$proposal->id}/decision", [
        'status' => 'rejected',
        'review_note' => 'Sujet déjà traité l\'an dernier, et orateur peu à l\'aise.',
    ]);

    Mail::assertQueued(ProposalDecisionMail::class, fn (ProposalDecisionMail $mail): bool => ! $mail->accepted && $mail->note === null);
});

it('règle l\'appel depuis l\'écran de l\'organisateur, date limite dans le fuseau de l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin] = eventWithProposalCall(open: false);

    app(CurrentOrganization::class)->set($organization);
    $event->update(['timezone' => 'Africa/Kinshasa']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->post("/events/{$event->id}/appel-a-contributions", [
        'is_open' => true,
        'intro' => 'Nous cherchons des retours de terrain.',
        'closes_at' => '2026-11-30T23:00',
    ])->assertSessionHas('status', 'proposal-call-saved');

    app(CurrentOrganization::class)->set($organization);
    $call = ProposalCall::query()->sole();
    // 23h à Kinshasa (UTC+1) : 22h en base, qui stocke toujours de l'UTC.
    expect($call->is_open)->toBeTrue()
        ->and($call->closes_at->toDateTimeString())->toBe('2026-11-30 22:00:00');
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/appel-a-contributions")->assertInertia(fn ($page) => $page
        ->component('Events/Proposals')
        ->where('call.isOpen', true)
        ->where('call.closesAt', '2026-11-30T23:00'));
});

it('réserve l\'appel aux membres qui peuvent modifier l\'événement', function (): void {
    ['organization' => $organization, 'event' => $event] = eventWithProposalCall();

    app(CurrentOrganization::class)->set($organization);
    $viewer = User::factory()->create();
    $viewer->memberships()->create(['organization_id' => $organization->id, 'role' => MembershipRole::Viewer]);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($viewer)->get("/events/{$event->id}/appel-a-contributions")->assertForbidden();
    $this->actingAs($viewer)->post("/events/{$event->id}/appel-a-contributions", ['is_open' => true])->assertForbidden();
});

it('classe les sujets qui attendent une décision en tête', function (): void {
    ['organization' => $organization, 'event' => $event, 'admin' => $admin, 'call' => $call] = eventWithProposalCall();

    app(CurrentOrganization::class)->set($organization);
    $base = ['organization_id' => $organization->id, 'event_id' => $event->id, 'proposal_call_id' => $call->id];
    Proposal::factory()->accepted()->create([...$base, 'title' => 'Sujet retenu']);
    Proposal::factory()->create([...$base, 'title' => 'Sujet en attente']);
    app(CurrentOrganization::class)->clear();

    $this->actingAs($admin)->get("/events/{$event->id}/appel-a-contributions")->assertInertia(fn ($page) => $page
        ->where('proposals.0.title', 'Sujet en attente')
        ->where('proposals.1.title', 'Sujet retenu'));
});
