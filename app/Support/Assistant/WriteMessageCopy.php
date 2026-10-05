<?php

declare(strict_types=1);

namespace App\Support\Assistant;

use Illuminate\Support\Str;

/**
 * Rédaction assistée des messages (D4, usage 3) : l'organisateur dit ce
 * qu'il veut dire, l'assistant l'écrit au ton demandé.
 *
 * Ne sortent d'Itaza que le brief de l'organisateur, le ton choisi et le
 * titre de son événement. Jamais la liste de ses invités, jamais leurs
 * réponses : le texte produit s'adresse à tous, et les variables du modèle
 * (:prenom, :evenement) feront le reste au moment de l'envoi.
 */
final class WriteMessageCopy
{
    public const TONES = [
        'chaleureux' => 'Chaleureux et proche',
        'sobre' => 'Sobre et professionnel',
        'solennel' => 'Solennel',
        'enjoue' => 'Enjoué',
    ];

    public function __construct(
        private readonly AnthropicAssistant $assistant,
    ) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function email(string $brief, string $tone, ?string $eventTitle, int $maxWords = 180): array
    {
        $toneLabel = self::TONES[$tone] ?? self::TONES['chaleureux'];
        $context = $eventTitle === null ? '' : " L'événement s'appelle « {$eventTitle} ».";

        $system = <<<PROMPT
            Tu écris des e-mails d'invitation et d'information pour un organisateur d'événements, en français.
            Ton : {$toneLabel}. Pas plus de {$maxWords} mots.
            Tu peux employer les variables :prenom et :evenement, qui seront remplacées à l'envoi.
            N'invente ni date, ni lieu, ni prix que le brief ne donne pas.
            Réponds uniquement par un objet JSON avec les clés "subject" (l'objet de l'e-mail) et "body" (le corps, en paragraphes séparés par des lignes vides).
            PROMPT;

        $answer = $this->assistant->askForJson($system, "Voici ce que je veux dire :{$context}\n\n{$brief}");

        return [
            'subject' => Str::limit((string) ($answer['subject'] ?? ''), 255, ''),
            'body' => trim((string) ($answer['body'] ?? '')),
        ];
    }

    /**
     * Un message WhatsApp : court, sans objet, sans mise en forme.
     */
    public function whatsapp(string $brief, string $tone, ?string $eventTitle): string
    {
        $toneLabel = self::TONES[$tone] ?? self::TONES['chaleureux'];
        $context = $eventTitle === null ? '' : " L'événement s'appelle « {$eventTitle} ».";

        $system = <<<PROMPT
            Tu écris des messages WhatsApp pour un organisateur d'événements, en français.
            Ton : {$toneLabel}. Quatre phrases au plus, pas de mise en forme, pas d'objet.
            Tu peux employer les variables :prenom et :evenement, qui seront remplacées à l'envoi.
            N'invente ni date, ni lieu, ni prix que le brief ne donne pas.
            Réponds uniquement par le message, sans guillemets ni commentaire.
            PROMPT;

        return $this->assistant->ask($system, "Voici ce que je veux dire :{$context}\n\n{$brief}", 600);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function toneOptions(): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::TONES),
            self::TONES,
        );
    }
}
