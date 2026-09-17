<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Support\Antivirus\FileScanner;
use App\Support\Antivirus\ScannerUnavailableException;
use App\Support\Antivirus\ScanResult;

/**
 * Analyseur factice des tests : sain par défaut, infecté quand $infection
 * porte une signature, injoignable quand $unavailable est vrai.
 */
final class FakeFileScanner implements FileScanner
{
    public ?string $infection = null;

    public bool $unavailable = false;

    public function scan($stream): ScanResult
    {
        if ($this->unavailable) {
            throw ScannerUnavailableException::unreachable('tcp://clamav.test:3310', 'démon arrêté');
        }

        stream_get_contents($stream);

        return $this->infection === null ? ScanResult::clean() : ScanResult::infected($this->infection);
    }
}
