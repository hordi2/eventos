<?php

declare(strict_types=1);

/**
 * Script de routage du serveur PHP intégré (conteneur de développement).
 *
 * Sans lui, `php -S` répond lui-même 404 à toute adresse qui ressemble à un
 * fichier — tout ce qui se termine par une extension. Or l'application en
 * sert plusieurs : agenda.ics, theme.css, qr.png, faire-part.pdf. Elles
 * fonctionnaient en production (nginx passe tout à index.php) et seulement
 * là, ce qui rendait la panne invisible en local.
 *
 * Même principe que le script de `php artisan serve` : un fichier qui
 * existe vraiment est servi tel quel, tout le reste part dans Laravel.
 */
$publicPath = __DIR__.'/../../public';
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';

require $publicPath.'/index.php';
