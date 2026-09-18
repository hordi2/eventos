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

> **Événements secondaires, étape D du formulaire (livrée)** :
> - **Écran « Événements secondaires »** dans le menu de l'événement : sessions avec horaires (fuseau de l'événement principal), capacité, liste d'attente, places tenues et conflits d'horaires signalés. Une session qui a des inscrits ne peut pas être supprimée.
> - **Bloc « Événements secondaires »** du constructeur : l'organisateur choisit les sessions proposées. Leurs titres sont repris du serveur et figés avec la version publiée.
> - **Côté invité** : les sessions s'affichent avec les places restantes, et deux sessions simultanées sont refusées dès l'étape des réponses.
> - **Inscription par session** : chaque session cochée crée une inscription rattachée (`parent_registration_id`) sur le sous-événement. Elle a sa capacité propre (une place par personne du groupe), sa liste d'attente, sa liste d'accueil et son check-in. Le QR principal y est reconnu.
> - **Mise à jour** : la modification et l'annulation mettent les sessions à jour.
> - **Tableau de bord** : les sessions n'y apparaissent plus comme des événements à part.

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

> **Extension « Personnaliser → Formulaire d'inscription », étape A (livrée)** :
> six questions de plus — liste déroulante (gratuite), date et heure, lien web,
> profil de réseau social, quantité, adresse postale (plans payants : essai
> possible au plan Gratuit, publication refusée tant qu'elles restent dans le
> formulaire). Réglages par bloc « Demander si » (toujours / invités présents /
> invités qui déclinent) et « Seulement pour les tags… ». Écran de bienvenue,
> réponse « Je ne peux pas venir » (statut Décliné, aucune place ni quota
> consommé, segment « Déclinés »), écrans de confirmation et de refus
> personnalisés, thème du parcours invité (5 couleurs, 2 polices, logo, image
> de fond). Réglages de présentation dans `forms.settings`, hors versionnement.
> Reste l'étape B (constructeur à blocs React) puis le lot 2 (téléversement
> de fichier avec antivirus, dons, sous-événements, informations du donateur,
> présence par participant — dépend de T-032).
>
> **Lot 2, étape F (livrée) — « Fichier joint »** (question avancée) : formats
> choisis dans le bloc (images, PDF, documents) et taille jusqu'à 10 Mo. Type
> réel, extension et taille contrôlés, puis quarantaine hors du dossier public
> (`registration_files`, disque `REGISTRATION_FILES_DISK`) et analyse ClamAV en
> file d'attente (`ScanRegistrationFileJob`, client INSTREAM sans dépendance,
> `CLAMAV_ADDRESS`). L'invité n'attend pas le verdict ; un fichier infecté est
> supprimé et l'invité prévenu par e-mail avec son lien de modification ; ClamAV
> injoignable : nouvelles tentatives, puis « Analyse impossible » et bouton
> « Relancer l'analyse ». Page « Fichiers reçus » de l'événement : liste pour
> `viewGuests`, téléchargement des fichiers sains pour `exportData`, journalisé
> (`registration_file.downloaded`). Fichiers conservés avec l'inscription,
> supprimés à l'effacement RGPD du contact ; ceux jamais rattachés à une
> inscription sont purgés après 30 jours (`registration-files:purge-unclaimed`).
> Service `clamav` ajouté au Docker Compose de développement.
> Limites connues : pas de question « Fichier joint » posée à chaque
> accompagnant ; le serveur PHP doit accepter les envois (`upload_max_filesize`
> ≥ 12M, `post_max_size` ≥ 64M, réglés dans l'image Docker) ; un envoi qui
> dépasse `post_max_size` renvoie une page d'erreur au lieu d'un message dans
> le formulaire.
>
> **Lot 2, étape G (livrée) — voir les réponses côté organisateur** : jusque-là
> les réponses n'étaient visibles nulle part (le tableau de bord ne montre que
> des compteurs). Nouvel écran « Réponses aux questions » : répartition des
> choix avec quotas restants, nombre de donateurs et total des dons, sessions
> choisies, aperçu des réponses libres, lien vers « Fichiers reçus » ; puis la
> liste des invités (50 plus récentes) avec leurs réponses, celles des
> accompagnants précédées de leur prénom. Les deux entrées du menu encore
> marquées « bientôt » sont livrées : « Préférences alimentaires » (une ligne
> par personne attendue, accompagnants compris, quotas restants) et « Dons et
> cadeaux » (dons du formulaire et des billets, état de paiement, totaux reçus
> et promis). Chaque écran n'apparaît dans le menu que si le formulaire pose la
> question correspondante ; tous exigent `viewGuests`.
> Limites connues : agrégation en mémoire, à revoir en agrégats SQL au-delà de
> quelques milliers d'inscriptions par événement ; la liste par invité n'est
> pas paginée (renvoi vers l'export au-delà de 50).
>
> **Lot 2, étape H (livrée) — image et vidéo dans « Texte, image, vidéo »** :
> le bloc informatif ne portait que du texte. L'organisateur y ajoute
> maintenant une image (PNG, JPEG, WebP, 8 Mo maximum) redimensionnée dès
> l'envoi — 1600 px de large au plus, orientation EXIF redressée, PNG et WebP
> gardés pour la transparence, le reste réencodé en JPEG — pour que la page
> invité tienne sa contrainte de poids ; elle est servie depuis
> `form-blocks/{form}` du disque public avec son texte alternatif et ses
> dimensions, ce qui évite tout décalage au chargement. Il colle aussi un lien
> YouTube ou Vimeo : seule l'adresse du lecteur est retenue (YouTube en
> `youtube-nocookie`), et rien n'est chargé avant que l'invité clique sur
> « Lire la vidéo ». Le chemin de l'image est validé côté serveur
> (`form-blocks/` obligatoire, `..` refusé) ; l'adresse publique est recalculée
> à l'affichage, jamais enregistrée dans la version du formulaire.
> Limites connues : aucun autre hébergeur vidéo que YouTube et Vimeo.
>
> **Lot 2, étape I (livrée) — bibliothèque d'images « Mes images »** : chaque
> image envoyée pour un logo, un fond ou un bloc était enfermée dans son
> emplacement, et il fallait la renvoyer ailleurs. Toutes passent maintenant
> par une entrée unique (`StoreOrganizationImage`) : redimensionnées — les
> images du thème le sont donc enfin —, rangées sous
> `organization-images/{organisation}` et inscrites dans `organization_images`,
> d'où l'onglet « Mes images » du constructeur les propose pour le logo, le
> fond et les blocs. Conséquence assumée : « Retirer » ne fait plus que
> détacher, le fichier appartenant à la bibliothèque ; la suppression
> définitive vit dans « Mes images » et est refusée tant que l'image sert
> quelque part, le message nommant chaque emplacement (toutes les versions de
> formulaire comptent, §4.7). La migration reprend les images déjà
> référencées, en ignorant celles dont le fichier a disparu.
> Limites connues : 60 images les plus récentes affichées, sans pagination ni
> recherche ; les bannières des pages événement ne rejoignent pas encore la
> bibliothèque.
>
> **Lot 2, étape J (livrée) — CSS personnalisé** : dernière fonction du
> constructeur marquée « bientôt ». Champ CSS par formulaire, réservé aux
> plans payants et refusé à l'enregistrement au plan Gratuit — contrairement
> aux questions avancées, il s'appliquerait tout de suite, il ne peut donc pas
> s'essayer. Ce qui ferait sortir la feuille de son rôle est refusé en nommant
> la construction fautive (@import, @charset, @namespace, expression(),
> behavior, -moz-binding, le caractère « < », et toute adresse dans url()
> hors /storage), puis revérifié à la livraison : une feuille écrite
> directement en base n'atteint jamais un invité (`CustomCss`). Elle est
> servie comme vraie feuille de style (`/r/{orga}/{événement}/theme.css`,
> empreinte du contenu en version, cache d'une heure) plutôt qu'en ligne : le
> navigateur la garde d'un écran à l'autre du parcours et une future CSP
> stricte n'aura pas à autoriser de style en ligne. Les pages invité portent
> désormais trois repères stables à cibler — `.itaza-page`, `.itaza-field`,
> `.itaza-progress` — sans quoi il faudrait viser des classes utilitaires qui
> changent à chaque construction des styles.
> Limites connues : le contrôle du plan a lieu à l'enregistrement, donc un
> rétrogradage ne retire pas un CSS déjà enregistré (même comportement que les
> questions avancées déjà publiées) ; pas d'aperçu du CSS dans le constructeur ;
> trois repères seulement, à élargir si les organisateurs le demandent.
>
> **Lot 2, étape K (livrée) — onglet « Bibliothèque » (photos libres Pexels)** :
> recherche en français dans les photos libres de droits de Pexels (sélection
> du moment à l'ouverture, « Plus de photos », crédit du photographe et lien
> vers Pexels affichés), depuis la fenêtre du logo et du fond comme depuis le
> bloc « Texte, image, vidéo ». La photo choisie est téléchargée par le
> serveur puis copiée dans « Mes images » par le chemin habituel
> (redimensionnée, nommée « Photo de … (Pexels) ») : la page invité ne
> contacte jamais Pexels. Le serveur ne télécharge qu'à partir de l'adresse
> renvoyée par l'API, et seulement sur images.pexels.com. Recherches mises en
> cache une heure et débit limité (le quota gratuit est de 200 requêtes par
> heure pour toute l'installation). Configuration : `PEXELS_API_KEY`, clé
> gratuite à créer sur pexels.com/api ; sans clé, l'onglet l'explique.
> Le constructeur n'a plus aucune fonction marquée « bientôt ».
> Correctif au passage : les adresses de la bibliothèque passent de `/images`
> à `/media-library`. Le dossier `public/images` (logos du site) faisait
> servir `/images` par le serveur web avant Laravel : « Mes images » ne
> chargeait jamais hors des tests. Un test vérifie désormais qu'aucune route
> ne porte le nom d'un dossier de `public/`. Autre correctif, trouvé en
> essayant l'import réel avec la clé Pexels : supprimer une image de « Mes
> images » renvoyait une erreur 500, la liaison implicite de la route
> cherchant l'image avant que l'organisation courante soit posée (les tests
> la laissaient posée depuis leur préparation). L'image est désormais
> cherchée après les middlewares, comme partout ailleurs dans le projet.
> Limites connues : une même photo importée deux fois crée deux images dans
> « Mes images » ; pas de filtre d'orientation ni de couleur.

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

> **Constructeur à blocs, étape B (livrée)** :
> - **Organisation de l'écran** : palette « Questions du formulaire » (glisser-déposer ou clic) et aperçu du parcours invité dans l'ordre réel (logo, bienvenue, coordonnées et réponse, questions, confirmation, refus), en mode ordinateur ou mobile.
> - **Blocs** : chaque question se règle, se duplique, monte, descend ou se retire, avec annulation possible du retrait.
> - **Réglages d'une question** : Question, Description, « Demander si », tags, Obligatoire, logique conditionnelle, Annuler/Enregistrer.
> - **Thème** : image de fond, logo avec fenêtre d'envoi, 5 couleurs, 2 polices.
> - **Aperçu** : on y simule la réponse « Je ne peux pas venir ».
> - **Enregistrement** : sauvegarde automatique ; la création, la révision et le brouillon s'écrivent dans une transaction, et une règle circulaire est refusée avec un message clair.
> - **Affiché « Bientôt »** : image et vidéo dans un bloc texte, question par personne du groupe, accompagnants, CSS personnalisé, « Mes images » et bibliothèque d'images, ainsi que les blocs du lot 2.

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

> **Accompagnants nommés, étape C du formulaire (livrée)** :
> - **Réglage** « Accompagnants maximum par invité » (0 à 20, 0 par défaut). L'invité ajoute ses accompagnants par prénom et nom ; cela fonctionne aussi sans JavaScript.
> - **Capacité** : chaque personne compte pour une place, liste d'attente comprise, et chaque option à quota est réservée par personne.
> - **Questions** « posées à chaque personne » (réglage du bloc) : une réponse par accompagnant, avec la même validation et la même logique conditionnelle que pour le titulaire.
> - **QR** : un QR signé et révocable par personne (§4.6) sur l'écran de confirmation. Le scan de l'accueil accepte ces QR comme ceux des billets, et une annulation les révoque.
> - **Accueil et export** : liste de check-in avec « (avec {titulaire}) » ; export avec le nombre de personnes et les accompagnants.
> - **Modification** : noms et réponses des accompagnants modifiables par le lien de l'invité ; leur nombre ne change pas.
> - **Non livré** : le +1 anonyme, écarté au profit des +X nommés (décision produit).

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

**Livré avec le formulaire d'inscription (lot 2, étape E)** : blocs « Don »
(devise choisie dans le bloc, montants proposés, montant libre, cause) et
« Informations sur le donateur » (nom prérempli, entreprise, adresse, case
« rester anonyme ») dans le constructeur. À la confirmation, le don promis
devient une commande sans billet (CreateDonationOrder, liée à l'inscription
par orders.registration_id), réglée par carte, Mobile Money ou à l'accueil
via le parcours de paiement existant. Le reçu part par e-mail au paiement
(événement OrderPaid émis par MarkOrderPaid → SendDonationReceipt). Les
informations du donateur ne sont exigées que d'un invité qui donne ; le don
ne se modifie plus depuis le lien « Modifier mon inscription ».
Limites connues : l'effacement RGPD n'anonymise pas encore commandes et dons
(même limite qu'Order.buyer_*) ; un don dont le paiement échoue ne peut pas
être relancé depuis la page de paiement.

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

**Complément (lot 2, étape G)** : l'export des inscriptions propose une
colonne par question du formulaire (clés préfixées `question:`), avec la même
lecture que l'écran des réponses — réponses des accompagnants précédées de
leur prénom (`App\Support\Registration\CollectRegistrationAnswers`).

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

Ticket sans critère d'acceptation dans le backlog d'origine. Format arbitré
avec l'utilisateur avant construction : une page « Aide » dans
l'organisateur (`/help`, accessible à tout membre authentifié quel que soit
son rôle, lien de navigation ajouté en tête de menu) plutôt que des
documents Markdown séparés — contenu rédigé en dur dans la page React
(`resources/js/organizer/Pages/Help/Index.tsx`), pas de CMS ni de contenu
piloté par la base pour ce périmètre MVP.

Trois sections, reflétant les fonctionnalités réellement livrées jusqu'à
T-076 (pas de fonctionnalité promise à tort) : un guide de démarrage en 6
étapes, huit tutoriels dépliables sur les parcours principaux (créer un
événement, formulaire, contacts, communications, billetterie, check-in,
résultats, organisation/RGPD), et une FAQ de 8 questions couvrant en
particulier les trois différenciateurs (§1 CLAUDE.md) : check-in hors
ligne, Mobile Money, WhatsApp.

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

**Non réalisable par ce logiciel seul, décision prise avec l'utilisateur** :
ce ticket décrit un vrai événement physique de 200 à 500 personnes, avec une
équipe sur place — aucun des quatre AC ci-dessus ne peut être coché par du
code ou simulé sans fabriquer un faux résultat. Ce qui pouvait réellement
être préparé a été livré à la place :
- `tests/Pilot/CheckInLoadTest.php` (nouvelle testsuite CI dédiée, comme
  `tests/Concurrency`/`tests/Performance`) : 300 check-ins un par un puis un
  lot de 50 resynchronisés en une requête, avec 300 billets déjà en base,
  pour prouver que le serveur seul ne peut pas être la cause d'un
  dépassement du seuil de 8 s — la marge mesurée est très large (~65 ms par
  check-in), le reste du budget de 8 s appartient au geste humain sur le
  terrain, hors de portée d'un test automatisé.
- `docs/evenement-pilote-checklist.md` : checklist opérationnelle de J-30 au
  lendemain de l'événement.
- `docs/evenement-pilote-compte-rendu-gabarit.md` : gabarit de compte rendu
  avec les niveaux de sévérité S1-S4 du §18.2, à remplir sur le terrain.

**Les quatre AC restent donc à cocher par l'organisation elle-même**, après
un vrai événement pilote mené avec ces outils.

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

# HORS BACKLOG INITIAL — DEMANDÉ APRÈS LE RÉCAPITULATIF

### Page Paramètres (compte) · partie 1/2

Suite au récapitulatif produit (proposition n°1), demandé directement par
l'utilisateur : une zone `/settings` unifiée avec menu latéral, regroupant
les pages de réglages déjà existantes (Personnalisation = charte
graphique, Facturation, Journal d'audit, Registre des traitements —
migrées vers ce même menu) et une nouvelle page **Compte** :

- Modifier nom/e-mail (l'e-mail redevient non vérifié et un nouveau lien de
  vérification part automatiquement en cas de changement)
- Changer le mot de passe (confirmation par le mot de passe actuel)
- Se déconnecter
- Supprimer le compte — anonymisation façon RGPD (même principe que
  `AnonymizeContact`, T-075), **bloquée** tant que l'utilisateur est seul
  membre au rôle Owner d'une organisation non supprimée (message clair
  plutôt qu'une organisation orpheline) ; ajout d'un `softDeletes()` sur
  `users` pour rester cohérent avec la règle 4.5 du CLAUDE.md (jamais de
  DELETE physique)

### Page Paramètres · partie 2/2 — Intégrations & API

Nouvelle section `/settings/api` (visible seulement avec la nouvelle
capacité `manageIntegrations`, Owner/Admin — même niveau que la charte
graphique). Distincte des intégrations Stripe/Twilio/Flutterwave qui
restent configurées côté serveur, invisibles à l'organisateur.

- **Clés API personnelles** : réutilise directement Sanctum
  (`User::HasApiTokens`, déjà utilisé par l'application mobile de
  check-in) — créer/lister/révoquer des jetons nommés, donnant accès aux
  mêmes points d'entrée que l'app de check-in (`/api/v1/events/{event}/...`).
  Le jeton en clair n'est affiché qu'une fois, à la création.
- **Webhooks sortants** : nouvelle table `webhooks` (organisation, URL,
  événements souscrits, secret HMAC — RLS activée comme sur toute table
  métier). Un listener par domaine émetteur (`App\Listeners\Webhooks\*`,
  enregistré explicitement dans `AppServiceProvider`, cohérent avec
  `withEvents(discover: false)`) traduit un événement Laravel en appel à
  `DispatchWebhooksForEvent`, qui met en file un `DeliverWebhookJob` par
  webhook concerné — signature HMAC-SHA256 du corps (en-tête
  `X-Itaza-Signature`, même principe que les webhooks entrants Stripe/
  Twilio dans l'autre sens), `X-Itaza-Delivery-Id` stable sur les réessais
  (règle 4.4 : rejouable sans dupliquer côté destinataire), 5 tentatives
  avec délai croissant (30 s / 2 min / 10 min / 30 min).
- **Événements disponibles limités à 4** (`registration.created`,
  `registration.updated`, `registration.cancelled`, `waitlist.promoted`) :
  ce sont les seuls événements Laravel réellement émis dans le code
  aujourd'hui. Ni Ticketing (paiement reçu) ni CheckIn (arrivée
  enregistrée) n'émettent encore d'événement Laravel — limite connue,
  documentée dans l'interface plutôt que masquée ; à étendre si ces
  domaines gagnent leurs propres événements plus tard.
- Édition d'un webhook existant limitée pour l'instant au bascule
  actif/inactif depuis la liste — modifier l'URL ou les événements
  souscrits d'un webhook déjà créé nécessite de le supprimer et d'en
  recréer un (pas d'écran d'édition dédié dans cette première version).

### Pied de page organisateur et pages légales

Demandé par l'utilisateur à partir d'une capture d'écran de référence
(RSVPify) : un pied de page sur toutes les pages de l'organisateur, avec
les mêmes rubriques (adaptées à Itaza).

- `Footer.tsx`, ajouté à `OrganizerLayout` (donc sur toutes les pages
  organisateur) : logo, liens, copyright, icônes réseaux sociaux —
  désactivées (`aria-disabled`) tant qu'aucun compte réel n'existe, plutôt
  que pointées vers des comptes qui n'existent pas.
- Six nouvelles pages publiques (`Guest\StaticPageController`, sans
  authentification, comme `/status`) : `/support`, `/community`,
  `/feedback`, `/terms`, `/privacy`, `/affiliates`.
- **Communauté** et **Programme d'affiliation** : décision prise avec
  l'utilisateur — ce sont de vraies fonctionnalités chez RSVPify (forum,
  suivi de parrainage), hors périmètre ici ; pages d'information simples
  avec contact par e-mail à la place.
- **Conditions d'utilisation** et **Politique de confidentialité** :
  décision prise avec l'utilisateur — gabarit standard rédigé, marqué
  « Brouillon, non validé par un juriste » en tête de page, avec les
  champs à compléter (raison sociale, siège, juridiction) laissés entre
  crochets. La page de confidentialité réutilise directement
  `GetProcessingRegister` (même source que le registre des traitements
  organisateur, T-075) pour son tableau des traitements — jamais de texte
  dupliqué à la main, aucun risque de divergence entre les deux pages.
  **Ne pas publier tel quel : relecture juridique requise avant mise en
  production.**

**Enrichi ensuite** à partir des pages réelles équivalentes de RSVPify
(liens fournis par l'utilisateur), lues directement dans le navigateur —
jamais de texte copié (droits d'auteur), seulement la structure et les
sujets couverts repris pour rédiger un contenu original :
- Soutien : entrées organisées par thème (démarrage, formulaires,
  billetterie, communications, check-in, compte) plutôt qu'un seul
  paragraphe.
- Retour d'information : trois catégories de retour (bug, idée de
  fonctionnalité, retour général) pour aider à écrire un message utile.
- Programme d'affiliation : les quatre étapes génériques d'un programme de
  parrainage (rejoindre, partager, suivre, être rémunéré) — sans
  fabriquer de taux de commission ni de chiffres, puisque le programme
  n'existe pas encore.
- Conditions d'utilisation et confidentialité : sommaire avec ancres
  ajouté en tête de page (repris de la structure RSVPify), et quatre
  sections supplémentaires pertinentes pour Itaza (éligibilité, services
  tiers, indemnisation, délai de réclamation côté conditions ;
  transferts internationaux de données et protection des mineurs côté
  confidentialité) — toujours un gabarit à faire valider par un juriste.

**Retour de l'utilisateur sur ce premier jet** : contenu jugé trop
générique, pages trop pâles visuellement, et « Communauté » attendue comme
un vrai espace d'échange plutôt qu'une page d'information — décision prise
avec l'utilisateur (forum minimal, pas de votes ni de modération avancée
pour cette version) :

- **Nouveau module `Domain/Community`** : `CommunityTopic` et
  `CommunityPost`, volontairement **hors cloisonnement multi-tenant**
  (comme `system_status_checks`, T-076) — un espace partagé entre TOUTES
  les organisations utilisant Itaza, pas une donnée d'une organisation en
  particulier. Ni `BelongsToOrganization` ni RLS sur ces deux tables (elles
  n'ont pas de colonne `organization_id`), conformément à
  `DomainModelsRespectMultiTenancyTest` qui n'exige le trait que pour les
  modèles possédant cette colonne.
- 4 catégories fixes (Gestion d'événement, Construction de formulaire,
  Dépannage, Suggestions). Tout compte organisateur authentifié peut créer
  un sujet et y répondre, quel que soit son rôle ou son organisation —
  `CommunityController`, routes `/community`, `/community/nouveau-sujet`,
  `/community/{topic}`.
- Remplace l'ancienne page d'information statique (`static.community`,
  supprimée) ; le pied de page et le menu principal pointent maintenant
  vers le vrai forum.
- **Pied de page et en-tête sur les pages publiques** : nouveau
  `guest.static-layout` (logo, lien connexion/tableau de bord, pied de page
  complet) utilisé par les 6 pages du pied de page + `/status` — **jamais**
  appliqué à `guest.layout` (RSVP, billetterie, désabonnement), qui reste
  volontairement minimal pour respecter la contrainte de poids/vitesse en
  3G (§2 CLAUDE.md).
- **Habillage visuel** : bandeau d'en-tête teinté (`bg-accent/5`) avec
  étiquette et grand titre en serif italique sur chaque page, icônes dans
  des pastilles pour les cartes de contenu — mêmes jetons de couleur que
  l'application organisateur (`--color-danger`/`--color-success` ajoutés à
  `guest.css`, absents jusqu'ici de ce bundle CSS séparé et minimal).

### Mode d'affichage choisi par l'organisation, plus par le système

Demandé par l'utilisateur : le mode sombre de l'espace organisateur suivait
jusqu'ici uniquement la préférence système (`prefers-color-scheme`, sans
bouton — décision documentée à l'origine dans `app.css`). Désormais
piloté explicitement par organisation, « Normal » (clair) par défaut :

- Colonne `organizations.theme_mode` (`light`/`dark`, défaut `light`),
  nouvel enum `ThemeMode`. Réglage ajouté au formulaire déjà existant de
  Paramètres → Personnalisation (`UpdateOrganizationBranding`).
- `resources/views/app.blade.php` pose `data-theme` sur `<html>` **avant**
  tout rendu React (`App\Support\Theme\ResolveThemeMode` : lit
  l'organisation courante si résolue, sinon `light` — jamais de flash du
  mauvais thème, y compris sur les pages avant authentification comme
  `/login`).
- `app.css` : le bloc `@media (prefers-color-scheme: dark)` est remplacé
  par un sélecteur `[data-theme='dark']` — la préférence système n'a plus
  aucun effet, seul ce réglage compte. Les pages vues par les invités
  (`guest.css`) ne sont pas concernées, elles n'ont jamais eu de mode
  sombre.
- Changer de mode recharge la page entière (`window.location.reload()`) :
  `data-theme` n'est posé qu'au chargement complet du document, une
  navigation Inertia classique ne le reverrait pas.

### Tableau de bord refondu en galerie d'événements

Demandé par l'utilisateur à partir d'une seconde capture RSVPify : la page
d'accueil organisateur n'est plus une vue d'ensemble par service (cartes
Contacts/Événements/Imports/Journal d'audit) mais une galerie de ses
propres événements, recherche et onglets Actuel/Passés inclus — Contacts,
imports et journal d'audit restent accessibles par leurs propres liens de
navigation, rien n'est perdu, seulement retiré de la page d'accueil.

- `GetOrganizationEventSummaries` (Support/Dashboard, même raison
  d'existence que `GetEventDashboardStats`) : bannière (Domain/Page),
  statut de cycle de vie (`Event::computedStatus()`) et répartition
  confirmées/liste d'attente/annulées (agrégation `GROUP BY`, jamais
  ligne à ligne) pour chaque événement de l'organisation.
- Recherche et bascule Actuel/Passés en clientside (filtrage JS sur la
  liste déjà chargée) — pas d'aller-retour serveur, le nombre
  d'événements d'une organisation reste modeste pour ce MVP.
- Vocabulaire aligné sur le reste de l'application (« Confirmées », «
  Liste d'attente », « Annulées » — pas les termes anglicisés de
  RSVPify) ; carte sans bannière : lettre initiale du titre plutôt qu'une
  illustration générique, faute d'illustration propre à Itaza.

### En-tête organisateur : logo, mise à niveau, menu utilisateur

Demandé par l'utilisateur à partir d'une troisième capture RSVPify (menu
utilisateur déplié) : remettre le vrai logo au lieu d'un texte sur les
pages statiques du pied de page, et réorganiser l'en-tête organisateur
autour d'un bouton « Mise à niveau », d'une icône cadeau, et d'un menu
utilisateur déroulant.

- `guest.static-layout.blade.php` et `guest/partials/footer.blade.php` :
  logo image (`/images/logo.png`) au lieu du nom de l'application en
  texte, en-tête et pied de page.
- Nouvelle page publique « Partage d'événements » (`/partage-evenements`,
  `StaticPageController::eventSharing`) : les trois canaux de partage déjà
  disponibles dans l'application (lien de la page événement, QR code du
  billet, invitations e-mail/WhatsApp) — nécessaire comme destination du
  nouveau lien « Partage d'événements » du menu utilisateur.
- `OrganizerLayout.tsx` : le lien « Se déconnecter » isolé est remplacé
  par un menu déroulant (avatar à initiale + nom, réutilise le motif
  clic-en-dehors déjà utilisé par `NavGroup`) contenant Mon compte,
  Partage d'événements, Obtenez du soutien (`/support`), Forum
  communautaire (`/community`), Déconnexion. Le bouton « Mise à niveau »
  (`/billing`) n'apparaît que si `settingsAccess.billing` est vrai (rôle
  Propriétaire), pour ne pas exposer un lien qui renverrait un 403 aux
  autres rôles.
- Navigation principale simplifiée en conséquence
  (`HandleInertiaRequests::buildNav`) : « Tableau de bord » renommé « Mes
  événements » (cohérent avec le nouveau tableau de bord en galerie), «
  Aide » et « Communauté » retirés du niveau supérieur puisqu'ils vivent
  maintenant dans le menu utilisateur.
- **Corrigé au passage** : les classes `dark:` de `Logo.tsx` suivaient
  encore `prefers-color-scheme` du système au lieu du réglage explicite
  `data-theme` de l'organisation (régression latente du chantier « mode
  d'affichage » ci-dessus, jamais remarquée faute d'utiliser `dark:`
  ailleurs dans le bundle organisateur) — `app.css` redéfinit maintenant
  la variante `dark:` de Tailwind via `@custom-variant` pour suivre
  `[data-theme='dark']`.
- **Corrigé après vérification mobile** : le bouton « Mise à niveau », le
  nom complet de l'utilisateur et l'icône cadeau ne tenaient pas sur un
  viewport 375px et provoquaient un défilement horizontal de toute la
  page. Le nom est masqué en dessous de `md`, le bouton « Mise à niveau »
  en dessous de `sm` (l'avatar et l'icône cadeau restent toujours
  visibles et cliquables).
- **Hors périmètre, repéré en vérifiant le mobile** : le champ de
  recherche du tableau de bord (« Trouve ton événement… ») est tronqué
  sur petit écran (`Dashboard.tsx`, chantier précédent) — non corrigé ici,
  à traiter dans un ticket dédié.

### Genre d'événement et refonte de « Mise à niveau »

Demandé par l'utilisateur à partir d'une nouvelle série de captures RSVPify
(page de tarification avec onglets, comparatif, FAQ) : ajouter un genre
d'événement choisi à la création, et adapter la page « Mise à niveau » en
conséquence. Deux décisions ont été confirmées avec l'utilisateur avant
de commencer (impact argent réel via Stripe) :

- Le genre (Professionnel/Communautaire/Personnel) **s'ajoute** à l'étape
  « Type » existante (bibliothèque de modèles à 16 entrées, M1.4) plutôt que
  de la remplacer — les deux servent des besoins différents.
- La page « Mise à niveau » est une **réorganisation visuelle** : toujours
  les 3 plans Stripe existants (T-074), aucun nouveau produit Stripe créé.

Mis en œuvre :

- `App\Domain\Event\Models\EventAudience` (nouvel enum : `professional` /
  `community` / `personal`), colonne `events.audience` (migration
  `2026_09_21_100000_add_audience_to_events_table.php`, défaut
  `professional` pour les événements existants). Champ nullable côté
  validation (`CreateEventRequest`/`UpdateEventRequest`), mais requis dans
  l'assistant de création (étape 1, à côté du choix de modèle) — même
  convention que le champ `type` existant.
- `config/plans.php` : les libellés de prix (placeholders documentés comme
  « à remplacer avant production », T-074) passent de 29 $/99 $ à 8,9 $/14 $
  par mois, chiffres donnés par l'utilisateur pour l'offre « Événements
  personnels ».
- `Billing/Show.tsx` refondu : 3 onglets (Événements personnels / Plans
  professionnels / Événements sur billet). Le panneau « Consommation ce
  mois-ci » et la gestion d'abonnement Stripe restent inchangés en haut de
  page.
  - **Événements personnels** (3 cartes) : les 3 plans réels tels quels
    (Gratuit/8,9 $/14 $), argumentaire pour un usage individuel.
  - **Plans professionnels** (4 cartes) : les 3 mêmes plans réels
    **relabellisés** « Démarreur »/« En plus »/« Carrière professionnelle »
    avec un argumentaire entreprise — même prix, même abonnement Stripe que
    l'onglet personnel, seul le nom de la formule change — plus une 4ᵉ carte
    « Entreprise / Sur mesure » sans prix, bouton « Nous contacter » vers
    `/support` (pas de Price ID Stripe : comme chez la plupart des éditeurs,
    ce palier se négocie, il ne s'achète pas en un clic).
  - **Événements sur billet** : panneau d'explication, pas de carte — Itaza
    ne prélève aucune commission propre sur les billets (contrairement à
    RSVPify, vérifié dans le code : aucune logique de frais de plateforme
    n'existe dans `Domain/Ticketing`), seuls les frais du prestataire de
    paiement choisi s'appliquent. Choix délibéré de ne pas inventer un taux
    de commission qui n'existe pas.
  - Bloc « Inclus dans tous les plans » et FAQ (4 questions) écrits pour
    refléter le comportement réel du système (alertes de quota à 80/100 %,
    jamais de blocage dur ; restriction uniquement en cas d'échec de
    paiement prolongé) plutôt que d'imiter le grand tableau comparatif
    ligne par ligne de RSVPify — un tel tableau supposerait des
    fonctionnalités réservées à certains plans, ce qui n'existe pas dans
    Itaza aujourd'hui (seuls les volumes mensuels diffèrent).

**Revu ensuite** à partir d'une nouvelle série de captures (chiffres et
listes de caractéristiques détaillés par palier) : l'utilisateur a cette
fois demandé de **vrais prix à facturer**, distincts entre l'onglet
personnel et l'onglet professionnel — ce qui revient sur la décision
« aucun nouveau produit Stripe » prise juste avant. Confirmé explicitement
avant de coder (impact argent réel) :

- `PlanTier` passe de 3 à **6 paliers réels**, chacun avec son propre Price
  ID Stripe : `free` (inchangé), `personal_essential` (8,9 $/mois),
  `personal_comfort` (15 $/mois), `professional_plus` (35 $/mois),
  `professional_career` (99 $/mois), `professional_pro` (289 $/mois).
  L'onglet personnel et l'onglet professionnel ne partagent donc plus les
  mêmes paliers Stripe qu'avant. « Entreprise / Sur mesure » reste sans
  Price ID (bouton « Nous contacter », comme la plupart des éditeurs SaaS
  pour ce palier).
- `config/plans.php` : 6 entrées, quotas dérivés des chiffres donnés
  (inscriptions/mois pour le professionnel, nombre d'invités réinterprété
  comme quota d'inscriptions mensuel pour le personnel faute de suivre un
  quota "invités par événement" séparé aujourd'hui ; e-mails extrapolés à
  5× les inscriptions, même ratio que les paliers d'origine).
- **`.env.example`** : `STRIPE_PRICE_PRO`/`STRIPE_PRICE_BUSINESS` remplacés
  par 5 variables (`STRIPE_PRICE_PERSONAL_ESSENTIAL`,
  `STRIPE_PRICE_PERSONAL_COMFORT`, `STRIPE_PRICE_PROFESSIONAL_PLUS`,
  `STRIPE_PRICE_PROFESSIONAL_CAREER`, `STRIPE_PRICE_PROFESSIONAL_PRO`).
  **Action requise côté utilisateur, hors de portée du code** : créer les 5
  produits/prix correspondants dans le tableau de bord Stripe, puis coller
  chaque Price ID dans `.env` — tant que ce n'est pas fait, le clic sur
  « Mise à niveau » échoue proprement avec un message clair
  (`StripeNotConfiguredException`, mécanisme déjà en place depuis T-074),
  jamais un plantage.
- Bascule **Mensuel/Annuel** sur la page : décision utilisateur explicite
  — **affichage seulement** pour l'instant. Les montants annuels (remise
  échelonnée 39 % sur le palier d'entrée de chaque famille, 29 % sur le
  palier intermédiaire, 19 % sur le palier le plus cher, à l'intérieur de
  la fourchette 19–39 % demandée) sont des chiffres fixes écrits une fois
  dans le composant, pas recalculés dynamiquement — aucun Price ID annuel
  Stripe n'existe encore, le paiement reste sur l'abonnement mensuel quel
  que soit l'état de la bascule.

### Navigation principale réduite à « Mes événements »

Demandé par l'utilisateur juste après le chantier précédent : retirer les
groupes Contacts, Événements, Communications et Paramètres du niveau
supérieur de navigation, et transformer « Mes événements » en menu
déroulant listant tous les événements de l'organisation.

- `HandleInertiaRequests::buildNav()` ne renvoie plus qu'un seul groupe
  (« Mes événements ») : « Tous les événements » (vers le tableau de
  bord) puis chaque événement de l'organisation par titre (le plus
  récent d'abord), chacun vers sa page d'édition
  (`events/{event}/edit`) — même destination que les cartes du tableau
  de bord.
- **Conséquence signalée, non résolue ici** : Contacts (liste, ajout,
  import, tags) et Communications (modèles e-mail/WhatsApp) n'ont plus
  aucun point d'entrée dans l'interface — seulement accessibles par URL
  directe. Paramètres reste atteignable via « Mon compte » dans le menu
  utilisateur, donc pas concerné. Il n'existe pas non plus aujourd'hui de
  tableau de bord par événement avec sa propre sous-navigation
  (`EventDashboard/Show.tsx` n'affiche que des statistiques) qui
  pourrait accueillir ces liens en contexte. À trancher avec
  l'utilisateur : où faire vivre ces deux fonctionnalités (nouveau menu,
  sous-navigation par événement, ou autre) ?

### Refonte de « Mon compte » : Sécurité, Notifications, Étiquetage blanc, Refer-a-Friend

Demandé par l'utilisateur à partir d'une nouvelle série de captures RSVPify
(page Mon compte à onglets) : réorganiser les Paramètres autour de la même
structure, et construire quatre fonctionnalités qui n'existaient pas du
tout côté Itaza. Trois décisions confirmées avec l'utilisateur avant de
coder (sécurité + argent réel) :

- MFA : deux réglages distincts, comme sur les captures — personnel
  (`users.mfa_email_enabled`) et politique d'organisation pour tous les
  membres (`organizations.require_mfa_for_members`, Propriétaire
  uniquement, nouvelle capacité `manageSecurity`).
- Étiquetage blanc : nom d'expéditeur + adresse de réponse personnalisés
  uniquement — pas de vérification de domaine SPF/DKIM (demanderait de
  choisir un prestataire d'e-mail dédié et ses identifiants).
- Parrainage : la récompense (« un mois gratuit ») prolonge
  `subscription_current_period_end` de 30 jours pour le parrain et le
  filleul, plutôt qu'un coupon Stripe — aucune configuration Stripe
  supplémentaire nécessaire.

**Navigation** (`SettingsLayout.tsx`) : Mon compte, Sécurité,
Notifications, Personnalisation, Facturation, Intégrations, Étiquetage
blanc, Refer-a-Friend, Journal d'audit, Registre des traitements — les
trois dernières colonnes n'existent pas chez RSVPify mais restent des
fonctionnalités Itaza réelles, conservées plutôt que masquées.

**Mon compte** (`Settings/Profile.tsx`) : ne garde plus que le profil, la
session et la suppression de compte ; gagne un résumé « Plan actuel »
(réutilise `GetOrganizationUsage`, déjà utilisé par la page Facturation).
Le mot de passe déménage entièrement vers Sécurité.

**Sécurité** (`Settings/Security.tsx`, `SecurityController`) : mot de
passe (déplacé depuis Mon compte) + les deux réglages MFA.

**Connexion en deux étapes** : `AttemptLogin` annule la connexion
(`Auth::logout()`) dès que la MFA est requise et stocke le défi en
session (code haché, expiration à 10 min) plutôt qu'en base — rien à
purger, il expire avec la session. Nouvelles routes `login/mfa` (état
« invité », comme `/login`), `VerifyMfaCode` (vérifie + limite les
tentatives, même mécanisme que `AttemptLogin`) et `ResendMfaCode`.
`IssueCheckInApiToken` (postes de check-in) est resté totalement
indépendant de `AttemptLogin` dès l'origine — aucun risque que la MFA
bloque le check-in hors ligne.

**Notifications** (`Settings/Notifications.tsx`, `NotificationController`)
: interrupteur général (`users.registration_notifications_enabled`) + une
ligne par événement dans `registration_notification_preferences`,
seulement créée quand un organisateur s'écarte du défaut (tout activé).
Nouveau listener `NotifyOrganizersOfRegistration`, branché sur les 3
événements réellement émis par `Domain/Form` (`RegistrationCreated`,
`RegistrationUpdated`, `RegistrationCancelled`) à côté de
`DispatchRegistrationWebhooks` — synchrone comme les autres ponts
inter-modules (voir le docblock de `SendConfirmationEmail`), seul l'envoi
de chaque e-mail est mis en file (le Mailable ne porte que des chaînes).

**Étiquetage blanc** (`Settings/WhiteLabel.tsx`, `WhiteLabelController`,
capacité `manageBranding`) : `organizations.email_from_name` /
`email_reply_to`, appliqués jusqu'au bout de la chaîne d'envoi
(`SendEmailMessageJob` → `GenericMail`) — seul le nom affiché change,
jamais l'adresse technique d'expédition, pour ne pas dégrader la
délivrabilité sans SPF/DKIM.

**Refer-a-Friend** (`Settings/Referral.tsx`, `ReferralController`, capacité
`manageBilling`) : `organizations.referral_code` (généré à l'inscription,
rattrapé à la volée pour les organisations plus anciennes),
`referred_by_organization_id` capturé une fois pour toutes via
`?via=CODE` sur `/register`. `ApplyReferralReward` s'exécute au premier
paiement confirmé du filleul (`checkout.session.completed`, après la mise
à jour du plan) ; `referral_rewarded_at` rend l'opération idempotente
(règle 4.4). Aucune prolongation pour un compte encore au plan gratuit
(rien à prolonger sans abonnement Stripe actif).

### Correctif : résolution de l'organisation courante

Trouvé en relisant les logs après le chantier précédent (deux problèmes
préexistants dans `ResolveCurrentOrganization`, sans lien avec les
fonctionnalités ajoutées) :

- **Erreur 500 non gérée** : un compte sans aucune adhésion laissait le
  middleware passer sans organisation, puis la première lecture cloisonnée
  levait `MissingOrganizationContextException` en pleine page. Le cas est
  anormal (les deux parcours d'inscription créent toujours une
  organisation) mais atteignable si la dernière adhésion est retirée.
  Désormais un 403 explicite.
- **Isolation multi-tenant (règle 4.1)** : `current_organization_id` était
  lu depuis la session sans revérifier l'adhésion. Un membre retiré d'une
  organisation gardait donc l'accès en lecture à ses données jusqu'à
  l'expiration de sa session — les lectures cloisonnées ordinaires
  (tableau de bord, contacts...) ne passent par aucune policy, seul le
  scope organisation les filtre. L'adhésion est maintenant revérifiée à
  chaque requête, et une session pointant vers une organisation quittée
  retombe sur la première adhésion restante.

### Finalisation de « Mon compte » d'après les captures

Demandé par l'utilisateur (« suis à la lettre ces captures ») après une
série de captures des sept onglets Mon compte de RSVPify :

- **Navigation en onglets horizontaux** à la place de la barre latérale
  (`SettingsLayout.tsx`) — c'est le point structurel commun aux six
  captures.
- **Composant `Toggle`** (interrupteur) : les captures utilisent des
  interrupteurs, pas des cases à cocher. Utilisé par Sécurité et
  Notifications.
- **Notifications** : tableau complété d'une colonne « enveloppe » qui
  bascule les trois réglages d'un événement d'un coup, d'une colonne date
  (affichée dans le fuseau de l'événement, règle 4.3) et d'un tri sur le
  nom et la date.
- **Refer-a-Friend** : mise en page deux colonnes, partage Facebook /
  Twitter / LinkedIn, et **invitation par e-mail** (nouvelle route
  `settings/referral/invitations`, `ReferralInvitationMail`, limitée à 10
  envois par minute).
- **Étiquetage blanc** : encart d'accompagnement, illustration et état du
  compte repris des captures, le formulaire nom d'expéditeur / adresse de
  réponse restant la partie réellement fonctionnelle.
- **Facturation** : blocs « Abonnement » et « Historique de la
  facturation » ajoutés en tête, avec bouton « Voir les forfaits et les
  tarifs » ancré sur la grille tarifaire. L'historique renvoie au portail
  Stripe, seul détenteur des factures — aucune table d'historique locale.
- **Intégrations** : en-tête « Intégrations » + contact commercial repris
  des captures.

**Deux éléments des captures volontairement non repris** :

- Les logos clients (« Digne de confiance : Volvo, Verizon, Starbucks… »)
  sont de vrais clients de RSVPify — les afficher chez Itaza serait une
  fausse référence commerciale.
- Les intégrations tierces listées (Zapier, Google Analytics, Facebook
  Pixel, Salesforce, HubSpot, Blackbaud) n'existent pas dans Itaza ; la
  page continue de présenter les intégrations réelles (clés API, webhooks
  sortants). Le badge « Offre à durée limitée » du parrainage devient
  « Offre permanente », faute d'échéance réelle.

### Intégration Zapier et n8n

Demandé par l'utilisateur. Les deux outils se branchent sur les webhooks
sortants existants ; le chantier ajoute ce qui manquait pour que la
connexion soit franche des deux côtés.

- **API REST Hooks** (`/api/v1/hooks`) : `POST` pour s'abonner,
  `DELETE {id}` pour se désabonner, `GET sample?event=…` pour la charge
  utile d'exemple dont Zapier et n8n ont besoin afin de proposer les
  champs à mapper. C'est le contrat qu'attend la plateforme Zapier quand
  un utilisateur active ou désactive un Zap.
- Les abonnements créés par API sont de **vrais `Webhook`** : mêmes
  livraisons signées (HMAC-SHA256), et visibles/révocables depuis
  Paramètres → Intégrations comme ceux créés à la main.
- `target_url` est restreinte à **https** : la charge utile contient des
  données personnelles d'invités (nom, e-mail).
- Nouveau middleware `resolve-api-organization` : les points d'entrée
  d'abonnement ne portent aucun identifiant d'événement, contrairement à
  `resolve-api-check-in-event`. L'organisation est donc résolue via la
  première adhésion du porteur de la clé, et `manageIntegrations` est
  vérifiée. **Limite documentée dans l'interface** : une clé pilote une
  seule organisation, il en faut une par organisation.
- `WebhookSamplePayload` est volontairement aligné sur ce qu'émettent les
  listeners réels — un exemple divergent produirait des mappings cassés
  chez l'utilisateur sans erreur visible côté Itaza.
- **Interface de connexion par clé API** (demande utilisateur) : un bouton
  « Créer la clé de connexion » par outil génère une clé nommée
  (« Connexion Zapier », « Connexion n8n ») et l'affiche une seule fois,
  avec les étapes exactes pour la coller dans l'outil. La clé n'est pas
  répétée dans la section « Clés API » plus bas quand elle vient d'être
  créée depuis ce bloc.
- **Point d'entrée de test `GET /api/v1/me`** : indispensable, Zapier et
  n8n valident la clé au moment de la connexion et affichent à qui elle
  appartient. Renvoie le compte et l'organisation ; une clé invalide
  ressort en 401, ce que les deux outils savent interpréter.
- Page Intégrations : guides pas à pas Zapier et n8n, et documentation de
  l'API d'abonnement avec l'URL de base calculée côté serveur.

**Connexion par la clé de l'outil (n8n)** — demande utilisateur : coller
dans Itaza la clé API de l'outil, et non l'inverse. Vérifié dans la
documentation des deux produits avant de coder :

- **n8n : possible**, et construit. n8n expose une API publique
  authentifiée par une clé longue durée en en-tête `X-N8N-API-KEY`
  (docs.n8n.io/api/authentication). L'organisateur colle l'adresse de son
  instance et sa clé ; Itaza la valide auprès de l'instance **avant** de
  l'enregistrer, liste ensuite ses workflows, reconstruit l'URL du nœud
  Webhook de chacun et crée le webhook sortant d'un clic — plus aucune URL
  à recopier. Clé chiffrée au repos (cast `encrypted`, jamais en clair en
  base : un test le vérifie), instance en https uniquement puisque la clé
  voyage dans chaque en-tête.
- **Zapier : impossible par ce chemin.** Zapier ne délivre pas de clé API
  à ses utilisateurs finaux permettant à une application tierce d'écrire
  dans leur compte : la Workflow API « n'est pas accessible aux
  utilisateurs finaux ni aux applications sans intégration publiée » et
  suppose une app listée + OAuth 2.0. Pour Zapier, la connexion reste donc
  la clé Itaza côté Zapier et l'URL de Catch Hook côté Itaza.

**Liste des workflows n8n chargée en arrière-plan** — correctif après
livraison : la page Intégrations interrogeait l'instance n8n du client à
chaque affichage (jusqu'à 10 s par page, et bien plus sur une grosse
instance paginée), contraire à la règle « tout traitement > 2 secondes
part en queue ».

- La page ne contacte plus jamais n8n : elle lit `N8nWorkflowCache`
  (par organisation, 10 minutes). Cache vide : elle lance
  `RefreshN8nWorkflowsJob` et affiche « Récupération de vos workflows
  n8n… », puis se recharge partiellement toutes les 2 s (prop `n8n`
  seule) jusqu'à ce que la liste arrive, 30 s au plus.
- Le job est unique par organisation : plusieurs onglets ou visites
  rapprochées n'empilent pas d'appels vers l'instance du client. Une
  instance injoignable est mémorisée comme erreur et affichée, sans
  casser la page.
- Bouton « Rafraîchir » : vide le cache et relance la récupération. La
  connexion le fait aussi ; la déconnexion vide le cache.
- Seule la vérification de la clé à la connexion reste synchrone : un
  unique appel léger (`limit=1`), pour dire tout de suite si la clé est
  refusée.
- **Condition d'exploitation** : un worker de file d'attente doit tourner
  (`php artisan horizon`), sans quoi la liste reste en chargement.

**Reste à faire côté utilisateur, hors code** : publier l'app Zapier sur
le compte développeur Zapier (création de l'app, déclencheurs pointant sur
les points d'entrée ci-dessus, revue par Zapier). Sans cette étape, la
connexion passe par « Webhooks by Zapier » (Catch Hook), qui fonctionne
déjà. n8n ne demande aucune publication : le nœud Webhook suffit.

### Partage d'événements (collaborateurs)

Demandé par l'utilisateur à partir de deux captures RSVPify (page vide
« Event Sharing », fenêtre « Add Collaborator or Event Staff »). Règles
tranchées avec lui avant de coder : Administrateur = tous les droits sur
l'événement, suppression et remboursements compris ; un compte créé depuis
une invitation n'a pas d'organisation propre ; ouvert à tous les plans ;
Propriétaire et Admin invitent (`inviteMembers`, matrice M0.3).

- **Page** Paramètres → Partage d'événements (`/settings/event-sharing`,
  lien du menu utilisateur visible avec `inviteMembers`) : état vide, fenêtre
  d'ajout (e-mail, recherche d'événement, permission par événement : Aucun
  accès / Lecture seule + check-in / Administrateur, « Enregistrer » grisé
  sans aucun accès), liste avec statut, Modifier, Renvoyer l'invitation,
  Retirer.
- **Données** : `collaborators` (empreinte SHA-256 du jeton, expiration
  7 jours, suppression logique, RLS) et `collaborator_event_permissions`
  (« Aucun accès » stocké en `none`, jamais de DELETE, RLS). Nouveau rôle
  d'adhésion `collaborator`, créé à l'acceptation.
- **Droits, refus par défaut** : `RestrictCollaboratorToSharedEvents`, appelé
  par `ResolveCurrentOrganization`, refuse à un collaborateur toute page
  organisateur sauf ses événements partagés (routes enfants formulaire,
  type de billet, tarif, automatisation et participant comprises, rattachées
  à leur événement), le tableau de bord, l'aide, la communauté, Mon compte
  et Sécurité. Sur un événement partagé, `CurrentEvent` est posé et
  `OrganizationPolicy` borne les capacités à la permission : aucun contrôle
  d'accès existant n'a été modifié. Événement non partagé : 404. Duplication
  interdite, elle créerait un nouvel événement dans l'organisation.
- Tableau de bord et menu « Mes événements » filtrés : la lecture seule
  ouvre le tableau de bord de l'événement, Administrateur l'éditeur. L'API
  de check-in mobile est ouverte au collaborateur sur son seul événement.
- **Invitation** : e-mail avec lien `/invitations/{organisation}/{jeton}`,
  usage unique, 7 jours, renvoi = nouveau jeton. Page d'acceptation :
  création de compte (e-mail vérifié d'office, le lien le prouve), connexion
  puis acceptation, ou refus si la session ouverte est celle d'un autre
  compte.
- **Sélecteur « Espaces de travail »** dans le menu utilisateur dès deux
  adhésions : indispensable pour qu'un organisateur invité ailleurs voie les
  événements partagés. À la connexion, son propre espace passe avant ceux où
  l'on n'est que collaborateur.
- Journal d'audit : `collaborator.invited`, `.permissions_updated`,
  `.invitation_resent`, `.removed`, `.accepted` — jamais l'adresse e-mail en
  clair.

**Hors périmètre, à décider** : un compte créé depuis une invitation ne peut
pas encore créer sa propre organisation ; une personne invitée sans compte
qui passe par « Continuer avec Google » reçoit une organisation comme une
inscription normale ; la page publique `/partage-evenements` reste dans le
pied de page mais n'est plus liée au menu utilisateur.

**Suites de la démonstration avec un compte collaborateur fictif** :

- Le bouton « Dupliquer » est masqué pour un collaborateur (prop
  `canDuplicate`) : la duplication lui était refusée mais le bouton menait
  à une erreur 403.
- Pages d'erreur en français : `lang/fr.json` traduit les pages 401 à 503
  de Laravel, dont « This action is unauthorized. », affiché à chaque refus
  d'une policy. Nouvelle vue de repli `resources/views/errors/4xx.blade.php`
  pour les codes sans page dédiée (409, 410...) : sans elle, la production
  perdait le message français d'`abort()`, comme celui d'une invitation
  expirée.
- Vérifié, pas de défaut : la duplication d'un événement est bien refusée
  à l'éditeur, au personnel d'accueil et au lecteur (CreateEvent exige
  `createEvents`) ; un test de route le verrouille désormais.

### Interface d'un événement : liste de contrôle et menu latéral

Demandé par l'utilisateur à partir de captures RSVPify (page « guide » d'un
événement). Règles tranchées avec lui : menu latéral sur toutes les pages
d'un événement ; rubriques absentes d'Itaza affichées « Bientôt » ;
complétion automatique quand Itaza la constate, bouton sinon ; un événement
publié peut repasser en « Inédit ».

- **Après la création**, redirection vers `/events/{id}` : liste de contrôle
  de 14 étapes en quatre onglets (Personnaliser, Lancement, Organiser,
  Suivi), progression « x sur 14 », statut En attente / Complet, lien vers
  la page Itaza correspondante et « À suivre » vers l'onglet suivant.
  Textes rédigés pour Itaza, pas repris des captures.
- **Complétion** (`GetEventChecklist`) : automatique pour le site web
  personnalisé, le formulaire publié, les inscrits, les e-mails de
  confirmation/rappel, la publication, les invitations, le plan de table, le
  check-in, un export terminé et les remerciements ; sinon « Marquer comme
  terminé » (`event_checklist_marks`, décocher remet `marked_at` à null,
  jamais de DELETE). La publication ne se coche jamais à la main. Ouvrir
  l'aperçu ou copier le lien coche l'étape correspondante.
- **Menu latéral et barre du haut** (`EventLayout`, `BuildEventNavigation`
  partagé en prop `eventNav`) sur les 13 pages d'un événement : fil
  d'Ariane, menu « Inédit / Publié », aperçu, compte. Chaque lien est calculé
  côté serveur selon les capacités : un collaborateur ne voit que les pages
  qu'il peut ouvrir. « Bientôt » : événements secondaires, préférences
  alimentaires, dons et cadeaux, décors, changer d'expérience, calendrier
  d'ouverture (les dates existent en base mais aucun écran ne les modifie).
- **Publication** : `PublishEvent` enfin branché (il n'avait aucune route) et
  nouveau `UnpublishEvent` (publié → brouillon ; un archivé ne revient
  jamais). Dépublier ferme la page publique et les inscriptions, les
  inscriptions reçues restent. Changement journalisé par `Auditable`.
- **Lien de test** : aperçu signé valable 7 jours qui ouvre la page publique
  d'un brouillon (`ResolveGuestEvent`), mémorisé en session pour les étapes
  suivantes du parcours. Un lien altéré ou un événement archivé : 404.
- `ResolveRouteEventId` extrait de la restriction des collaborateurs, pour
  être partagé avec la navigation.

**Hors périmètre, à décider** : écran des dates d'ouverture/fermeture des
inscriptions ; interface des sous-événements (le modèle existe) ; les
inscriptions faites via le lien de test comptent comme de vraies réponses.

---

*Backlog v1.0 — à réviser à chaque fin de sprint.*
