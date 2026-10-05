<?php

declare(strict_types=1);

use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\MembershipRole;
use App\Support\Assistant\AnthropicAssistant;
use App\Support\Assistant\DraftEventFromSentence;
use App\Support\Assistant\WriteMessageCopy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Assistant de création et de rédaction (D4).
 *
 * Les appels à Anthropic sont simulés : les tests n'ont jamais à sortir du
 * réseau, et ils vérifient surtout ce qui part — c'est-à-dire ce que
 * l'organisateur a écrit, et rien d'autre.
 */
beforeEach(function (): void {
    config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-sonnet-5-5']);
});

function anthropicReplies(string $text): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => $text]]]),
    ]);
}

it('monte un brouillon à partir d\'une phrase', function (): void {
    anthropicReplies(json_encode([
        'title' => 'Conférence annuelle des partenaires',
        'type' => 'conference',
        'start_at' => '2027-03-15 09:00',
        'capacity' => 300,
        'description' => 'Une journée avec nos partenaires.',
        'fields' => [
            ['label' => 'Votre entreprise', 'type' => 'short_text', 'is_required' => true],
            ['label' => 'Votre fonction', 'type' => 'short_text', 'is_required' => false],
            ['label' => 'Inventé', 'type' => 'type_inexistant', 'is_required' => false],
        ],
        'ticket_suggestions' => ['早 — tarif découverte', 'Tarif plein', 'Tarif partenaire'],
    ], JSON_UNESCAPED_UNICODE));

    $draft = app(DraftEventFromSentence::class)->handle(
        'Crée un événement pour la conférence annuelle des partenaires, 300 personnes, le 15 mars 2027 à Kinshasa.',
        'Africa/Kinshasa',
    );

    expect($draft['title'])->toBe('Conférence annuelle des partenaires')
        ->and($draft['type'])->toBe(EventType::Conference->value)
        ->and($draft['capacity'])->toBe(300)
        ->and($draft['fields'])->toHaveCount(3)
        ->and($draft['fields'][0]['key'])->toBe('votre_entreprise')
        // Un type de champ inconnu retombe sur du texte court plutôt que de
        // faire échouer la création.
        ->and($draft['fields'][2]['type'])->toBe('short_text')
        ->and($draft['ticket_suggestions'])->toHaveCount(3);

    // Seule la phrase de l'organisateur est partie.
    Http::assertSent(function ($request): bool {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'conférence annuelle des partenaires')
            && ! str_contains($body, 'example.com');
    });
});

it('ne crée jamais un événement dans le passé', function (): void {
    anthropicReplies(json_encode([
        'title' => 'Journée portes ouvertes',
        'type' => 'open_house',
        // L'assistant s'est trompé d'année.
        'start_at' => '2020-03-15 09:00',
        'fields' => [],
    ]));

    $draft = app(DraftEventFromSentence::class)->handle('Une journée portes ouvertes le 15 mars.', 'Africa/Kinshasa');

    expect(CarbonImmutable::parse($draft['start_at'], 'Africa/Kinshasa')->isFuture())->toBeTrue();
});

it('rédige un e-mail au ton demandé, sans jamais envoyer les contacts', function (): void {
    anthropicReplies(json_encode([
        'subject' => 'Vous êtes invité',
        'body' => "Bonjour :prenom,\n\nNous serions heureux de vous accueillir.",
    ], JSON_UNESCAPED_UNICODE));

    $copy = app(WriteMessageCopy::class)->email('Invite nos partenaires au gala', 'sobre', 'Gala annuel');

    expect($copy['subject'])->toBe('Vous êtes invité')
        ->and($copy['body'])->toContain(':prenom');

    Http::assertSent(function ($request): bool {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($body, 'Invite nos partenaires au gala')
            && str_contains($body, 'Gala annuel')
            && str_contains($body, 'Sobre et professionnel');
    });
});

it('dit simplement que l\'assistant n\'est pas configuré', function (): void {
    config(['services.anthropic.key' => null]);
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);

    expect(app(AnthropicAssistant::class)->isConfigured())->toBeFalse();

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->postJson('/assistant/evenement', ['sentence' => 'Une conférence le 15 mars à Kinshasa.'])
        ->assertStatus(503);
});

it('réserve la rédaction à qui peut écrire aux invités', function (): void {
    [$organization, $viewer] = organizationWithContactRole(MembershipRole::Viewer);
    anthropicReplies(json_encode(['subject' => 'Objet', 'body' => 'Corps']));

    $this->actingAs($viewer)
        ->withSession(['current_organization_id' => $organization->id])
        ->postJson('/assistant/redaction', ['brief' => 'Invite nos partenaires', 'tone' => 'sobre'])
        ->assertForbidden();
});

it('refuse une phrase trop courte', function (): void {
    [$organization, $owner] = organizationWithContactRole(MembershipRole::Owner);

    $this->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id])
        ->postJson('/assistant/evenement', ['sentence' => 'Gala'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sentence');
});
