<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

/**
 * Protection SSRF (§7 du CLAUDE.md) : une URL de webhook sortant est
 * choisie par l'organisateur (Paramètres → Intégrations) ou par un tiers
 * via l'API publique (Zapier/n8n, SubscribeWebhookRequest) — jamais de
 * confiance. Résout le nom d'hôte puis rejette toute IP privée, loopback,
 * lien-local (couvre l'adresse de métadonnées cloud 169.254.169.254) ou
 * réservée : filter_var(..., FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
 * couvre RFC 1918/4193 (privé) et RFC 5735/6890 (loopback, lien-local,
 * réservé), IPv4 et IPv6.
 *
 * Résout le nom plutôt que de ne vérifier que la chaîne saisie : un nom de
 * domaine public au moment de l'enregistrement peut être repointé vers une
 * adresse interne ensuite (« DNS rebinding ») — appelée aussi bien à
 * l'enregistrement (StoreWebhookRequest, SubscribeWebhookRequest) qu'au
 * moment de l'envoi (DeliverWebhookJob), pour fermer ce contournement.
 */
final class TargetsPrivateNetwork
{
    public function handle(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return true;
        }

        $host = mb_strtolower($host);

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            // Non résolu : ni confirmé sûr, ni confirmé dangereux — refusé
            // par prudence plutôt que de laisser passer un hôte qui ne
            // répond simplement pas encore.
            return true;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }
}
