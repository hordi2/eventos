<?php

declare(strict_types=1);

/*
 * Faux démon clamd pour ClamAvScannerTest : écoute sur le socket Unix reçu
 * en argument, accepte une seule connexion et parle le protocole INSTREAM
 * (commande terminée par \0, morceaux préfixés de leur longueur sur 4
 * octets, morceau vide pour finir). Un contenu qui porte « EICAR » est
 * déclaré infecté.
 */

$socketPath = $argv[1] ?? exit(1);
@unlink($socketPath);

$server = stream_socket_server("unix://{$socketPath}", $errorCode, $errorMessage);
$client = $server !== false ? stream_socket_accept($server, 10) : false;

if ($client === false) {
    exit(1);
}

$read = function (int $bytes) use ($client): string {
    $data = '';

    while (strlen($data) < $bytes) {
        $part = fread($client, $bytes - strlen($data));

        if ($part === false || $part === '') {
            exit(1);
        }

        $data .= $part;
    }

    return $data;
};

$command = '';

while (! str_ends_with($command, "\0")) {
    $command .= $read(1);
}

$content = '';

while (($length = unpack('N', $read(4))[1]) > 0) {
    $content .= $read($length);
}

fwrite($client, str_contains($content, 'EICAR') ? "stream: Eicar-Test-Signature FOUND\0" : "stream: OK\0");
fclose($client);
@unlink($socketPath);
