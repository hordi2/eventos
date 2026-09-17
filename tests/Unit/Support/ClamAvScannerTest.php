<?php

declare(strict_types=1);

use App\Support\Antivirus\ClamAvScanner;
use App\Support\Antivirus\ScannerUnavailableException;
use App\Support\Antivirus\ScanResult;

/**
 * Analyse un contenu avec le vrai client, face au faux démon clamd de
 * tests/Fixtures lancé dans un processus à part.
 */
function scanWithFakeClamd(string $content): ScanResult
{
    $socketPath = sys_get_temp_dir().'/itaza-clamd-'.bin2hex(random_bytes(4)).'.sock';
    $process = proc_open([PHP_BINARY, __DIR__.'/../../Fixtures/fake-clamd.php', $socketPath], [], $pipes);

    for ($attempt = 0; $attempt < 150 && ! file_exists($socketPath); $attempt++) {
        usleep(20_000);
    }

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    try {
        return (new ClamAvScanner("unix://{$socketPath}", 5))->scan($stream);
    } finally {
        fclose($stream);
        proc_close($process);
    }
}

it('déclare sain un fichier accepté par clamd, envoyé en plusieurs morceaux', function (): void {
    $result = scanWithFakeClamd(str_repeat('a', 20_000));

    expect($result->isInfected)->toBeFalse();
    expect($result->signature)->toBeNull();
});

it('rend la signature d\'un fichier infecté', function (): void {
    $result = scanWithFakeClamd(str_repeat('a', 9_000).'EICAR');

    expect($result->isInfected)->toBeTrue();
    expect($result->signature)->toBe('Eicar-Test-Signature');
});

it('signale un démon injoignable plutôt que de déclarer le fichier sain', function (): void {
    $stream = fopen('php://memory', 'r+');

    (new ClamAvScanner('unix://'.sys_get_temp_dir().'/itaza-clamd-absent.sock', 1))->scan($stream);
})->throws(ScannerUnavailableException::class);
