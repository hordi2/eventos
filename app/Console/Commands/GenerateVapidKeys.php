<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

final class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid-keys';

    protected $description = 'Génère une paire de clés VAPID pour les notifications push et les affiche pour le .env';

    public function handle(): int
    {
        $keyResource = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($keyResource === false) {
            $this->error('Impossible de générer la paire de clés EC (vérifiez que l\'extension OpenSSL est active).');

            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($keyResource);

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('La clé générée ne contient pas de point EC exploitable.');
        }

        if (! openssl_pkey_export($keyResource, $privatePem)) {
            throw new RuntimeException("Échec de l'export de la clé privée.");
        }

        $publicKey = $this->encodePublicKey($details['ec']['x'], $details['ec']['y']);

        $this->info('Clés VAPID générées. Ajoutez ces lignes à votre .env :');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$publicKey);
        $this->line('VAPID_PRIVATE_KEY='.base64_encode($privatePem));
        $this->line('VAPID_SUBJECT=mailto:contact@votre-domaine.example');
        $this->newLine();
        $this->comment('VAPID_SUBJECT doit être une adresse mailto: ou une URL https:// qui identifie votre organisation auprès des services de push.');

        return self::SUCCESS;
    }

    private function encodePublicKey(string $x, string $y): string
    {
        $point = "\x04".str_pad($x, 32, "\x00", STR_PAD_LEFT).str_pad($y, 32, "\x00", STR_PAD_LEFT);

        return rtrim(strtr(base64_encode($point), '+/', '-_'), '=');
    }
}
