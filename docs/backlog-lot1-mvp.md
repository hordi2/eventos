# BACKLOG — LOT 1 (MVP)
## Plateforme de gestion d'événements

**Durée cible** : 8 sprints de 2 semaines (16 semaines)
**Objectif du lot** : un organisateur crée un événement, invite, collecte les
réponses, vend des billets et accueille ses invités le jour J — le tout utilisable
sur un vrai événement de 200 à 500 personnes.

**Comment utiliser ce document** : un ticket = une session Claude Code. Copie le
ticket entier dans le prompt, avec la référence du cahier des charges.
Ne lance jamais « construis le module M2 » — lance un ticket.

**Légende taille** : S = ½ à 1 j · M = 2 à 3 j · L = 4 à 6 j · XL = à redécouper

---

# SPRINT 1 — FONDATIONS

*Rien de visible pour l'utilisateur. Tout ce qui suit en dépend. Ne pas bâcler.*

---

### T-001 · Initialisation du projet · M
**Réf** : §8 CDC

Mettre en place le squelette Laravel 12 + PostgreSQL + Redis, la structure de
dossiers `app/Domain/`, Pest, PHPStan niveau 6, Pint, et le pipeline GitHub Actions
(tests + analyse statique bloquants sur PR).

**Critères d'acceptation**
- [ ] `./vendor/bin/pest` passe sur un projet vide
- [ ] PHPStan niveau 6 sans erreur
- [ ] CI verte sur une PR de test
- [ ] `docker compose up` démarre app + PostgreSQL + Redis
- [ ] README avec la procédure d'installation en moins de 10 commandes

---

### T-002 · Multi-tenant : organisations et cloisonnement · L
**Réf** : M0.2, §4.1 CLAUDE.md · **Dépend de** T-001

Modèles `Organization`, `User`, `Membership`. Trait `BelongsToOrganization` avec
global scope. Row-level security PostgreSQL activée. Middleware de résolution de
l'organisation courante.

**Critères d'acceptation**
- [ ] Un utilisateur peut appartenir à plusieurs organisations
- [ ] Une requête sans contexte d'organisation lève une exception explicite
- [ ] Test : un utilisateur de l'orga A ne peut jamais lire une donnée de l'orga B
- [ ] Test d'architecture : tout modèle de `Domain/` ayant `organization_id` déclare le trait
- [ ] La RLS PostgreSQL bloque même une requête SQL brute mal filtrée

> ⚠️ Ticket critique. Une faille ici est une fuite de données entre clients.
> À faire relire par une seconde personne.

---

### T-003 · Authentification · M
**Réf** : M0.1.1, M0.1.2, M0.1.6, M0.1.7 · **Dépend de** T-002

Inscription, connexion, déconnexion, mot de passe oublié, vérification d'e-mail.
OAuth Google. Verrouillage après 5 échecs. Hachage Argon2id.

**Critères d'acceptation**
- [ ] Parcours complet inscription → vérification → connexion
- [ ] Token de réinitialisation expiré après 30 min
- [ ] Blocage 15 min après 5 tentatives échouées
- [ ] Rate limit sur `/login` et `/register`
- [ ] Connexion Google fonctionnelle avec création de compte à la volée

---

### T-004 · Rôles et permissions · M
**Réf** : M0.3 · **Dépend de** T-002

Rôles : `owner`, `admin`, `editor`, `door_staff`, `viewer`. Policies Laravel.
Rôle par organisation + surcharge possible par événement.

**Critères d'acceptation**
- [ ] Matrice M0.3 du CDC intégralement couverte par des tests
- [ ] Un `door_staff` ne peut ni modifier la liste ni voir les montants
- [ ] Un `viewer` ne peut effectuer aucune écriture
- [ ] Tentative non autorisée → 403, jamais 404 ni erreur serveur

---

### T-005 · Journal d'audit · S
**Réf** : M0.5 · **Dépend de** T-002

Table `audit_logs`, trait `Auditable`, enregistrement automatique des actions
sensibles (qui, quoi, quand, IP, user-agent).

**Critères d'acceptation**
- [ ] Export, suppression, changement de permission et envoi de masse sont tracés
- [ ] Le journal est consultable et exportable par le `owner`
- [ ] Une entrée d'audit ne peut jamais être modifiée ni supprimée

---

### T-006 · Value objects Money et gestion des devises · S
**Réf** : §4.2 CLAUDE.md · **Dépend de** T-001

Classe `Money` immuable (montant entier + devise), cast Eloquent, formatage par
locale, opérations arithmétiques sûres, interdiction du mélange de devises.

**Critères d'acceptation**
- [ ] Addition de deux devises différentes → exception
- [ ] Répartition d'un montant sans perte de centime (test sur 100 / 3)
- [ ] Formatage correct pour EUR, USD, CDF, XOF, XAF
- [ ] Aucun `float` dans le code de la classe

---

### T-007 · Design system et layouts de base · M
**Réf** : §11 CDC · **Dépend de** T-001

Jetons Tailwind (couleurs, typographie, espacements, rayons), composants de base
(bouton, champ, sélecteur, modale, table, alerte, badge), layout organisateur
(Inertia/React) et layout invité (Blade).

**Critères d'acceptation**
- [ ] Contraste ≥ 4,5:1 vérifié sur tous les composants
- [ ] Navigation clavier complète, focus visible
- [ ] Layout invité < 100 Ko sans contenu
- [ ] Responsive validé à 360 / 768 / 1280 px

---

# SPRINT 2 — ÉVÉNEMENTS

---

### T-010 · Modèle Event et CRUD · L
**Réf** : M1.1, M1.2 · **Dépend de** T-002, T-004

Modèle `Event` complet (voir §7.2 CDC), migrations, factory, policies, actions
`CreateEvent`, `UpdateEvent`, `PublishEvent`, `ArchiveEvent`.

**Critères d'acceptation**
- [ ] Machine à états respectée : `draft → published → live → ended → archived`
- [ ] Publier un événement sans formulaire actif → refus avec message clair
- [ ] `timezone` obligatoire, validée contre la liste IANA
- [ ] Slug unique par organisation, généré automatiquement, modifiable
- [ ] Suppression logique, restauration possible sous 30 jours

---

### T-011 · Interface de création d'événement · M
**Réf** : M1.1 · **Dépend de** T-010, T-007

Assistant en 3 étapes : type d'événement → informations essentielles → confirmation.
Valeurs par défaut intelligentes. Objectif : événement créé en moins de 3 minutes.

**Critères d'acceptation**
- [ ] Seuls titre, date et fuseau sont obligatoires
- [ ] Sauvegarde automatique en brouillon à chaque étape
- [ ] Test utilisateur : création réussie sans aide en < 3 min

---

### T-012 · Lieu et informations pratiques · S
**Réf** : M1.2 · **Dépend de** T-010

Modèle `Venue` : nom, adresse, coordonnées GPS, instructions d'accès, parking.
Autocomplétion d'adresse. Réutilisation d'un lieu déjà saisi.

---

### T-013 · Sous-événements · M
**Réf** : M1.3 · **Dépend de** T-010

Un événement contient N sous-événements avec capacité, horaires et lieu propres.

**Critères d'acceptation**
- [ ] Inscription indépendante par sous-événement
- [ ] Détection des conflits d'horaires entre sous-événements
- [ ] Capacité gérée séparément de l'événement parent
- [ ] Suppression d'un sous-événement avec inscriptions → refus ou archivage

---

### T-014 · Duplication d'événement · S
**Réf** : M1.1.3 · **Dépend de** T-010, T-013

Dupliquer un événement avec choix : formulaire, page, tarifs, liste d'invités,
séquences d'e-mails. Les dates sont décalées, jamais copiées telles quelles.

---

# SPRINT 3 — FORMULAIRES

*Le module le plus risqué du lot. Prototyper le moteur de règles avant tout le reste.*

---

### T-020 · Modèle de formulaire et versionnement · L
**Réf** : M2.1, §4.7 CLAUDE.md · **Dépend de** T-010

Modèles `Form`, `FormVersion`, `FormField`, `FieldOption`. Une soumission est liée
à une `FormVersion` figée.

**Critères d'acceptation**
- [ ] Modifier un formulaire crée une nouvelle version, l'ancienne reste intacte
- [ ] Les réponses déjà collectées restent interprétables après modification
- [ ] Test : ajout, suppression et renommage d'un champ après 50 réponses

---

### T-021 · Types de champs (12 types MVP) · L
**Réf** : M2.1 · **Dépend de** T-020

Texte court, texte long, nombre, e-mail, téléphone, date, choix unique, choix
multiple, oui/non, consentement, menu/repas, texte informatif.

**Critères d'acceptation**
- [ ] Chaque type : validation serveur ET client, rendu, stockage, export
- [ ] Téléphone normalisé en E.164 avec sélecteur d'indicatif
- [ ] E-mail validé syntaxe + MX
- [ ] Champ consentement horodaté avec IP
- [ ] Quota par option de choix (« 50 places pour l'atelier A »)

---

### T-022 · Moteur de logique conditionnelle · L
**Réf** : M2.2 · **Dépend de** T-021

Règles « si champ X vaut Y alors afficher/masquer/rendre obligatoire le champ Z ».
Opérateurs : est, n'est pas, contient, >, <, est vide. Combinaisons ET/OU.

**Critères d'acceptation**
- [ ] Évaluation identique côté client et côté serveur (jamais de divergence)
- [ ] Détection des références circulaires au moment de la sauvegarde
- [ ] Un champ masqué n'est jamais validé ni enregistré
- [ ] Mode « simuler une réponse » dans l'éditeur
- [ ] Test avec 5 niveaux de dépendances imbriquées

> ⚠️ Ticket à risque identifié en annexe C du CDC. Commencer par un prototype
> jetable du moteur d'évaluation, valider la structure de données, puis intégrer.

---

### T-023 · Constructeur de formulaire (interface) · L
**Réf** : M2.1 · **Dépend de** T-021, T-022

Éditeur drag-and-drop, panneau de propriétés, aperçu temps réel mobile/desktop.

**Critères d'acceptation**
- [ ] Réorganisation par glisser-déposer, y compris au clavier (accessibilité)
- [ ] Aperçu fidèle au rendu final
- [ ] Sauvegarde automatique, avertissement en cas de sortie non enregistrée

---

### T-024 · Capacités et liste d'attente · M
**Réf** : M2.4, M1.2 · **Dépend de** T-020, T-013

Capacité globale, par sous-événement, par option. Liste d'attente avec position et
promotion automatique en cas de désistement.

**Critères d'acceptation**
- [ ] Verrou Redis empêchant le dépassement en cas d'inscriptions simultanées
- [ ] Test de concurrence : 100 inscriptions simultanées sur 50 places → exactement 50 acceptées
- [ ] Désistement → promotion du premier de la liste + notification
- [ ] Option complète affichée grisée avec mention « Complet »

---

# SPRINT 4 — INSCRIPTION ET PARCOURS INVITÉ

---

### T-030 · Modèle Registration et soumission · L
**Réf** : M2.4, UC-05 · **Dépend de** T-020, T-024

Modèles `Registration`, `RegistrationAnswer`, `Attendee`. Action `SubmitRegistration`.

**Critères d'acceptation**
- [ ] Détection de doublon par e-mail → proposition de modifier l'inscription existante
- [ ] Capture de la source (UTM, référent, IP, user-agent, locale)
- [ ] Inscription hors période → message personnalisable
- [ ] Transaction atomique : réponse + décompte de capacité + événement domaine

---

### T-031 · Page RSVP publique · L
**Réf** : M2.5, §11.4 · **Dépend de** T-030, T-007

Page invité en Blade + Alpine. Multi-étapes avec barre de progression, sauvegarde
et reprise, récapitulatif avant validation.

**Critères d'acceptation**
- [ ] **Poids < 500 Ko, premier rendu < 2 s en 3G simulée**
- [ ] Score Lighthouse mobile > 90
- [ ] Fonctionne sans JavaScript pour les formulaires simples (dégradation gracieuse)
- [ ] Aucune création de compte demandée à l'invité
- [ ] Test utilisateur : taux de complétion > 85 %

---

### T-032 · Inscription de groupe et foyers · M
**Réf** : M3.2, M3.5 · **Dépend de** T-030

Un invité répond pour lui et les membres de son foyer, chacun avec ses propres
réponses (menu, présence par sous-événement). Gestion des +1 et +X.

**Critères d'acceptation**
- [ ] Le chef de foyer voit et modifie les réponses de tous les membres
- [ ] Chaque membre a son propre QR code
- [ ] Le décompte de capacité compte chaque personne, pas chaque soumission
- [ ] +1 anonyme et +X nommés, tous deux fonctionnels

---

### T-033 · Modification et annulation par l'invité · M
**Réf** : M2.4 · **Dépend de** T-030

Lien sécurisé permettant à l'invité de modifier ou annuler sa réponse jusqu'à J-X.

**Critères d'acceptation**
- [ ] Lien signé, non devinable, expirant à la date limite
- [ ] Historisation de la version précédente
- [ ] Annulation → libération de la place + promotion de la liste d'attente
- [ ] Verrouillage automatique passé la date limite paramétrée

---

# SPRINT 5 — CONTACTS ET COMMUNICATION

---

### T-040 · Base de contacts et foyers · L
**Réf** : M3.1, M3.2 · **Dépend de** T-002

Modèles `Contact`, `Household`, champs personnalisés en JSONB, consentements par
canal horodatés.

**Critères d'acceptation**
- [ ] Contact réutilisable d'un événement à l'autre au sein de l'organisation
- [ ] Historique de participation consultable sur la fiche
- [ ] Consentement e-mail / SMS / WhatsApp distincts, avec source et date
- [ ] Recherche sur 10 000 contacts en < 500 ms

---

### T-041 · Import de contacts · L
**Réf** : M3.3 · **Dépend de** T-040

Import CSV/Excel avec mappage de colonnes assisté, détection de doublons, aperçu
avant validation, rapport d'import.

**Critères d'acceptation**
- [ ] Import de 10 000 lignes traité en queue, sans blocage de l'interface
- [ ] Mappage proposé automatiquement d'après les en-têtes
- [ ] Doublons détectés par score de similarité, choix fusionner / ignorer / créer
- [ ] Rapport ligne par ligne : acceptée, rejetée, motif
- [ ] Import incrémental sans écraser les données existantes non fournies

---

### T-042 · Tags et segments · M
**Réf** : M3.4 · **Dépend de** T-040

Tags libres et colorés. Segments dynamiques définis par critères, recalculés à la
volée.

**Critères d'acceptation**
- [ ] Segments prédéfinis : sans réponse, confirmés, déclinés, présents, no-show
- [ ] Un segment se recalcule à chaque consultation, jamais figé
- [ ] Application d'un tag en masse sur un segment

---

### T-043 · Infrastructure d'e-mail transactionnel · M
**Réf** : M4.5 · **Dépend de** T-001

Intégration du prestataire, gestion des bounces et plaintes, liste de suppression,
suivi ouvertures/clics, envoi en queue avec limitation de débit.

**Critères d'acceptation**
- [ ] Bounce dur → contact marqué invalide, exclu des envois suivants
- [ ] Webhooks du prestataire traités de façon idempotente
- [ ] Lien de désabonnement conforme sur tout e-mail non transactionnel
- [ ] Envoi de 5 000 e-mails sans saturer la queue ni dépasser le débit autorisé

---

### T-044 · Éditeur d'e-mails et variables de fusion · L
**Réf** : M4.2 · **Dépend de** T-043, T-040

Éditeur par blocs, bibliothèque de modèles, variables de fusion, aperçu par
destinataire réel, test d'envoi.

**Critères d'acceptation**
- [ ] Variables : prénom, nom, lien RSVP personnalisé, QR, date, lieu, table, champs personnalisés
- [ ] Une variable non résolue affiche une valeur de repli, jamais `{{prenom}}` brut
- [ ] Rendu correct sur Gmail, Outlook, Apple Mail (test Litmus ou équivalent)
- [ ] Aperçu avec les données réelles d'un destinataire au choix

---

### T-045 · Campagnes et e-mails automatiques · M
**Réf** : M4.3 · **Dépend de** T-044

Envoi d'invitation, confirmation automatique, rappel de réponse, rappel J-7/J-1,
remerciement J+1. Pièce jointe ICS. Planification.

**Critères d'acceptation**
- [ ] Confirmation envoyée dans les 60 s suivant l'inscription
- [ ] Fichier ICS correct avec fuseau horaire et rappel
- [ ] Ciblage par segment (relance des non-répondants uniquement)
- [ ] Envoi planifié respectant le fuseau du destinataire
- [ ] Annulation possible d'un envoi planifié

---

# SPRINT 6 — BILLETTERIE ET PAIEMENTS

---

### T-050 · Types de billets et tarification · M
**Réf** : M5.1 · **Dépend de** T-010

Modèles `TicketType`, `PriceTier`. Billets gratuits et payants, quantités, min/max
par commande, paliers early bird, options payantes, TVA.

**Critères d'acceptation**
- [ ] Bascule automatique de palier à la date ou au quota atteint
- [ ] Frais absorbés ou répercutés — choix explicite et affiché à l'acheteur
- [ ] TVA incluse ou en sus, calcul testé sur cas limites
- [ ] Aucun montant en float (test d'architecture)

---

### T-051 · Panier et commande · L
**Réf** : M5.4 · **Dépend de** T-050, T-030

Modèles `Order`, `OrderItem`, `Ticket`, `Payment`. Réservation temporaire du stock
15 minutes pendant le paiement.

**Critères d'acceptation**
- [ ] Stock réservé à l'entrée en paiement, libéré à l'expiration
- [ ] Machine à états : `pending → paid | failed | expired | refunded`
- [ ] Test de concurrence sur les 5 derniers billets
- [ ] Abandon de panier détecté et enregistré

---

### T-052 · Intégration Stripe (carte) · M
**Réf** : M5.3 · **Dépend de** T-051

Paiement carte, Apple Pay, Google Pay. Webhooks idempotents. Aucune donnée de
carte côté serveur.

**Critères d'acceptation**
- [ ] Webhook rejoué 3 fois → une seule commande confirmée
- [ ] Échec de paiement → stock libéré, invité informé, possibilité de réessayer
- [ ] Signature de webhook vérifiée systématiquement
- [ ] Test avec les cartes de test 3D Secure

---

### T-053 · Intégration Mobile Money · L
**Réf** : M5.3, D3 · **Dépend de** T-051

Couche d'abstraction `PaymentProvider` + premier agrégateur (Flutterwave ou
CinetPay). Gestion du paiement **asynchrone** : l'utilisateur valide sur son
téléphone, la confirmation arrive par webhook.

**Critères d'acceptation**
- [ ] État `pending` géré explicitement avec écran d'attente et relance de statut
- [ ] Délai de confirmation jusqu'à 5 min supporté sans perdre la commande
- [ ] Timeout → stock libéré, commande expirée, invité notifié
- [ ] Réconciliation quotidienne automatique avec le fournisseur
- [ ] Interface d'abstraction permettant d'ajouter un 2e agrégateur sans toucher au métier

> ⚠️ Risque identifié en annexe C. Prévoir un mode dégradé si l'agrégateur est
> indisponible : basculer sur « paiement à l'arrivée ».

---

### T-054 · Paiement à l'arrivée et espèces · S
**Réf** : D3 · **Dépend de** T-051

L'invité réserve sans payer, le personnel enregistre l'encaissement au check-in
avec émission d'un reçu numérique.

**Critères d'acceptation**
- [ ] Commande en statut `payment_on_site` clairement identifiée au check-in
- [ ] Encaissement tracé : montant, opérateur, horodatage
- [ ] Rapport de caisse en fin d'événement

---

### T-055 · Billets et QR codes · M
**Réf** : §4.6 CLAUDE.md · **Dépend de** T-051

Génération de QR signés à usage unique, billet PDF, envoi par e-mail.

**Critères d'acceptation**
- [ ] Token JWT signé, non devinable, révocable
- [ ] Un QR par personne, jamais par commande
- [ ] PDF lisible et scannable depuis un écran de téléphone en basse luminosité
- [ ] Réémission possible en cas de perte, invalidant l'ancien

---

### T-056 · Dons · S
**Réf** : M5.6 · **Dépend de** T-051

Montants suggérés et libres, don additionnel au moment du paiement du billet,
reçu automatique.

---

### T-057 · Configuration des billets et tarifs (interface organisateur) · L
**Réf** : M5.1, M5.2 · **Dépend de** T-050

Écran de configuration des types de billets côté organisateur — T-050 n'avait
livré que les modèles, sans interface. CRUD complet d'un type de billet
(gratuit ou payant), gestion des paliers de tarification (early bird → normal
→ tardif) avec dates et quotas, choix TVA incluse/en sus, choix frais
absorbés/répercutés affiché explicitement, quantité min/max par commande,
quota global par type de billet.

**Critères d'acceptation**
- [ ] Création d'un type de billet gratuit ou payant en moins de 2 minutes
- [ ] Ajout/suppression d'un palier avec aperçu de la bascule automatique (dates + quota)
- [ ] Choix TVA et frais absorbés/répercutés visibles et modifiables, avec aperçu du prix final affiché à l'acheteur
- [ ] Liste des types de billets d'un événement avec quantité vendue/restante en temps réel
- [ ] Erreurs de bornes incohérentes (min/max, palier sans dates ni quota) affichées clairement

---

### T-058 · Page publique de sélection des billets et panier (invité) · L
**Réf** : M5.4 · **Dépend de** T-057, T-055

Interface publique (Blade/Alpine, même contrainte de poids que la page RSVP
T-031) : sélection des types de billets et quantités sur la page événement,
ajout d'un don libre ou suggéré au moment du paiement, récapitulatif du
panier, formulaire acheteur (nom/e-mail/téléphone), déclenchement de
`CreateOrder`.

**Critères d'acceptation**
- [ ] Page RSVP < 500 Ko, premier rendu < 2 s en 3G (§2 CLAUDE.md)
- [ ] Sélection de quantité respecte min/max par commande et le quota affiché en temps réel
- [ ] Bascule visuelle du palier actif (ex. early bird épuisé → palier normal affiché)
- [ ] Don optionnel avec montants suggérés et montant libre
- [ ] Erreur claire si le quota est atteint entre l'affichage et la soumission

---

### T-059 · Choix et confirmation du moyen de paiement (invité) · L
**Réf** : M5.3 · **Dépend de** T-058, T-052, T-053, T-054

Écran de choix du moyen de paiement (carte via Stripe Checkout, Mobile Money
via Flutterwave, paiement à l'arrivée), écran d'attente Mobile Money avec
relance de statut, page de confirmation avec téléchargement du billet PDF
(T-055) une fois la commande payée.

**Critères d'acceptation**
- [ ] Redirection Stripe Checkout fonctionnelle, retour sur la page de confirmation après paiement
- [ ] Écran d'attente Mobile Money avec relance de statut, timeout géré (message clair + option de réessayer)
- [ ] Paiement à l'arrivée : confirmation immédiate de la réservation, rappel du mode choisi
- [ ] Téléchargement du/des billets PDF disponible dès la commande payée
- [ ] Message clair et action de réessai en cas d'échec de paiement

---

# SPRINT 7 — JOUR J

---

### T-060 · API de check-in · M
**Réf** : M7.1 · **Dépend de** T-055

Endpoints : téléchargement de la liste complète, check-in, check-out, recherche,
synchronisation par lot.

**Critères d'acceptation**
- [x] Synchronisation par lot idempotente via `device_local_id`
- [x] Deux postes scannant le même billet → premier accepté, second signalé en conflit
- [x] Téléchargement d'une liste de 5 000 invités en < 10 s (mesuré à ~20 ms en local, hors latence réseau)
- [x] Réponse de check-in en < 200 ms

---

### T-061 · Application de check-in — mode hors ligne · XL
**Réf** : M7.1, D2 · **Dépend de** T-060

> Ticket structurant du différenciateur D2. Ne pas rogner dessus.

Redécoupé en 3 sous-tickets au moment du sprint planning (Sprint 7), comme prévu
ci-dessous. React Native + base locale (WatermelonDB), nouveau projet dans
`mobile/` à la racine du dépôt.

---

### T-061a · Socle appli mobile + liste hors ligne · M
**Réf** : M7.1, D2 · **Dépend de** T-060

Projet React Native + WatermelonDB. Authentification via l'API de check-in
(T-060, jeton Sanctum par appareil), téléchargement préalable de la liste
complète des invités d'un événement dans la base locale, recherche instantanée
hors ligne par nom/e-mail/téléphone/n° de billet.

**Critères d'acceptation**
- [ ] Connexion au poste (e-mail/mot de passe) et obtention d'un jeton d'appareil, stocké de façon persistante
- [ ] Téléchargement d'une liste de 5 000 invités dans la base locale, consultable immédiatement après
- [ ] Recherche locale instantanée sur 5 000 invités, sans connexion réseau
- [ ] Indicateur visible de l'état de connectivité (en ligne / hors ligne)

---

### T-061b · Scan et check-in local · S
**Réf** : M7.1, D2 · **Dépend de** T-061a

Scan de QR code par caméra, écriture du check-in en local uniquement (aucun
appel réseau synchrone) — la synchronisation proprement dite est T-061c.

**Critères d'acceptation**
- [ ] Scan en < 1 s — implémenté (expo-camera + décodage QR local), non chronométré sur appareil réel
- [ ] Écran lisible en pénombre, utilisable à une main — implémenté (fond sombre, retour en grand texte, un seul bouton)
- [ ] Mode avion complet : 500 check-ins enregistrés en local sans connexion, **zéro perte** — implémenté (écriture 100 % locale, aucun appel réseau dans le chemin d'écriture), jamais chargé à 500 sur appareil réel
- [ ] Alerte immédiate (locale) si l'invité scanné est déjà marqué présent dans la base locale — implémenté et couvert par la logique (`recordLocalCheckIn`)
- [ ] Mode « liste » sans scan (recherche + bouton, comme le check-in web T-062) — implémenté

**⚠️ Aucune de ces cases n'a été vérifiée sur simulateur/appareil réel** (pas
d'Xcode/Android Studio dans l'environnement de développement à l'écriture,
voir mobile/README.md) : vérifié uniquement par `tsc`, un export Metro complet
et des tests Jest sur la logique pure. À valider avant de cocher.

---

### T-061c · Synchronisation et résolution de conflits · S
**Réf** : M7.1, D2 · **Dépend de** T-061b

File d'attente de synchronisation locale, envoi par lot à l'API de check-in
(T-060, idempotente via `device_local_id`) dès que la connexion revient, sans
intervention de l'utilisateur.

**Critères d'acceptation**
- [ ] Reconnexion → synchronisation automatique de la file d'attente, sans action manuelle — implémenté (`useSyncEngine`, détection NetInfo + minuteur de rattrapage)
- [ ] Résolution de conflit correcte quand un autre poste a déjà enregistré le même invité entre-temps — implémenté (un « conflict » serveur marque la ligne comme envoyée, jamais rejouée)
- [ ] Indicateur visible : en ligne / hors ligne / en cours de synchro / N en attente — implémenté (`SyncStatusBadge`)
- [ ] Batterie : 4 h d'utilisation continue sur un téléphone d'entrée de gamme (scan + sync périodique) — minuteur à 30 s choisi pour rester léger, jamais mesuré sur un vrai appareil

**⚠️ Aucune de ces cases n'a été vérifiée sur simulateur/appareil réel** (pas
d'Xcode/Android Studio dans l'environnement de développement à l'écriture,
voir mobile/README.md) : vérifié uniquement par `tsc`, un export Metro complet
et des tests Jest sur la logique pure. À valider avant de cocher — ce qui clôt
alors T-061 dans son ensemble (T-061a + T-061b + T-061c).

---

### T-062 · Check-in web (secours) · M
**Réf** : M7.1 · **Dépend de** T-060

Version navigateur du check-in, pour un poste fixe avec ordinateur portable.
Scan par webcam ou douchette USB, recherche par nom.

---

### T-063 · Ajout d'un invité sur place · S
**Réf** : M7.1.7 · **Dépend de** T-060, T-054

Inscription rapide au comptoir avec formulaire minimal et encaissement éventuel.

---

### T-064 · Badges PDF · M
**Réf** : M7.3 · **Dépend de** T-055

Éditeur simple de badge (champs, logo, QR, couleur par tag), génération PDF pour
planches Avery et impression à la demande.

**Critères d'acceptation**
- [x] Génération d'un badge en < 2 s
- [x] Génération en masse de 500 badges en queue
- [x] Code couleur automatique par tag — invités RSVP uniquement (via leur Contact) ; un billet payé n'a aujourd'hui aucun lien vers un Contact, badge neutre
- [x] Repères de découpe corrects sur planche Avery (8 badges/A4)

---

### T-065 · Plan de table et placement · L
**Réf** : M7.4 · **Dépend de** T-030, T-051, T-064

Ticket ajouté après coup (signalé par l'utilisateur en relisant le tableau de
bord événement, T-070 : M7.4 existe dans le cahier des charges mais n'avait
jamais eu de ticket dans ce backlog).

Éditeur visuel du plan de salle : tables de formes différentes (ronde,
rectangulaire, en U, cocktail, rangées), capacité par table, numérotation
automatique. Affectation des invités par glisser-déposer ou placement
automatique assisté (équilibrage du remplissage, contraintes « doit être
avec » / « ne doit pas être avec »). Export PDF du plan et des listes par
table. Le numéro de table assigné doit apparaître sur le badge (T-064) et
dans l'e-mail de rappel (M4), et rester consultable par l'équipe d'accueil
au check-in (T-062) sans dépendre du plan visuel lui-même.

**Critères d'acceptation**
- [x] Éditeur visuel : créer, positionner et redimensionner des tables (formes ronde, rectangulaire, en U, cocktail, rangées), capacité définie par table
- [x] Numérotation automatique des tables
- [x] Affectation d'un invité à une table par glisser-déposer ; un invité n'est jamais affecté à deux tables à la fois
- [x] Placement automatique assisté : remplit les tables en respectant leur capacité et en équilibrant le remplissage
- [x] Contraintes manuelles « doit être avec » / « ne doit pas être avec » respectées par le placement automatique, et signalées si violées après une affectation manuelle
- [x] Export PDF du plan de salle et des listes d'invités par table
- [x] Le numéro de table assigné apparaît sur le badge (T-064) et dans l'e-mail de rappel
- [x] Vue « qui est à quelle table » consultable par l'équipe d'accueil, indépendamment de l'éditeur visuel

Deux ajouts demandés par l'utilisateur après la première livraison :
- chaque table est entourée d'une place (« boule ») par siège de capacité — occupée (initiales de l'invité) ou vide (pointillé, « + ») — un clic permet d'assigner, retirer ou remplacer l'invité de cette place ;
- une page dédiée en lecture seule (`/events/{event}/seating/overview`, « Vue d'ensemble de la salle ») affiche toutes les tables et leurs places sans aucune interaction, pensée pour être affichée telle quelle le jour de l'événement.

---

# SPRINT 8 — PILOTAGE, FACTURATION ET STABILISATION

---

### T-070 · Tableau de bord événement temps réel · L
**Réf** : M8.1 · **Dépend de** T-030, T-051, T-060

Blocs inscriptions, financier, communication, jour J. Mise à jour en direct.

Seuls les blocs « inscriptions » (courbe cumulée) et « jour J » (présents, taux
de présence, courbe d'arrivée) sont couverts, conformément aux critères
d'acceptation ci-dessous — les blocs financier, communication et logistique de
M8.1 ne sont pas dans le périmètre de ce ticket, signalés pour un ticket
séparé plutôt que construits ici.

**Critères d'acceptation**
- [x] Mise à jour sans rechargement (WebSocket ou SSE) — SSE choisi, vérifié en direct dans le navigateur (compteur passé de 3 à 4 sans rechargement après un check-in ajouté en base)
- [x] Courbe cumulée des inscriptions dans le temps
- [x] Le jour J : présents, taux de présence, courbe d'arrivée par tranche horaire
- [x] Chargement en < 1 s sur un événement de 5 000 inscrits — mesuré à ~24 ms

**⚠️ Point d'attention production** : une connexion SSE ouverte immobilise un
worker PHP-FPM pour toute sa durée (30 s ici, avant reconnexion automatique du
navigateur) — contrairement à une requête HTTP classique. Avec beaucoup
d'organisateurs gardant le tableau de bord ouvert simultanément, un pool de
workers trop petit pourrait s'épuiser. Au-delà d'un certain volume, ce
compromis justifierait de passer à Octane ou à un vrai serveur WebSocket
(Reverb), qui ne consomment pas un worker de requête par connexion ouverte.

---

### T-071 · Exports · M
**Réf** : M8.4 · **Dépend de** T-030

Export CSV et Excel des invités, inscriptions, commandes, check-ins. Sélection de
colonnes, filtrage par segment. Traitement en queue avec lien de téléchargement.

**Critères d'acceptation**
- [ ] Export de 50 000 lignes en < 60 s, en tâche de fond — implémenté en streaming (`cursor()`, aucune requête N+1) mais non chargé-testé sur un vrai jeu de 50 000 lignes dans cette session ; à vérifier avant mise en production
- [x] Lien de téléchargement expirant après 24 h
- [x] **Tout export est journalisé dans l'audit** (exigence RGPD)
- [x] Encodage UTF-8 avec BOM (compatibilité Excel)

Scope MVP volontairement réduit par rapport au cahier des charges (M8.4
mentionne aussi PDF/JSON, les exports planifiés envoyés par e-mail) : seul
le CSV est couvert, conformément aux critères d'acceptation ci-dessus qui
ne demandent ni PDF/JSON ni planification — pas de dépendance ajoutée pour
un vrai format .xlsx binaire, le CSV avec BOM UTF-8 s'ouvre correctement
dans Excel. Le filtre par segment ne s'applique qu'au type « invités » :
c'est le seul des quatre où un segment RSVP a un sens direct.

---

### T-072 · Page événement publique · L
**Réf** : M6.1 · **Dépend de** T-010, T-031

Un modèle de page configurable : bannière, description, programme, lieu + carte,
FAQ, formulaire intégré. Métadonnées SEO et données structurées `schema.org/Event`.

Scope MVP volontairement réduit par rapport au cahier des charges (M6.1
mentionne un éditeur par sections drag-and-drop, du multi-pages, une page de
remerciement personnalisable) : un modèle de page fixe mais configurable
(bannière, programme, FAQ en listes ordonnées), conformément aux critères
d'acceptation ci-dessous qui ne demandent ni éditeur drag-and-drop ni
multi-pages. La racine `/r/{organisation}/{événement}` affiche désormais
cette page publique au lieu de créer immédiatement un brouillon
d'inscription — un nouveau bouton « S'inscrire » (route `.../commencer`)
déclenche ce qui se faisait auparavant à la simple visite de l'URL.

**Critères d'acceptation**
- [ ] Score Lighthouse mobile > 90 — non mesuré dans cette session (pas d'outil Lighthouse disponible) ; page construite en Blade + Alpine, sans bundle React, conformément à la contrainte < 500 Ko du CLAUDE.md
- [ ] Balisage `schema.org/Event` validé par l'outil Google — non soumis à l'outil de test officiel de Google dans cette session ; le JSON-LD généré a été vérifié manuellement (champs `@context`, `@type`, `startDate`, `location`, `image`)
- [ ] Image de partage correcte sur WhatsApp, Facebook, LinkedIn — balises Open Graph et Twitter Card en place (`og:image`, `og:title`, `og:description`) ; non testé sur un vrai partage WhatsApp/Facebook/LinkedIn dans cette session
- [x] URL personnalisée par événement — le slug de l'événement (existant, modifiable par l'organisateur) sert d'URL

---

### T-073 · Charte graphique de l'organisation · M
**Réf** : M6.2 · **Dépend de** T-072

Logo, palette, typographies, appliqués en un clic à toutes les pages et e-mails.

Ticket sans critère d'acceptation dans le backlog d'origine ; scope MVP
arbitré avec l'utilisateur avant construction, faute d'AC pour trancher —
le cahier des charges (M6.2) prévoit une palette à 5 couleurs, un choix de
typographies (avec upload de polices propriétaires), des rayons d'arrondi
et un CSS personnalisé pour les plans avancés, hors périmètre ici :
- logo + une seule couleur principale (pas de palette à 5 couleurs, pas de
  typographie personnalisée, pas de CSS libre) ;
- nouvelle page de réglages `/organization/branding` (première page de
  réglages d'organisation du produit — jusqu'ici seul le journal d'audit
  existait sous « Organisation ») ;
- appliqués à la page événement publique (T-072 : logo au-dessus du titre,
  couleur du bouton « S'inscrire ») et à l'en-tête des e-mails (logo,
  bordure supérieure de la couleur principale) — pas aux pages de
  l'organisateur lui-même (interface produit d'Itaza, pas de la marque de
  l'organisateur, conformément à la section 2 du CLAUDE.md).

---

### T-074 · Plans, quotas et facturation · L
**Réf** : M0.4, §13 · **Dépend de** T-002

Trois plans (gratuit + 2 payants), feature flags, compteurs d'usage, abonnement
Stripe, factures PDF.

Comme pour T-052/T-053, aucun compte Stripe réel n'était configuré au moment
de construire ce ticket (décision prise avec l'utilisateur) : les Price ID
des deux plans payants sont des variables d'environnement à renseigner
(`STRIPE_PRICE_PRO`, `STRIPE_PRICE_BUSINESS`, voir .env.example), et toute
action de paiement échoue proprement avec un message clair tant qu'ils sont
vides, plutôt que de planter. Le prix affiché (29 $/99 $ par mois) est un
espace réservé arbitraire, aucune tarification n'a été définie par le
produit. Les factures PDF ne sont pas reconstruites : le portail Stripe
(lien « Gérer mon abonnement ») expose déjà les factures hostées par
Stripe, ce que le ticket ne demande pas de dupliquer.

**Critères d'acceptation**
- [x] Compteurs fiables : inscriptions/mois, e-mails/mois, événements actifs — recalculés à la demande (jamais de compteur dénormalisé sujet à dérive)
- [x] Alerte à 80 % et 100 % de quota — e-mail au propriétaire/administrateur, une seule fois par mois et par seuil (tâche planifiée quotidienne)
- [x] **Dépassement = blocage non destructif** : les nouvelles inscriptions passent en liste d'attente (jamais rejetées) une fois le quota mensuel atteint, avec notification à l'organisateur
- [x] Changement de plan avec prorata correct — délégué à l'API Stripe Subscriptions (`proration_behavior: create_prorations`), jamais recalculé côté application ; non vérifié avec un vrai compte Stripe faute de Price ID configurés
- [x] Échec de prélèvement → relances J+1, J+3, J+7, puis restriction — la restriction ne modifie jamais le plan enregistré, seulement son application aux quotas tant que le paiement n'est pas régularisé

---

### T-075 · Conformité RGPD · M
**Réf** : §10.2 · **Dépend de** T-040, T-005

Export des données d'une personne, effacement par anonymisation, gestion des
consentements, durées de conservation, bannière cookies.

Périmètre arbitré avec l'utilisateur avant construction : la bannière
cookies (P-14) et l'effacement des comptes organisateurs (`User`, sans
SoftDeletes) sont mentionnés au cahier des charges mais absents des 4 AC
ci-dessous — restent hors de ce ticket, à traiter séparément si besoin.
La gestion des consentements (P-02) existait déjà (T-040).

**Découverte en construisant, corrigée dans ce ticket** : le journal
d'audit est immuable au niveau base (déclencheur PostgreSQL, T-005) et
enregistrait les valeurs brutes des champs modifiés — e-mail, nom,
téléphone — dans `audit_logs.metadata`, à chaque modification d'un
Contact/Registration/Order. Anonymiser un contact ne pouvait donc rien
changer aux entrées déjà écrites. Corrigé pour l'avenir seulement (décision
prise avec l'utilisateur) : ces champs sont désormais remplacés par
`[redacted]` avant journalisation. **Les entrées antérieures à ce correctif
contiennent toujours des données en clair** — techniquement impossible à
corriger rétroactivement tant que le déclencheur d'immuabilité reste en
place ; à traiter par un administrateur base de données si nécessaire,
hors du périmètre logiciel de ce ticket.

**Critères d'acceptation**
- [x] Export complet d'une personne en JSON, en un clic — contact, consentements, tags, foyer, inscriptions et réponses de formulaire, participants ; n'inclut pas les commandes de billetterie (aucune colonne ne relie une commande à un Contact, Domain/Ticketing n'a pas de contact_id — limite connue, signalée)
- [x] Effacement : données identifiantes supprimées, agrégats et lignes comptables conservés — anonymise le Contact et ses Registrations/Attendees liés (statut, date, événement conservés) ; n'anonymise pas les commandes de billetterie (même limite que l'export, ci-dessus) ni les réponses de formulaire libres (aucune façon fiable d'y détecter une identité sans risquer d'effacer une réponse métier légitime)
- [x] Job planifié de purge selon les durées configurées — `config/gdpr.php` (durée par défaut : 36 mois depuis la dernière inscription, ou la création si aucune), tâche quotidienne
- [x] Registre des traitements généré — page + export PDF, durée de conservation injectée depuis la configuration en vigueur

---

### T-076 · Page de statut et supervision · S
**Réf** : §18.2 · **Dépend de** T-001

Sentry, métriques, healthchecks, page de statut publique, alertes.

Ticket sans critère d'acceptation dans le backlog d'origine, comme T-073 —
scope arbitré avec l'utilisateur avant construction :
- **Sentry** : dépendance ajoutée (décision prise avec l'utilisateur —
  `sentry/sentry-laravel`), branchée sur `bootstrap/app.php` via
  `Integration::handles()`. `SENTRY_LARAVEL_DSN` vide par défaut (voir
  .env.example) : aucun envoi tant qu'il n'est pas renseigné, même principe
  que Stripe/Postmark/Twilio/Flutterwave dans ce projet.
- **Métriques** : le cahier des charges (tableau d'architecture) vise
  Sentry + OpenTelemetry + Grafana à terme — déployer une stack
  d'observabilité séparée (collecteur OpenTelemetry, Grafana hébergé) est
  hors périmètre d'un ticket S et dépend de décisions d'infrastructure que
  l'utilisateur n'a pas encore prises ; Sentry couvre déjà les erreurs et
  offre ses propres métriques de performance basiques, jugé suffisant pour
  le MVP.
- **Healthchecks** : `App\Support\Status\CheckSystemHealth` vérifie base de
  données et Redis ; un listener sur l'événement natif `DiagnosingHealth`
  de Laravel fait échouer la route `/up` existante (`bootstrap/app.php`,
  `health: '/up'`) en cas de panne d'un des deux.
  `system_status_checks` (hors cloisonnement multi-tenant §4.1 — donnée
  d'infrastructure, pas d'organisation) historise chaque vérification,
  exécutée toutes les 5 minutes (`status:check-health`, routes/console.php).
- **Page de statut publique** : `/status` (Blade, sans authentification),
  affiche l'état courant par composant, la disponibilité sur 24 h et un
  historique visuel des dernières vérifications.
- **Alertes** : e-mail à `STATUS_ALERT_EMAIL` (vide = aucun envoi)
  uniquement lors d'un changement d'état (passage sain → en échec ou
  inverse), jamais à chaque vérification planifiée, pour ne pas noyer
  l'équipe pendant un incident prolongé.

---

### T-077 · Documentation utilisateur · M
**Dépend de** l'ensemble du lot

Guide de démarrage, tutoriels des parcours principaux, FAQ.

---

### T-078 · Événement pilote · L
**Réf** : §17.4 · **Dépend de** tout

Organiser un vrai événement de 200 à 500 personnes sur la plateforme, équipe
présente sur place, relevé de tous les incidents.

**Critères d'acceptation**
- [ ] Zéro perte de donnée
- [ ] Temps moyen de check-in < 8 s
- [ ] Compte rendu écrit avec les incidents classés par sévérité
- [ ] Les correctifs bloquants sont livrés avant la mise en marché

> Ce ticket n'est pas optionnel. C'est la seule recette qui révèle les vrais
> problèmes : le réseau qui tombe, l'imprimante qui bourre, le bénévole qui ne
> comprend pas l'écran, les 40 personnes qui arrivent en même temps.

---

# RÉCAPITULATIF

| Sprint | Thème | Tickets | Charge estimée |
|---|---|---|---|
| 1 | Fondations | T-001 → T-007 | ~22 j |
| 2 | Événements | T-010 → T-014 | ~16 j |
| 3 | Formulaires | T-020 → T-024 | ~26 j |
| 4 | Inscription et parcours invité | T-030 → T-033 | ~19 j |
| 5 | Contacts et communication | T-040 → T-045 | ~28 j |
| 6 | Billetterie et paiements | T-050 → T-059 | ~42 j |
| 7 | Jour J | T-060 → T-065 (T-061 en 061a/b/c) | ~32 j |
| 8 | Pilotage et stabilisation | T-070 → T-078 | ~32 j |
| | | **54 tickets** | **~217 j** |

*Charge en jours de développement, hors design, QA dédiée, gestion de projet et
provision pour imprévus. Rapprocher de l'estimation §16.2 du CDC (560 j/h tous
profils confondus pour le Lot 1).*

---

# TICKETS À RISQUE — SURVEILLANCE RAPPROCHÉE

| Ticket | Risque | Mesure |
|---|---|---|
| T-002 | Faille de cloisonnement multi-tenant | Double relecture + tests d'intrusion dédiés |
| T-022 | Moteur conditionnel sous-estimé | Prototype jetable avant intégration |
| T-053 | Fiabilité de l'agrégateur Mobile Money | Mode dégradé + réconciliation automatique |
| T-061a/b/c | Complexité du hors ligne | Redécoupé en 3 tickets (Sprint 7), livrer T-061a en premier pour valider tôt la synchronisation |
| T-074 | Compteurs d'usage faux = revenus faux | Tests de charge sur les compteurs |

---

# ORDRE DE DÉMARRAGE RECOMMANDÉ

Si tu ne devais démarrer que trois tickets cette semaine, dans cet ordre :

1. **T-001** — sans le squelette, rien n'avance
2. **T-002** — c'est la fondation de tout, et la plus dangereuse à reprendre plus tard
3. **T-022** en prototype jetable — pour lever le risque le plus élevé du lot avant
   d'avoir construit dessus

---

*Backlog v1.0 — à réviser à chaque fin de sprint.*
