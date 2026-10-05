<?php

declare(strict_types=1);

namespace App\Support\Assistant;

use App\Domain\Event\Models\EventType;
use App\Domain\Form\Models\FieldType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * « Crée un événement pour la conférence annuelle des partenaires, 300
 * personnes, le 15 mars à Kinshasa » → un brouillon complet (D4, usage 1).
 *
 * L'assistant ne reçoit que la phrase de l'organisateur et la date du jour,
 * pour comprendre « le 15 mars prochain ». Rien d'autre ne sort d'Itaza.
 *
 * Il ne fixe jamais de prix : les paliers qu'il reconnaît dans la phrase
 * reviennent en suggestions, que l'organisateur crée lui-même dans sa
 * billetterie. Un assistant ne décide pas de ce que coûte une place.
 */
final class DraftEventFromSentence
{
    public function __construct(
        private readonly AnthropicAssistant $assistant,
    ) {}

    /**
     * @return array{title: string, type: string, start_at: string, capacity: ?int, description: ?string, fields: list<array<string, mixed>>, ticket_suggestions: list<string>}
     */
    public function handle(string $sentence, string $timezone): array
    {
        $types = implode(', ', array_column(EventType::options(), 'value'));
        $fieldTypes = implode(', ', array_map(fn (FieldType $type): string => $type->value, FieldType::cases()));

        $system = <<<PROMPT
            Tu aides un organisateur d'événements à monter un brouillon à partir d'une phrase.
            Réponds uniquement par un objet JSON, sans texte autour, avec ces clés :
            - "title" : le titre de l'événement, en français ;
            - "type" : l'une de ces valeurs exactement : {$types} ;
            - "start_at" : date et heure de début au format AAAA-MM-JJ HH:MM, dans le fuseau de l'organisateur ;
            - "capacity" : un entier, ou null si la phrase n'en donne pas ;
            - "description" : deux phrases au plus, ou null ;
            - "fields" : la liste des questions du formulaire d'inscription, chacune avec
              "label" (en français), "type" (l'une de : {$fieldTypes}) et "is_required" (booléen) ;
            - "ticket_suggestions" : la liste des paliers de billets évoqués, en texte libre, sans prix inventé.
            N'invente ni prix, ni lieu, ni date que la phrase ne donne pas.
            PROMPT;

        $today = CarbonImmutable::now($timezone)->translatedFormat('l j F Y');
        $answer = $this->assistant->askForJson($system, "Nous sommes le {$today}. Voici la demande :\n\n{$sentence}");

        return [
            'title' => (string) ($answer['title'] ?? 'Nouvel événement'),
            'type' => $this->type($answer['type'] ?? null),
            'start_at' => $this->startAt($answer['start_at'] ?? null, $timezone),
            'capacity' => isset($answer['capacity']) && is_numeric($answer['capacity']) ? (int) $answer['capacity'] : null,
            'description' => isset($answer['description']) && is_string($answer['description']) ? $answer['description'] : null,
            'fields' => $this->fields($answer['fields'] ?? []),
            'ticket_suggestions' => $this->suggestions($answer['ticket_suggestions'] ?? []),
        ];
    }

    private function type(mixed $value): string
    {
        return is_string($value) && EventType::tryFrom($value) !== null ? $value : EventType::Other->value;
    }

    /**
     * Une date que l'assistant aurait mal comprise ne doit pas créer un
     * événement dans le passé : à défaut, dans un mois.
     */
    private function startAt(mixed $value, string $timezone): string
    {
        $fallback = CarbonImmutable::now($timezone)->addMonth()->setTime(9, 0);

        if (! is_string($value)) {
            return $fallback->format('Y-m-d H:i');
        }

        try {
            $parsed = CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return $fallback->format('Y-m-d H:i');
        }

        return $parsed->isPast() ? $fallback->format('Y-m-d H:i') : $parsed->format('Y-m-d H:i');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        $kept = [];

        foreach ($fields as $index => $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null)) {
                continue;
            }

            $type = FieldType::tryFrom((string) ($field['type'] ?? '')) ?? FieldType::ShortText;

            $kept[] = [
                'key' => Str::slug((string) $field['label'], '_') ?: 'question_'.($index + 1),
                'type' => $type->value,
                'label' => Str::limit((string) $field['label'], 255, ''),
                'is_required' => (bool) ($field['is_required'] ?? false),
            ];

            // Un formulaire d'inscription qui pose trente questions n'est
            // plus un formulaire d'inscription.
            if (count($kept) >= 15) {
                break;
            }
        }

        return $kept;
    }

    /**
     * @return list<string>
     */
    private function suggestions(mixed $suggestions): array
    {
        if (! is_array($suggestions)) {
            return [];
        }

        return array_slice(array_values(array_map(
            fn (mixed $line): string => Str::limit((string) $line, 120, ''),
            array_filter($suggestions, 'is_string'),
        )), 0, 5);
    }
}
