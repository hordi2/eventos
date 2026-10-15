<?php

declare(strict_types=1);

namespace App\Support\WebPush;

final readonly class VapidKeyPair
{
    public function __construct(
        public string $publicKey,
        public string $privateKeyPem,
        public string $subject,
    ) {}

    public static function fromConfig(): ?self
    {
        $publicKey = config('webpush.public_key');
        $privateKeyPem = config('webpush.private_key');
        $subject = config('webpush.subject');

        if (blank($publicKey) || blank($privateKeyPem) || blank($subject)) {
            return null;
        }

        $decodedPem = base64_decode((string) $privateKeyPem, strict: true);

        if ($decodedPem === false) {
            return null;
        }

        return new self((string) $publicKey, $decodedPem, (string) $subject);
    }

    public static function isConfigured(): bool
    {
        return self::fromConfig() instanceof self;
    }
}
