FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Fichiers joints des invités : jusqu'à 10 Mo chacun, plusieurs par page.
RUN printf "upload_max_filesize=12M\npost_max_size=64M\n" > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

EXPOSE 8000

# On n'utilise pas `artisan serve` : cette commande démarre le serveur PHP
# intégré dans un sous-processus avec un environnement restreint (seulement
# PATH et APP_ENV), ce qui masque les variables injectées par Docker Compose
# (DB_HOST, REDIS_HOST). `php -S` en direct hérite de tout l'environnement.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
