<?php

declare(strict_types=1);

namespace App\Domain\Form;

use RuntimeException;

final class InvalidAttendeeQrTokenException extends RuntimeException
{
    public static function invalid(): self
    {
        return new self('QR code illisible ou falsifié.');
    }

    public static function expired(): self
    {
        return new self('Ce QR code a expiré.');
    }

    public static function revoked(): self
    {
        return new self("Ce QR code n'est plus valable : un nouveau a été émis.");
    }

    public static function notConfirmed(): self
    {
        return new self("Cette inscription n'est pas confirmée : annulée, refusée ou en liste d'attente.");
    }
}
