# EventOS

Plateforme SaaS de gestion d'événements : invitation, inscription, billetterie,
check-in, analytics.

Le contexte produit complet est dans [`docs/cahier-des-charges.md`](docs/cahier-des-charges.md),
le découpage en tickets dans [`docs/backlog-lot1-mvp.md`](docs/backlog-lot1-mvp.md),
et les conventions de développement dans [`CLAUDE.md`](CLAUDE.md).

## Stack

PHP 8.3 · Laravel 12 · PostgreSQL 16 · Redis 7 · Inertia + React (organisateur) ·
Blade + Alpine (invité) · Pest · PHPStan (niveau 6) · Pint.

## Installation

Prérequis : PHP 8.3+, Composer, Node 18+, Docker (ou colima).

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up -d postgres redis
php artisan migrate
npm install
npm run build
php artisan serve
```

L'application est disponible sur `http://localhost:8000`.

Pour lancer l'app **et** ses dépendances entièrement dans Docker, à la place des
deux dernières commandes :

```bash
docker compose up -d
```

## Développement au quotidien

```bash
php artisan serve              # serveur applicatif
npm run dev                    # Vite (assets organisateur)
php artisan horizon            # workers de queue (Redis)
```

## Qualité

```bash
./vendor/bin/pest                      # tests
./vendor/bin/pest --coverage --min=75  # avec couverture (seuil du projet)
./vendor/bin/phpstan analyse --memory-limit=512M  # analyse statique (niveau 6)
./vendor/bin/pint                      # formatage automatique
./vendor/bin/pint --test               # vérification sans modification
```

La CI GitHub Actions (`.github/workflows/ci.yml`) bloque toute PR si les tests,
PHPStan ou Pint échouent.

## Structure

Voir la section 3 de [`CLAUDE.md`](CLAUDE.md) pour le détail de l'arborescence
`app/Domain/` et les règles métier absolues (section 4) à respecter dans tout
le code.
