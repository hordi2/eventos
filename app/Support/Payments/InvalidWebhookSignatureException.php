<?php

declare(strict_types=1);

namespace App\Support\Payments;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self("Signature de webhook {$provider} invalide.");
    }
}
