#!/bin/sh
set -e

# Lien public/storage, posé à chaque démarrage du conteneur.
#
# `php artisan storage:link` écrit un lien *absolu* vers le chemin de la
# machine hôte — un chemin qui n'existe pas dans le conteneur, où le projet
# vit sous /var/www/html. Le lien y pointe alors dans le vide et toutes les
# images téléversées répondent 404.
#
# Un lien relatif, lui, se résout des deux côtés : depuis public/,
# ../storage/app/public mène au bon dossier sur l'hôte comme ici.
if [ -L public/storage ] && [ ! -e public/storage ]; then
    rm public/storage
fi

if [ ! -e public/storage ]; then
    ln -s ../storage/app/public public/storage
fi

exec "$@"
