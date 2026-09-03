<?php

declare(strict_types=1);

namespace App\Support\Messaging;

/**
 * Séparé de SendWhatsappMessageJob pour rester testable : le SDK Twilio
 * fait ses propres appels HTTP (Guzzle interne), jamais via le client
 * Illuminate\Support\Facades\Http — Http::fake() ne l'intercepterait pas.
 * Cette interface se lie (voir AppServiceProvider) et se remplace en test.
 */
interface WhatsappProvider
{
    /**
     * @param  array<int, string>  $contentVariables
     * @return string identifiant du message chez le prestataire (provider_message_id)
     */
    public function send(string $toPhoneE164, string $contentSid, array $contentVariables, string $statusCallbackUrl): string;
}
