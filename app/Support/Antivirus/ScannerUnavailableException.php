<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

use RuntimeException;

final class ScannerUnavailableException extends RuntimeException
{
    public static function unreachable(string $address, string $reason): self
    {
        return new self("ClamAV injoignable à {$address} : {$reason}");
    }

    public static function noResponse(string $address): self
    {
        return new self("ClamAV n'a pas répondu à temps ({$address}).");
    }

    public static function unreadableFile(): self
    {
        return new self('Le fichier à analyser est illisible.');
    }

    public static function unexpectedResponse(string $response): self
    {
        return new self("Réponse inattendue de ClamAV : « {$response} ».");
    }
}
