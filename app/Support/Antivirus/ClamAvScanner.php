<?php

declare(strict_types=1);

namespace App\Support\Antivirus;

/**
 * Client du démon clamd par la commande INSTREAM : le fichier part par
 * morceaux préfixés de leur longueur, et clamd répond « stream: OK » ou
 * « stream: <signature> FOUND ». Écrit ici plutôt qu'ajouté en dépendance :
 * le protocole tient en quelques lignes.
 */
final class ClamAvScanner implements FileScanner
{
    private const CHUNK_BYTES = 8192;

    public function __construct(
        private readonly string $address,
        private readonly int $timeoutSeconds,
    ) {}

    public function scan($stream): ScanResult
    {
        $socket = @stream_socket_client($this->address, $errorCode, $errorMessage, $this->timeoutSeconds);

        if ($socket === false) {
            throw ScannerUnavailableException::unreachable($this->address, $errorMessage);
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $this->send($socket, "zINSTREAM\0");

            while (! feof($stream)) {
                $chunk = @fread($stream, self::CHUNK_BYTES);

                if ($chunk === false) {
                    throw ScannerUnavailableException::unreadableFile();
                }

                if ($chunk !== '') {
                    $this->send($socket, pack('N', strlen($chunk)).$chunk);
                }
            }

            $this->send($socket, pack('N', 0));
            $response = stream_get_contents($socket);

            if ($response === false || stream_get_meta_data($socket)['timed_out']) {
                throw ScannerUnavailableException::noResponse($this->address);
            }
        } finally {
            fclose($socket);
        }

        return $this->interpret(trim($response, "\0\n "));
    }

    /**
     * @param  resource  $socket
     */
    private function send($socket, string $bytes): void
    {
        while ($bytes !== '') {
            // clamd coupe la connexion quand le fichier dépasse sa limite :
            // l'écriture échoue alors, sans avertissement PHP.
            $written = @fwrite($socket, $bytes);

            if ($written === false || $written === 0) {
                throw ScannerUnavailableException::noResponse($this->address);
            }

            $bytes = substr($bytes, $written);
        }
    }

    private function interpret(string $response): ScanResult
    {
        if ($response === 'stream: OK') {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $response, $matches) === 1) {
            return ScanResult::infected($matches[1]);
        }

        // « INSTREAM size limit exceeded. ERROR » comme toute autre réponse.
        throw ScannerUnavailableException::unexpectedResponse($response);
    }
}
