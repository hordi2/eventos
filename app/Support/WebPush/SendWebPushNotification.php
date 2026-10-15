<?php

declare(strict_types=1);

namespace App\Support\WebPush;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envoie une notification push RFC 8030 sans charge utile (payload-less),
 * authentifiée par un jeton VAPID (RFC 8292). Le contenu réel de la
 * notification vit dans le fil de notifications in-app de l'application
 * participant, jamais dans la charge chiffrée du push.
 */
final readonly class SendWebPushNotification
{
    public function __construct(private VapidKeyPair $vapid) {}

    /**
     * @return bool true si l'abonnement est toujours valide, false s'il est
     *              expiré et doit être supprimé (404/410).
     */
    public function handle(string $endpoint): bool
    {
        $jwt = JWT::encode([
            'aud' => $this->originOf($endpoint),
            'exp' => now()->addHours(12)->timestamp,
            'sub' => $this->vapid->subject,
        ], $this->vapid->privateKeyPem, 'ES256');

        $response = Http::withHeaders([
            'Authorization' => sprintf('vapid t=%s, k=%s', $jwt, $this->vapid->publicKey),
            'TTL' => '86400',
        ])->post($endpoint);

        if ($response->successful()) {
            return true;
        }

        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }

        throw new RuntimeException("Échec de l'envoi de la notification push : HTTP {$response->status()}.");
    }

    private function originOf(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("Endpoint d'abonnement push invalide : {$endpoint}");
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
