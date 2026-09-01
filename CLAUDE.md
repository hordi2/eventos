# CLAUDE.md

> Ce fichier est lu automatiquement au début de chaque session Claude Code.
> Il fait autorité sur les conventions du projet. En cas de contradiction entre
> ce fichier et une habitude générale du framework, **ce fichier gagne**.

---

## 1. Le projet en une page

Plateforme SaaS de gestion d'événements : invitation, inscription, billetterie,
check-in, analytics. Un seul outil qui remplace le patchwork
Google Forms + Excel + Mailchimp + billetterie + liste papier.

**Clients cibles** : entreprises, ONG, écoles, églises, agences événementielles,
particuliers (mariages, anniversaires).

**Marchés prioritaires** : Afrique francophone d'abord (RDC, Congo, Cameroun,
Côte d'Ivoire, Sénégal), puis Europe francophone.

**Trois différenciateurs non négociables** — si une décision technique les met en
péril, il faut alerter, pas contourner :

1. **WhatsApp est un canal de premier rang**, au même niveau que l'e-mail.
2. **Le check-in fonctionne totalement hors ligne.**
3. **Le Mobile Money est un moyen de paiement natif**, pas un ajout.

Le cahier des charges complet est dans `docs/cahier-des-charges.md`.
Les références `M0.1.1`, `D1`, `UC-05` dans les tickets renvoient à ce document.

---

## 2. Stack technique

| Couche | Techno | Version |
|---|---|---|
| Langage | PHP | 8.3+ |
| Framework | Laravel | 12 |
| Base de données | PostgreSQL | 16 |
| Cache / sessions / verrous | Redis | 7 |
| Files d'attente | Laravel Queue (driver Redis) + Horizon | — |
| Front organisateur | Inertia.js + React 18 + TypeScript | — |
| Front invité (public) | Blade + Alpine.js | — |
| CSS | Tailwind CSS | 3 |
| Tests | Pest | 3 |
| Analyse statique | PHPStan (niveau 6 min.) + Laravel Pint | — |
| Stockage fichiers | S3-compatible (Cloudflare R2) | — |
| App check-in mobile | React Native + WatermelonDB *(lot 1, sprint 7)* | — |

### Pourquoi ce découpage front

- **Organisateur → Inertia + React** : interface riche, beaucoup d'état,
  drag-and-drop, temps réel. Pas de SEO nécessaire.
- **Invité → Blade + Alpine** : rendu serveur, poids minimal, SEO,
  doit tourner en 3G sur un téléphone d'entrée de gamme.
  **Contrainte dure : page RSVP < 500 Ko, premier rendu < 2 s en 3G.**
  Ne jamais servir un bundle React sur les pages invité.

---

## 3. Structure du dépôt

```
app/
├── Domain/                  # Cœur métier, organisé par module
│   ├── Organization/        # M0 — comptes, orgas, rôles, abonnements
│   ├── Event/               # M1 — événements, sous-événements, modèles
│   ├── Form/                # M2 — formulaires, champs, logique conditionnelle
│   ├── Contact/             # M3 — contacts, foyers, tags, segments
│   ├── Messaging/           # M4 — e-mail, WhatsApp, SMS, séquences
│   ├── Ticketing/           # M5 — billets, commandes, paiements, dons
│   ├── Page/                # M6 — pages événement, branding
│   ├── CheckIn/             # M7 — check-in, badges, plan de table
│   └── Analytics/           # M8 — tableaux de bord, rapports, exports
│       ├── Models/
│       ├── Actions/         # Une action = un cas d'usage métier
│       ├── Data/            # DTO (spatie/laravel-data)
│       ├── Events/
│       ├── Listeners/
│       ├── Policies/
│       └── Services/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          # API publique versionnée
│   │   ├── Organizer/       # Back-office (Inertia)
│   │   └── Guest/           # Pages publiques (Blade)
│   ├── Middleware/
│   ├── Requests/            # Form Requests = toute la validation
│   └── Resources/           # API Resources = toute la sérialisation
├── Jobs/
└── Support/                 # Helpers transverses, value objects
database/
├── migrations/
├── factories/
└── seeders/
resources/
├── js/organizer/            # App React
├── views/guest/             # Blade invité
└── views/emails/
tests/
├── Unit/
├── Feature/
└── Architecture/            # Tests d'architecture Pest
docs/
```

**Règle** : un module de `Domain/` ne dépend jamais directement des modèles d'un
autre module. La communication passe par des **événements** ou des **services
exposés**. Objectif : pouvoir extraire un module en service séparé plus tard sans
tout casser.

---

## 4. Règles métier absolues

Ces règles ne se discutent pas. Toute violation est un bug bloquant.

### 4.1 Cloisonnement multi-tenant

Toute requête sur une donnée d'organisation **doit** être filtrée sur
`organization_id`.

- Un global scope `BelongsToOrganization` est appliqué sur tous les modèles concernés.
- La row-level security PostgreSQL est activée en seconde barrière.
- **Ne jamais** écrire un `Model::find($id)` sans passer par le scope, sauf dans
  les commandes d'administration explicitement marquées.
- Un test d'architecture vérifie que chaque modèle de `Domain/` déclare le trait.

### 4.2 Argent

- **Tous les montants sont des entiers en plus petite unité monétaire (centimes).**
  Jamais de `float`, jamais de `decimal` en PHP.
- Colonnes : `amount_minor` (bigint) + `currency` (char 3, ISO 4217).
- Utiliser le value object `Money` de `app/Support/Money.php`. Pas d'arithmétique
  directe sur les entiers ailleurs.
- Tout arrondi doit être explicite et testé.

### 4.3 Dates

- **Toutes les dates sont stockées en UTC.**
- Le fuseau de l'événement est stocké séparément dans `events.timezone` (format IANA).
- L'affichage se fait toujours dans le fuseau de l'événement, jamais celui du serveur.
- Ne jamais utiliser `now()` sans passer par `CarbonImmutable`.

### 4.4 Idempotence

Toute opération financière ou de synchronisation doit être idempotente.

- Les webhooks de paiement stockent un `provider_event_id` unique. Un événement
  déjà traité est ignoré silencieusement (log en info, pas d'erreur).
- Les check-ins portent un `device_local_id` (UUID v7 généré côté appareil).
  Une resynchronisation ne crée jamais de doublon.
- Les jobs de queue doivent supporter d'être rejoués.

### 4.5 Suppression

- Suppression logique partout (`deleted_at`), jamais de `DELETE` direct.
- Purge définitive par job planifié après 30 jours.
- L'effacement RGPD est une **anonymisation** : on garde les agrégats et les
  lignes comptables, on efface les données identifiantes.

### 4.6 QR codes

- Un `qr_token` est un JWT signé, à usage unique, révocable.
- Ne jamais utiliser un ID séquentiel ou devinable dans un QR.
- Le scan vérifie signature + expiration + statut + non-réutilisation.

### 4.7 Versionnement des formulaires

Une réponse d'invité reste liée à la **version du formulaire au moment de la
soumission**. Modifier un formulaire ne doit jamais altérer l'interprétation des
réponses déjà collectées.

---

## 5. Conventions de code

### Général

- Code en anglais (classes, méthodes, variables, colonnes).
- Commentaires et messages utilisateur en français.
- `declare(strict_types=1);` en tête de chaque fichier PHP.
- Typage explicite partout : paramètres, retours, propriétés.
- Pas de `mixed` sauf justification en commentaire.

### Laravel

- **Actions plutôt que services fourre-tout.** Une action = une classe = un cas
  d'usage, avec une méthode `handle()`. Exemple :
  `Domain/Event/Actions/PublishEvent.php`.
- **Toute validation dans un Form Request.** Jamais de `$request->validate()`
  dans un contrôleur.
- **Toute sérialisation API dans une API Resource.** Jamais de `return $model`.
- **Contrôleurs maigres** : maximum 15 lignes par méthode. Ils orchestrent, ils
  ne décident pas.
- **Policies pour toute autorisation.** Jamais de `if ($user->role === 'admin')`
  dans le code métier.
- Requêtes N+1 interdites : `Model::preventLazyLoading()` est actif en local et
  en recette.
- Tout traitement > 2 secondes part en queue.

### Nommage base de données

- Tables : pluriel, snake_case (`event_registrations`)
- Colonnes : snake_case
- Clés étrangères : `{table_singulier}_id`
- Booléens : préfixe `is_` ou `has_` (`is_published`, `has_waitlist`)
- Dates : suffixe `_at` (`published_at`, `checked_in_at`)
- Index : créer systématiquement sur `organization_id`, `event_id`, et toute
  colonne servant à filtrer une liste.

### React / TypeScript

- Composants fonctionnels uniquement, un composant par fichier.
- Props typées explicitement, pas de `any`.
- Pas de `useEffect` pour dériver un état — calculer directement.
- Pas de librairie de state management globale tant qu'Inertia suffit.

---

## 6. Tests

**Aucune fonctionnalité n'est terminée sans test.**

| Type | Exigence |
|---|---|
| Feature test | Obligatoire pour chaque endpoint et chaque action métier |
| Unit test | Obligatoire pour tout calcul (montants, capacités, logique conditionnelle) |
| Architecture test | Vérifie le cloisonnement des modules et le trait multi-tenant |
| Couverture | ≥ 75 % sur `app/Domain/` |

Conventions :

- Pest, syntaxe `it('...')` en français : `it('refuse une inscription quand la capacité est atteinte')`
- Une factory par modèle, avec des états nommés (`->published()`, `->soldOut()`)
- Base de test PostgreSQL réelle, jamais SQLite (les comportements JSONB diffèrent)
- Chaque correction de bug commence par un test qui reproduit le bug

**Avant de considérer une tâche terminée, lancer :**

```bash
./vendor/bin/pest
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
npm run build
```

---

## 7. Sécurité — rappels permanents

- Aucun secret dans le code ni dans le dépôt. Tout passe par `.env` / le coffre.
- **Aucune donnée de carte bancaire ne transite ou n'est stockée** par
  l'application. Tout est délégué au prestataire de paiement.
- Rate limiting obligatoire sur : login, inscription, formulaires publics,
  endpoints API, envoi de messages.
- Fichiers téléversés : validation du type réel (pas seulement l'extension),
  taille max, scan antivirus, stockage hors du dossier public.
- CSP stricte sur les pages publiques.
- Toute action sensible est journalisée dans `audit_logs` :
  export, suppression, remboursement, changement de permission, envoi de masse.

---

## 8. Git et livraison

**Branches** : `main` (production) ← `develop` ← `feature/T-042-nom-court`

**Commits** — format Conventional Commits, en français :

```
feat(event): ajoute la duplication d'événement
fix(checkin): corrige le doublon lors d'une resynchronisation hors ligne
refactor(form): extrait le moteur de règles conditionnelles
test(ticketing): couvre le remboursement partiel
```

**Pull request** : référence le ticket, description de ce qui change, capture si
l'interface est touchée, checklist tests/PHPStan/Pint verte.

---

## 9. Comment travailler avec moi (instructions à Claude)

### Avant d'écrire du code

1. **Lis le ticket en entier**, puis la section correspondante du cahier des
   charges (`docs/cahier-des-charges.md`).
2. **Explore le code existant** avant de créer quoi que ce soit. Il y a peut-être
   déjà une action, un trait ou un helper qui fait le travail.
3. **Si le ticket est ambigu ou incomplet, demande** — ne devine pas une règle
   métier. Une mauvaise règle métier coûte plus cher qu'une question.
4. **Propose un plan court** avant d'attaquer une tâche qui touche plus de
   5 fichiers.

### Pendant

- Une tâche à la fois. Ne pars pas refactorer du code non lié.
- Migration + modèle + factory + action + test dans le même mouvement.
- Si tu vois un problème hors périmètre, **signale-le, ne le corrige pas** :
  note-le en fin de réponse, on créera un ticket.

### Ce que je ne veux pas

- Pas de code « au cas où » ni d'abstraction anticipée. On écrit le code du
  ticket, rien de plus.
- Pas de dépendance Composer/npm ajoutée sans me demander d'abord.
- Pas de `TODO` laissé dans le code — soit c'est fait, soit c'est un ticket.
- Pas de commentaire qui paraphrase le code. Un commentaire explique **pourquoi**,
  jamais **quoi**.
- Pas de contournement d'une règle de la section 4. Si une règle bloque, alerte-moi.

### Ce que j'attends quand tu me réponds

- Ce que tu as fait, en 3 lignes maximum.
- Les décisions que tu as prises et pourquoi, s'il y en a.
- Ce qui reste à faire ou à vérifier de mon côté.
- Pas de récapitulatif long du code que je peux lire moi-même.

---

## 10. Commandes utiles

```bash
# Environnement
php artisan serve
npm run dev
php artisan horizon              # workers de queue

# Base
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoEventSeeder

# Qualité
./vendor/bin/pest
./vendor/bin/pest --coverage --min=75
./vendor/bin/phpstan analyse
./vendor/bin/pint

# Debug
php artisan tinker
php artisan queue:failed
```

---

## 11. État d'avancement

> Section à tenir à jour à chaque fin de lot.

- [ ] **Lot 1 — MVP** (sprints 1 à 8)
- [ ] Lot 2 — Consolidation
- [ ] Lot 3 — Montée en gamme

**Sprint en cours** : Sprint 1 — Fondations
**Dernière mise à jour** : —
