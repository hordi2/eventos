<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\WhatsappTemplate;

/**
 * Traduit variable_mapping (liste ordonnée de clés de fusion) en variables
 * numérotées pour l'API Content de Twilio — partagé par
 * SendWhatsappToContact et SendTestWhatsapp, qui ne diffèrent que par le
 * destinataire réel de l'envoi.
 */
final class ResolveWhatsappTemplateVariables
{
    public function __construct(
        private readonly ResolveMergeVariables $resolveMergeVariables,
    ) {}

    /**
     * PHP normalise toute clé de tableau numérique en entier, même
     * explicitement castée en chaîne ("1" devient la clé int 1) : le
     * tableau retourné est donc bien array<int, string> — json_encode()
     * l'encodera correctement en objet {"1": "...", "2": "..."} côté
     * Twilio du moment que les clés ne commencent pas à 0 (jamais le cas
     * ici, la première variable vaut toujours 1).
     *
     * @return array<int, string>
     */
    public function handle(WhatsappTemplate $template, Contact $contact, ?Event $event): array
    {
        $variables = [];

        // Position dans variable_mapping => numéro Twilio : index 0 => 1,
        // index 1 => 2... (Content API, jamais {{0}}).
        foreach ($template->variable_mapping as $index => $mergeVariable) {
            $variables[$index + 1] = $this->resolveMergeVariables->resolve("{{{$mergeVariable}}}", $contact, $event);
        }

        return $variables;
    }
}
