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

WORKDIR /var/www/html

EXPOSE 8000

# On n'utilise pas `artisan serve` : cette commande démarre le serveur PHP
# intégré dans un sous-processus avec un environnement restreint (seulement
# PATH et APP_ENV), ce qui masque les variables injectées par Docker Compose
# (DB_HOST, REDIS_HOST). `php -S` en direct hérite de tout l'environnement.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
