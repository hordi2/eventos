<?php

declare(strict_types=1);

namespace App\Support\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * L'assistant de rédaction et de création (D4), adossé à l'API d'Anthropic.
 *
 * Règle tenue partout dans cette classe et chez ceux qui l'appellent : seul
 * part ce que l'organisateur a écrit lui-même — sa phrase, son brief, le
 * titre de son événement. Jamais ses invités, jamais leurs réponses, jamais
 * ses chiffres. L'organisateur a refusé que les données de ses invités
 * sortent d'Itaza, et aucun appel d'ici ne doit le démentir.
 *
 * Sans clé configurée, l'assistant se déclare indisponible plutôt que
 * d'échouer : les écrans le disent, et le reste du produit continue.
 */
final class AnthropicAssistant
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function isConfigured(): bool
    {
        return is_string(config('services.anthropic.key')) && config('services.anthropic.key') !== '';
    }

    /**
     * Pose une question et renvoie le texte de la réponse.
     *
     * @throws RuntimeException quand l'assistant n'est pas configuré ou n'a pas répondu
     */
    public function ask(string $system, string $prompt, int $maxTokens = 1500): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException("L'assistant n'est pas configuré.");
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.key'),
            'anthropic-version' => self::VERSION,
        ])
            ->timeout(60)
            // Une panne passagère ne doit pas renvoyer l'organisateur à rien.
            ->retry(2, 500, throw: false)
            ->post(self::ENDPOINT, [
                'model' => (string) config('services.anthropic.model'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("L'assistant n'a pas répondu.");
        }

        $text = '';

        foreach ($response->json('content') ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException("L'assistant n'a rien répondu.");
        }

        return trim($text);
    }

    /**
     * Même chose, pour une réponse attendue en JSON. Le modèle entoure
     * parfois son objet de texte : on ne garde que l'objet.
     *
     * @return array<string, mixed>
     */
    public function askForJson(string $system, string $prompt, int $maxTokens = 2000): array
    {
        $text = $this->ask($system, $prompt, $maxTokens);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException("L'assistant n'a pas répondu dans le format attendu.");
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("L'assistant n'a pas répondu dans le format attendu.");
        }

        return $decoded;
    }
}
