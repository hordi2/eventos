# CAHIER DES CHARGES
## Plateforme SaaS de gestion d'événements
### Inscription • Invitation • Billetterie • Check-in • Analytics

---

**Document** : Cahier des charges fonctionnel et technique
**Version** : 1.0
**Date** : Août 2026
**Référence marché** : RSVPify (rsvpify.com), Cvent, Eventbrite, Swoogo, Splash
**Nom de code projet** : *EventOS* (à remplacer par le nom commercial retenu)

---

# SOMMAIRE

1. Contexte et objectifs
2. Vision produit et proposition de valeur
3. Périmètre du projet
4. Personas et cas d'usage
5. Architecture fonctionnelle générale
6. Spécifications fonctionnelles détaillées
7. Modèle de données
8. Architecture technique
9. Intégrations et API
10. Sécurité, conformité et RGPD
11. UX / UI et design system
12. Exigences non fonctionnelles
13. Modèle économique et facturation
14. Innovations et différenciateurs proposés
15. Découpage en lots et roadmap
16. Organisation projet, charges et budget
17. Recette, tests et critères d'acceptation
18. Maintenance, support et KPI
19. Annexes

---

# 1. CONTEXTE ET OBJECTIFS

## 1.1 Constat de marché

Les organisateurs d'événements — entreprises, ONG, écoles, églises, agences, particuliers — utilisent aujourd'hui un patchwork d'outils mal reliés :

| Besoin | Outil habituel | Problème |
|---|---|---|
| Collecte d'inscriptions | Google Forms | Pas de gestion d'invités, pas de capacité |
| Liste d'invités | Excel / WhatsApp | Pas de temps réel, versions multiples |
| Invitations | Mailchimp / WhatsApp manuel | Pas de lien avec les réponses |
| Paiement | Virement / Mobile Money manuel | Aucun rapprochement automatique |
| Accueil le jour J | Liste papier + stylo | Files d'attente, erreurs, aucune donnée |
| Bilan | Comptage manuel | Aucun ROI mesurable |

La conséquence : jusqu'à **60 heures de travail administratif par événement**, une expérience invité dégradée, et une perte totale de la donnée exploitable après l'événement.

## 1.2 Objectif du projet

Concevoir et développer une **plateforme SaaS unifiée** couvrant l'intégralité du cycle de vie d'un événement : de la création de l'invitation jusqu'à l'analyse post-événement, accessible aussi bien à un particulier organisant un mariage qu'à une direction marketing gérant un programme de 200 événements par an.

## 1.3 Objectifs mesurables

| Objectif | Indicateur cible |
|---|---|
| Réduire le temps de préparation | Événement en ligne en < 30 minutes |
| Fiabiliser le jour J | Check-in d'un invité en < 5 secondes |
| Monétiser sans friction | Billetterie opérationnelle sans abonnement |
| Prouver le ROI | Rapport automatique post-événement |
| Adoption | Taux de complétion du formulaire invité > 85 % |
| Rétention | > 60 % des organisateurs créent un 2e événement |

---

# 2. VISION PRODUIT ET PROPOSITION DE VALEUR

## 2.1 Promesse

> **« De l'invitation à la donnée. Une seule plateforme, du premier envoi au dernier badge scanné. »**

## 2.2 Piliers différenciants

1. **Unification** — un seul outil remplace 5 à 6 abonnements.
2. **Granularité de l'invité** — la donnée n'est pas « une inscription » mais « une personne dans un foyer / un groupe / une entreprise », avec son historique.
3. **Fonctionnement dégradé** — l'application du jour J fonctionne sans connexion internet.
4. **Paiements locaux** — Mobile Money, carte, virement, espèces enregistrées.
5. **Canaux réels** — WhatsApp et SMS au même niveau que l'e-mail.
6. **Pilotage par l'IA** — création d'événement et analyse en langage naturel.

## 2.3 Ce que le produit N'EST PAS

- Ce n'est pas une plateforme de streaming / événements virtuels (V3 éventuellement).
- Ce n'est pas un CRM complet — il s'y connecte.
- Ce n'est pas une marketplace de découverte d'événements type Eventbrite public (option V2 : « Event Hub »).
- Ce n'est pas un outil de gestion de prestataires / budget événementiel (V3).

---

# 3. PÉRIMÈTRE DU PROJET

## 3.1 Dans le périmètre

- Application web responsive (organisateur + invité)
- Application mobile de check-in (iOS + Android)
- Back-office d'administration de la plateforme
- API publique REST + Webhooks
- Site vitrine et tunnel d'acquisition
- Système de facturation et d'abonnement
- Documentation technique et utilisateur

## 3.2 Hors périmètre (phase 1)

- Diffusion vidéo en direct
- Application mobile complète pour l'invité (le web responsive suffit)
- Gestion de budget prévisionnel événementiel
- Gestion des prestataires et contrats
- Marketplace publique d'événements

---

# 4. PERSONAS ET CAS D'USAGE

## 4.1 Personas organisateurs

### P1 — Marie, chargée d'événementiel en entreprise
- Gère 15 à 40 événements par an : conférences, lancements produit, soirées clients
- Besoins : branding strict, intégration CRM, badges, reporting ROI, collaborateurs
- Volume : 100 à 2 000 invités par événement
- Contrainte : doit justifier chaque dépense marketing

### P2 — Joseph, responsable de collecte de fonds en ONG
- Gala annuel, dîners de donateurs, événements communautaires
- Besoins : billetterie + dons, gestion des tables, segmentation des donateurs
- Sensible au prix, cherche une remise associative
- Contrainte : chaque franc de frais est un franc en moins pour la cause

### P3 — Sarah, agence événementielle
- Gère des événements **pour le compte de clients**
- Besoins : multi-comptes, white-label total, transfert de propriété d'événement
- Facture ses clients — a besoin d'une marge sur l'outil

### P4 — Pasteur Daniel / responsable associatif
- Événements récurrents, communauté large, budget faible
- Besoins : simplicité extrême, WhatsApp, gratuit ou très bas coût
- Contrainte : les invités n'ont pas tous d'adresse e-mail

### P5 — Grace, particulier (mariage, anniversaire)
- Un seul événement, forte charge émotionnelle
- Besoins : beauté du rendu, plan de table, RSVP par foyer, menus
- Contrainte : zéro compétence technique

## 4.2 Personas côté invité

### I1 — L'invité classique
Reçoit un lien, répond en 3 clics, reçoit une confirmation avec QR code.

### I2 — Le chef de foyer
Répond pour lui, son conjoint et ses enfants, chacun avec son choix de menu.

### I3 — L'acheteur de billet
Achète 4 billets, en transfère 3, paie par Mobile Money.

### I4 — Le personnel d'accueil (jour J)
Scanne, cherche par nom, imprime un badge, gère les imprévus, hors ligne.

## 4.3 Cas d'usage principaux (résumé)

| ID | Cas d'usage | Acteur |
|---|---|---|
| UC-01 | Créer un événement à partir d'un modèle | Organisateur |
| UC-02 | Construire un formulaire d'inscription personnalisé | Organisateur |
| UC-03 | Importer une liste d'invités | Organisateur |
| UC-04 | Envoyer des invitations multicanal | Organisateur |
| UC-05 | Répondre à une invitation (RSVP) | Invité |
| UC-06 | Acheter un billet | Invité |
| UC-07 | Faire un don | Invité |
| UC-08 | Placer les invités à table | Organisateur |
| UC-09 | Enregistrer les arrivées le jour J | Personnel |
| UC-10 | Consulter le tableau de bord temps réel | Organisateur |
| UC-11 | Exporter et analyser les données post-événement | Organisateur |
| UC-12 | Synchroniser avec le CRM | Système |

---

# 5. ARCHITECTURE FONCTIONNELLE GÉNÉRALE

## 5.1 Les 9 modules

```
┌──────────────────────────────────────────────────────────────┐
│  M0 — COMPTE, ORGANISATION, RÔLES ET FACTURATION             │
└──────────────────────────────────────────────────────────────┘
        │
┌───────┴──────────────────────────────────────────────────────┐
│  M1 — ÉVÉNEMENTS         │  M2 — FORMULAIRES & INSCRIPTION   │
│  Création, modèles,      │  Form builder, logique            │
│  sous-événements, agenda │  conditionnelle, capacités        │
├──────────────────────────┼───────────────────────────────────┤
│  M3 — INVITÉS & CONTACTS │  M4 — COMMUNICATION               │
│  Import, foyers, tags,   │  E-mail, WhatsApp, SMS,           │
│  +1, segmentation        │  séquences automatiques           │
├──────────────────────────┼───────────────────────────────────┤
│  M5 — BILLETTERIE        │  M6 — PAGES & BRANDING            │
│  Tarifs, paiement, dons, │  Site événement, invitations,     │
│  codes promo, remb.      │  thèmes, multilingue              │
├──────────────────────────┼───────────────────────────────────┤
│  M7 — LOGISTIQUE JOUR J  │  M8 — DONNÉES & PILOTAGE          │
│  Check-in, badges, plan  │  Dashboard, rapports, exports,    │
│  de table, kiosque       │  ROI, IA                          │
└──────────────────────────┴───────────────────────────────────┘
                            │
┌───────────────────────────┴──────────────────────────────────┐
│  M9 — INTÉGRATIONS, API, WEBHOOKS, AUTOMATISATIONS           │
└──────────────────────────────────────────────────────────────┘
```

## 5.2 Flux principal

```
Créer événement → Construire formulaire → Constituer la liste
      ↓
Publier page + envoyer invitations (e-mail / WhatsApp / QR / lien)
      ↓
Invité répond / achète → Confirmation + QR code + calendrier
      ↓
Relances automatiques aux non-répondants
      ↓
Plan de table + préparation logistique
      ↓
JOUR J : check-in scan / recherche / kiosque + badges
      ↓
Rapport automatique + export + synchro CRM + relance post-événement
```

---

# 6. SPÉCIFICATIONS FONCTIONNELLES DÉTAILLÉES

---

## M0 — COMPTE, ORGANISATION, RÔLES ET FACTURATION

### M0.1 Inscription et authentification

| Réf | Exigence | Priorité |
|---|---|---|
| M0.1.1 | Création de compte par e-mail + mot de passe | MVP |
| M0.1.2 | Connexion sociale Google et Microsoft | MVP |
| M0.1.3 | Connexion par lien magique (sans mot de passe) | V2 |
| M0.1.4 | Authentification à deux facteurs (TOTP + SMS) | V2 |
| M0.1.5 | SSO SAML 2.0 / OIDC pour les comptes Enterprise | V3 |
| M0.1.6 | Récupération de mot de passe avec token à durée limitée (30 min) | MVP |
| M0.1.7 | Verrouillage après 5 tentatives échouées (15 min) | MVP |

### M0.2 Structure organisationnelle

- Un **compte utilisateur** appartient à une ou plusieurs **organisations** (espaces de travail).
- Une organisation contient des **événements**, des **contacts**, des **modèles**, une **charte graphique**, un **abonnement**.
- Un utilisateur a un rôle par organisation, et éventuellement un rôle spécifique par événement.

### M0.3 Matrice des rôles et permissions

| Action | Propriétaire | Admin | Éditeur | Personnel accueil | Lecteur |
|---|:---:|:---:|:---:|:---:|:---:|
| Gérer l'abonnement / facturation | ✅ | ❌ | ❌ | ❌ | ❌ |
| Inviter / retirer des membres | ✅ | ✅ | ❌ | ❌ | ❌ |
| Créer / supprimer un événement | ✅ | ✅ | ❌ | ❌ | ❌ |
| Modifier un événement assigné | ✅ | ✅ | ✅ | ❌ | ❌ |
| Voir la liste des invités | ✅ | ✅ | ✅ | ✅ | ✅ |
| Modifier la liste des invités | ✅ | ✅ | ✅ | ❌ | ❌ |
| Envoyer des communications | ✅ | ✅ | ✅ | ❌ | ❌ |
| Effectuer un check-in | ✅ | ✅ | ✅ | ✅ | ❌ |
| Voir les données financières | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| Exporter les données | ✅ | ✅ | ⚙️ | ❌ | ❌ |
| Rembourser un billet | ✅ | ✅ | ❌ | ❌ | ❌ |
| Voir le journal d'audit | ✅ | ✅ | ❌ | ❌ | ❌ |

⚙️ = paramétrable par le propriétaire

### M0.4 Gestion de l'abonnement

- Sélection de plan, changement de plan avec calcul du prorata
- Compteurs d'usage temps réel (inscriptions, e-mails, événements actifs, crédits de check-in)
- Alertes à 80 % et 100 % de consommation d'un quota
- Comportement au dépassement : **blocage progressif et non destructif** (les données restent, les nouvelles inscriptions passent en file d'attente avec notification à l'organisateur)
- Historique de factures téléchargeables (PDF)
- Gestion des remises : associatif, éducation, code partenaire, affiliation

### M0.5 Journal d'audit

Toute action sensible est journalisée : qui, quoi, quand, depuis quelle IP.
Actions tracées : connexion, export de données, suppression, modification de tarif, remboursement, envoi de masse, changement de permission.
Conservation : 24 mois minimum. Consultable et exportable par le propriétaire.

---

## M1 — GESTION DES ÉVÉNEMENTS

### M1.1 Création d'un événement

| Réf | Exigence |
|---|---|
| M1.1.1 | Création à partir d'un modèle de la bibliothèque (min. 30 modèles) |
| M1.1.2 | Création à partir de zéro |
| M1.1.3 | Duplication d'un événement existant (avec ou sans liste d'invités) |
| M1.1.4 | Création par IA à partir d'un brief en langage naturel |
| M1.1.5 | Import depuis un fichier (Excel décrivant l'événement) |

### M1.2 Paramètres d'un événement

**Informations générales**
- Titre, sous-titre, description riche (éditeur WYSIWYG)
- Type d'événement (taxonomie : conférence, gala, mariage, formation…)
- Visuel principal, galerie, vidéo
- Date/heure de début et de fin, **fuseau horaire explicite**
- Événement sur plusieurs jours, événement récurrent (série)
- Lieu : nom, adresse, coordonnées GPS, plan, instructions d'accès, parking
- Événement en ligne : lien de connexion (délivré uniquement aux inscrits confirmés)
- Événement hybride : les deux, avec choix du mode par l'invité

**Paramètres d'inscription**
- Date d'ouverture et de fermeture des inscriptions
- Capacité totale et capacité par option
- Liste d'attente automatique avec promotion automatique en cas de désistement
- Mode d'accès : public / lien privé / liste fermée / mot de passe
- Validation manuelle des inscriptions (mode « approbation »)
- Autorisation de modification ou d'annulation par l'invité, jusqu'à J-X

**Statuts de l'événement**
`Brouillon` → `Publié` → `En cours` → `Terminé` → `Archivé`
(+ `Suspendu` : inscriptions gelées sans dépublier la page)

### M1.3 Sous-événements

Un événement principal peut contenir des **sous-événements** auxquels on s'inscrit séparément : cérémonie, dîner de gala, ateliers, sessions parallèles, visite guidée.

Règles :
- Chaque sous-événement a sa propre capacité, ses propres horaires, son propre lieu
- Un sous-événement peut être : ouvert à tous / réservé à certains tags / payant
- Détection des **conflits d'horaires** entre sessions parallèles
- Le check-in peut être effectué au niveau de l'événement global **et** par sous-événement

### M1.4 Bibliothèque de modèles

Modèles par catégorie : entreprise (conférence, lancement, séminaire, assemblée générale, kick-off), associatif (gala, collecte, assemblée), éducation (remise de diplômes, journée portes ouvertes, réunion parents), religieux, personnel (mariage, anniversaire, baptême, deuil), agence.

Chaque modèle embarque : structure de formulaire pré-remplie, design, séquence d'e-mails, champs de check-in.

---

## M2 — FORMULAIRES ET INSCRIPTION

### M2.1 Constructeur de formulaire

Éditeur visuel drag-and-drop, aperçu temps réel, mode mobile/desktop.

**Types de champs disponibles**

| Type | Options de configuration |
|---|---|
| Texte court / long | Longueur min/max, expression régulière |
| Nombre | Min, max, pas, unité |
| E-mail | Validation MX, détection de doublon |
| Téléphone | Sélecteur d'indicatif pays, format international |
| Date / heure | Plage autorisée, exclusion de dates |
| Choix unique (radio / liste) | Options avec quota individuel, prix associé |
| Choix multiple | Nombre min/max de sélections, quota par option |
| Oui / Non | Libellés personnalisables |
| Fichier joint | Types autorisés, taille max, antivirus |
| Signature / consentement | Texte légal, horodatage, IP |
| Notation / échelle | 1-5, 1-10, NPS |
| Adresse | Autocomplétion cartographique |
| Séparateur / titre / texte informatif | Mise en forme |
| Champ caché | Valeur depuis l'URL, UTM, référent |
| Menu / repas | Options, allergènes, préférences alimentaires |
| Sélection de créneau | Durée, capacité par créneau |
| Billet / tarif | Quantité, prix, taxe |

**Propriétés communes à tout champ** : libellé, texte d'aide, obligatoire/facultatif, valeur par défaut, visibilité conditionnelle, mappage CRM, champ « donnée sensible » (chiffrement renforcé).

### M2.2 Logique conditionnelle

Trois niveaux, cumulables :

1. **Logique par réponse** — « Si le champ *Participez-vous au dîner ?* = Oui, alors afficher *Choix du menu* »
2. **Logique par tag** — « Si l'invité porte le tag *VIP*, afficher la session *Cocktail privé* »
3. **Logique par segment** — « Si l'invité vient de l'organisation X, appliquer le tarif Partenaire »

Opérateurs : est / n'est pas / contient / ne contient pas / supérieur à / inférieur à / est vide / n'est pas vide.
Combinaisons ET / OU avec groupes de conditions.
Actions possibles : afficher, masquer, rendre obligatoire, pré-remplir, sauter à une étape, appliquer un tarif, ajouter un tag, envoyer une notification interne.

**Exigence critique** : un moteur de règles avec détection de boucles et prévisualisation « simuler une réponse ».

### M2.3 Formulaire multi-étapes

- Découpage en étapes avec barre de progression
- Sauvegarde automatique et reprise ultérieure (lien de reprise envoyé par e-mail)
- Récapitulatif avant validation
- Gestion des **inscriptions de groupe** : un inscrivant renseigne N participants, chacun avec ses propres réponses

### M2.4 Règles de gestion des inscriptions

| Règle | Comportement |
|---|---|
| Doublon détecté (même e-mail) | Proposer de modifier l'inscription existante |
| Capacité atteinte | Basculer sur liste d'attente ou fermer |
| Option en rupture | Griser l'option avec mention « Complet » |
| Inscription hors période | Message personnalisable |
| Abandon de panier | Relance automatique à H+2 et J+1 |
| Modification par l'invité | Historisation de la version précédente |
| Annulation | Libération de la place, promotion de la liste d'attente, remboursement selon politique |

### M2.5 Intégration du formulaire

- Page hébergée avec URL personnalisée (`evenement.mondomaine.com/nom`)
- Iframe responsive avec redimensionnement automatique
- Widget JavaScript (bouton flottant, modale, inline)
- QR code généré automatiquement (statique et par invité)
- Lien court traçable avec paramètres UTM

---

## M3 — INVITÉS, CONTACTS ET SEGMENTATION

### M3.1 Base de contacts

Une base de contacts **au niveau de l'organisation**, réutilisable d'un événement à l'autre.

Fiche contact : identité, coordonnées, entreprise/fonction, langue préférée, canal préféré, tags, champs personnalisés, historique complet de participation, score d'engagement, consentements et préférences de communication.

### M3.2 Notion de foyer / groupe

**Différenciateur majeur.** Un contact peut appartenir à un **foyer** (famille) ou à un **groupe** (entreprise, délégation).

Comportements :
- Un membre du foyer peut répondre pour tous les autres
- Chaque membre garde ses propres réponses (menu, présence par sous-événement)
- Les communications peuvent être envoyées au foyer (une seule) ou individuellement
- Le décompte des places gère le foyer comme une unité de placement

### M3.3 Import et enrichissement

| Réf | Exigence |
|---|---|
| M3.3.1 | Import CSV / Excel avec mappage de colonnes assisté |
| M3.3.2 | Détection et fusion des doublons (score de similarité) |
| M3.3.3 | Aperçu et validation avant import définitif |
| M3.3.4 | Rapport d'import (lignes acceptées, rejetées, motif) |
| M3.3.5 | Import depuis Google Contacts, Outlook, CRM |
| M3.3.6 | Validation des e-mails (syntaxe + MX + détection jetables) |
| M3.3.7 | Normalisation des numéros au format E.164 |
| M3.3.8 | Import incrémental (mise à jour sans écrasement) |

### M3.4 Tags et segmentation

- Tags libres, hiérarchisables, colorés
- Tags automatiques par règle (« si montant du don > 500 alors tag *Grand donateur* »)
- **Segments dynamiques** : audience définie par critères, recalculée en continu
- Exemples de segments : n'ont pas répondu, ont ouvert sans cliquer, VIP présents l'an dernier, végétariens, arrivés en retard

### M3.5 Gestion des accompagnants

- +1 anonyme (« vous pouvez venir accompagné »)
- +X avec nombre défini par invité
- Accompagnants nommés obligatoires (sécurité, badges)
- Accompagnant avec son propre formulaire
- Blocage des accompagnants sur certains sous-événements

### M3.6 Statuts d'invité

`Non invité` → `Invité` → `Ouvert` → `En cours` → `Confirmé` / `Décliné` / `Liste d'attente` → `Présent` / `Absent` / `Annulé`

Chaque changement de statut est horodaté et déclenche des automatisations possibles.

---

## M4 — COMMUNICATION MULTICANAL

### M4.1 Canaux supportés

| Canal | Usage | Priorité |
|---|---|---|
| E-mail | Invitations, confirmations, rappels, billets | MVP |
| WhatsApp Business API | Invitations, rappels, billet avec QR | MVP+ |
| SMS | Rappels courts, code d'accès, alertes jour J | V2 |
| Notification push (app check-in) | Alertes équipe | V2 |
| Courrier papier (QR imprimé) | Invitations formelles | MVP (génération PDF) |

### M4.2 Éditeur d'e-mails

- Éditeur visuel par blocs + mode HTML avancé
- Bibliothèque de modèles par type de message
- **Variables de fusion** : `{{prenom}}`, `{{nom}}`, `{{lien_rsvp}}`, `{{qr_code}}`, `{{date_evenement}}`, `{{lieu}}`, `{{table}}`, `{{montant}}`, champs personnalisés
- Contenu conditionnel dans l'e-mail (bloc affiché si tag = VIP)
- Aperçu par destinataire réel
- Test d'envoi et **score anti-spam** avant expédition
- Test A/B sur l'objet

### M4.3 Types de messages

| Type | Déclencheur | Contenu type |
|---|---|---|
| Save the date | Manuel | Date + accroche |
| Invitation | Manuel ou planifié | Lien personnalisé RSVP |
| Confirmation | Automatique post-inscription | Récapitulatif + QR + ICS |
| Rappel de réponse | J-X sur non-répondants | Relance |
| Rappel d'événement | J-7, J-1, H-2 | Infos pratiques + QR |
| Modification | Sur changement d'événement | Diff des changements |
| Annulation | Manuel | Motif + remboursement |
| Remerciement | J+1 | Photos, enquête |
| Enquête de satisfaction | J+1 à J+3 | Lien formulaire / NPS |
| Reçu de paiement / don | Automatique | Facture ou reçu fiscal |

### M4.4 Séquences automatisées

Éditeur de scénario visuel : déclencheur → conditions → délai → action.

Exemple :
```
Déclencheur : invitation envoyée
   ↓ attendre 4 jours
Condition : statut = "Invité" (pas de réponse)
   ↓
Action : envoyer relance 1 (e-mail)
   ↓ attendre 3 jours
Condition : toujours pas de réponse
   ↓
Action : envoyer relance 2 (WhatsApp)
   ↓ attendre 3 jours
Action : notifier l'organisateur de la liste des silencieux
```

### M4.5 Délivrabilité

| Réf | Exigence |
|---|---|
| M4.5.1 | Domaine d'envoi personnalisé avec configuration SPF, DKIM, DMARC assistée |
| M4.5.2 | Chauffe progressive des domaines (IP warming) |
| M4.5.3 | Gestion des bounces durs/souples avec suppression automatique |
| M4.5.4 | Lien de désabonnement conforme et liste de suppression |
| M4.5.5 | Suivi ouvertures, clics, plaintes, désabonnements |
| M4.5.6 | Limitation de débit d'envoi paramétrable |
| M4.5.7 | Bascule automatique de canal si e-mail invalide (→ WhatsApp/SMS) |

---

## M5 — BILLETTERIE, PAIEMENTS ET DONS

### M5.1 Configuration des billets

- Types de billets illimités : gratuit, payant, sur invitation, don libre
- Prix, devise, quantité disponible, quantité min/max par commande
- **Tarification par paliers dans le temps** : early bird → normal → tardif (bascule automatique)
- Tarification par segment : membre / non-membre / étudiant / partenaire
- Billets groupés (table de 10, pack entreprise)
- Options additionnelles payantes (parking, repas, hébergement, goodies)
- Taxes : TVA paramétrable, incluse ou en sus, par pays
- Gestion des frais : **absorbés par l'organisateur ou répercutés sur l'acheteur** (choix explicite)

### M5.2 Codes promotionnels

- Réduction en pourcentage ou montant fixe
- Nombre d'utilisations total et par personne
- Période de validité
- Applicable à certains types de billets uniquement
- Code d'accès débloquant des billets cachés
- Génération de codes uniques en masse (nominatifs, traçables)

### M5.3 Moyens de paiement

| Moyen | Marché | Priorité |
|---|---|---|
| Carte bancaire (Visa, Mastercard) via Stripe | International | MVP |
| Apple Pay / Google Pay | International | MVP |
| **Mobile Money** (M-Pesa, Orange Money, Airtel Money, MTN MoMo) | Afrique | **MVP** |
| Virement bancaire avec référence de rapprochement | Tous | V2 |
| PayPal | International | V2 |
| Paiement à l'arrivée (espèces) avec enregistrement | Tous | MVP |
| Paiement en plusieurs fois | International | V3 |

**Architecture recommandée** : couche d'abstraction « fournisseur de paiement » permettant de brancher un agrégateur local (Flutterwave, Paystack, CinetPay, MaxiCash) sans toucher au cœur applicatif.

### M5.4 Cycle de vie d'une commande

```
Panier → Paiement initié → [Succès / Échec / En attente]
   ↓ succès
Commande confirmée → Billets émis (QR uniques) → Reçu envoyé
   ↓
[Transfert de billet] [Modification] [Annulation → Remboursement]
```

Règles :
- Réservation temporaire du stock pendant le paiement (15 min)
- Idempotence des webhooks de paiement (pas de double comptage)
- Statut « en attente » géré explicitement pour Mobile Money (confirmation asynchrone)
- Réconciliation quotidienne automatique avec le fournisseur de paiement

### M5.5 Remboursements

- Total ou partiel, avec ou sans les frais
- Politique de remboursement paramétrable et affichée à l'achat
- Remboursement automatique en cas d'annulation d'événement
- Traçabilité complète et notification à l'acheteur

### M5.6 Dons

- Montants suggérés + montant libre
- Don ponctuel ou récurrent (V2)
- Don au moment du paiement du billet (« arrondir » ou ajouter)
- Affichage optionnel d'une jauge de collecte publique
- Reçu fiscal automatique paramétrable par pays
- Attribution du don à une cause / un projet spécifique

### M5.7 Reversement des fonds

- Virement automatique vers le compte de l'organisateur (fréquence paramétrable)
- Tableau de bord financier : encaissé, frais, net, en attente, remboursé
- Export comptable (CSV, format compatible logiciels de compta)
- Facture de commission émise automatiquement

---

## M6 — PAGES ÉVÉNEMENT, INVITATIONS ET BRANDING

### M6.1 Constructeur de site événement

Éditeur par sections drag-and-drop.

**Blocs disponibles** : bannière/hero, compte à rebours, description, programme/agenda, intervenants, lieu + carte, galerie photo/vidéo, FAQ, sponsors/partenaires, témoignages, tarifs, formulaire d'inscription, plan d'accès, contact, réseaux sociaux, bloc HTML libre.

**Fonctionnalités** :
- Responsive automatique avec réglages par point de rupture
- Mode page unique ou multi-pages
- Page de remerciement personnalisable avec redirection possible
- SEO : titre, méta-description, image de partage, données structurées `Event` (schema.org)
- Vitesse : score Lighthouse > 90

### M6.2 Charte graphique de l'organisation

- Logo, favicon
- Palette de couleurs (primaire, secondaire, accent, fond, texte)
- Typographies (bibliothèque + upload de polices propriétaires)
- Rayons d'arrondi, styles de boutons
- **Application en un clic** à tous les événements et e-mails de l'organisation
- CSS personnalisé pour les plans avancés

### M6.3 Niveaux de marque blanche

| Niveau | Plan | Contenu |
|---|---|---|
| Marque visible | Gratuit | « Propulsé par [Plateforme] » sur page + e-mails |
| Marque réduite | Intermédiaire | Retrait sur les e-mails uniquement |
| Sans marque | Professionnel | Aucune mention |
| **White-label total** | Entreprise/Agence | Domaine propre, e-mails propres, favicon, portail client à la marque de l'agence |

### M6.4 Invitations en ligne

- Modèles de cartons d'invitation animés
- Upload d'un visuel créé ailleurs (Canva, Photoshop) avec zones interactives
- Version imprimable PDF haute définition avec QR individuel
- Prévisualisation par destinataire

### M6.5 Multilingue

- Détection automatique de la langue du navigateur
- Sélecteur de langue sur la page
- Traduction de l'interface (textes système) + traduction des contenus saisis
- **Langues cibles prioritaires** : français, anglais, portugais, espagnol, arabe (RTL), swahili, lingala
- Langue de communication définie par contact

---

## M7 — LOGISTIQUE JOUR J

### M7.1 Application de check-in

Application mobile native (iOS/Android) + version web.

| Réf | Exigence | Priorité |
|---|---|---|
| M7.1.1 | Scan de QR code par caméra (< 1 s) | MVP |
| M7.1.2 | Recherche par nom, e-mail, téléphone, n° de billet | MVP |
| M7.1.3 | **Mode hors ligne complet** avec synchronisation différée | MVP |
| M7.1.4 | Résolution de conflits en cas de scan simultané multi-postes | MVP |
| M7.1.5 | Check-in et check-out (suivi de présence réelle) | V2 |
| M7.1.6 | Check-in par sous-événement / session | V2 |
| M7.1.7 | Ajout d'un invité sur place (walk-in) avec paiement | MVP |
| M7.1.8 | Affichage des informations clés : table, menu, allergies, tag VIP, notes | MVP |
| M7.1.9 | Nommage des postes de contrôle (Entrée principale, VIP, Salle A) | V2 |
| M7.1.10 | Compteur temps réel et objectif de jauge | MVP |
| M7.1.11 | Alerte sur invité déjà enregistré (anti-fraude) | MVP |
| M7.1.12 | Mode « liste » sans scan pour petits événements | MVP |

**Exigence critique — le mode hors ligne** : l'application télécharge la liste complète avant l'événement, fonctionne en local (base embarquée), et synchronise dès que la connexion revient. En cas de conflit (même invité scanné sur deux postes), la règle est : premier horodatage retenu, second signalé comme alerte.

### M7.2 Kiosque de self check-in

- Mode plein écran verrouillé sur tablette
- L'invité scanne son QR ou saisit son nom
- Impression automatique du badge
- Retour visuel et sonore
- Mode multilingue

### M7.3 Badges

- Éditeur de badge (format, champs, logo, QR, code couleur par catégorie)
- Formats supportés : imprimantes thermiques (Brother QL, Zebra, Dymo), planches Avery, PDF
- Impression à la demande au check-in ou pré-impression en masse
- Code couleur automatique par tag (VIP, presse, intervenant, staff)

### M7.4 Plan de table et placement

- Éditeur visuel drag-and-drop du plan de salle
- Formes de tables : ronde, rectangulaire, en U, cocktail, rangées (théâtre/conférence)
- Capacité par table, numérotation automatique
- Glisser-déposer des invités, ou **placement automatique assisté** selon règles (garder les foyers ensemble, séparer certaines personnes, regrouper par entreprise, équilibrer les tables)
- Contraintes : « X doit être avec Y », « X ne doit pas être avec Z »
- Export PDF du plan et des listes par table
- Affichage du numéro de table sur le badge et dans l'e-mail de rappel
- Vue « qui est à quelle table » pour l'équipe d'accueil

### M7.5 Gestion des repas

- Récapitulatif temps réel des choix de menu pour le traiteur
- Export par table et par catégorie
- Signalement visuel des allergies et régimes spéciaux au check-in
- Alerte si le nombre de repas commandés diverge des présents confirmés

---

## M8 — DONNÉES, PILOTAGE ET ANALYSE

### M8.1 Tableau de bord événement (temps réel)

**Bloc inscriptions** : invités, ouvertures, réponses, confirmés, déclinés, sans réponse, liste d'attente, taux de conversion, courbe cumulée dans le temps.

**Bloc financier** : billets vendus par type, chiffre d'affaires brut, frais, net, dons, panier moyen, projection de recette.

**Bloc communication** : envoyés, délivrés, ouverts, cliqués, désabonnés, par canal.

**Bloc jour J** : présents, taux de présence (`présents / confirmés`), no-show, courbe d'arrivée par tranche horaire, répartition par point de contrôle.

**Bloc logistique** : répartition des menus, tables remplies, sessions les plus demandées.

### M8.2 Rapports

- Rapport post-événement généré automatiquement (PDF + web partageable)
- Comparaison entre événements (même série, année N vs N-1)
- Tableau de bord de programme (portefeuille de tous les événements de l'organisation)
- Rapport par segment, par source d'acquisition, par canal

### M8.3 Calcul du ROI

Formule paramétrable :
```
ROI = (Valeur générée − Coût total) / Coût total

Valeur générée = recettes billetterie + dons
               + (nb leads qualifiés × valeur moyenne d'un lead)
               + (opportunités CRM créées × taux de conversion × panier moyen)

Coût total = coûts saisis (lieu, traiteur, personnel, communication)
           + abonnement plateforme + frais de transaction
```
Saisie des coûts dans l'outil, rapprochement automatique avec les données CRM synchronisées.

### M8.4 Exports

- CSV, Excel, PDF, JSON
- Export sélectif par colonnes, par segment, par période
- Exports planifiés et envoyés par e-mail automatiquement
- **Journalisation obligatoire de tout export** (RGPD)

### M8.5 Assistant IA analytique

Interrogation en langage naturel de ses propres données :
- « Combien de VIP ont confirmé pour le gala ? »
- « Donne-moi la liste des invités qui n'ont pas répondu depuis 10 jours »
- « Quel est le taux de présence moyen de mes événements de 2026 ? »
- « Résume les retours de l'enquête de satisfaction »
- « Génère un rapport de ROI pour le lancement produit »

**Contraintes impératives** :
- Accès strictement limité aux données de l'organisation de l'utilisateur
- Respect de la matrice de permissions (un lecteur ne peut pas faire sortir des données financières)
- Chaque requête journalisée
- Mention explicite que les réponses doivent être vérifiées
- Aucune donnée client utilisée pour entraîner un modèle
- Mode lecture seule par défaut, actions d'écriture nécessitant confirmation explicite

---

## M9 — INTÉGRATIONS, API ET AUTOMATISATIONS

### M9.1 Intégrations natives

| Catégorie | Outils | Priorité |
|---|---|---|
| CRM | HubSpot, Salesforce, Zoho, Pipedrive | V2 |
| E-mail marketing | Mailchimp, Brevo, ActiveCampaign | V2 |
| Paiement | Stripe, agrégateurs Mobile Money, PayPal | MVP |
| Agenda | Google Calendar, Outlook, ICS | MVP |
| Automatisation | Zapier, Make, n8n | V2 |
| Analytics | Google Analytics 4, Meta Pixel, LinkedIn Insight | MVP |
| Messagerie | WhatsApp Business API, Slack, Teams | V2 |
| Visio | Zoom, Google Meet, Teams | V3 |
| Stockage | Google Drive, Dropbox | V3 |
| Comptabilité | Export normalisé, Sage, QuickBooks | V3 |

### M9.2 API publique

- REST, versionnée (`/v1/`), format JSON
- Authentification par clé API et OAuth 2.0
- Ressources exposées : événements, formulaires, inscriptions, contacts, billets, commandes, check-ins, rapports
- Pagination par curseur, filtrage, tri, champs partiels
- Limitation de débit : 1 000 req/min (Enterprise), 100 req/min (standard)
- Documentation OpenAPI 3 interactive + SDK JavaScript, PHP, Python
- Environnement bac à sable

### M9.3 Webhooks

Événements émis : `registration.created`, `registration.updated`, `registration.cancelled`, `ticket.purchased`, `payment.succeeded`, `payment.failed`, `refund.issued`, `checkin.completed`, `event.published`, `capacity.reached`, `waitlist.promoted`, `donation.received`.

Exigences : signature HMAC, réessais avec backoff exponentiel (5 tentatives sur 24 h), journal consultable, rejeu manuel.

### M9.4 Moteur d'automatisation interne

Constructeur de règles « Si … Alors … » sans code :
- « Si un invité avec le tag *Presse* s'inscrit → notifier le responsable com sur Slack »
- « Si la jauge atteint 90 % → envoyer une alerte et ouvrir la liste d'attente »
- « Si un don > 1 000 → créer une tâche dans le CRM »
- « Si un invité ne s'est pas présenté → l'ajouter au segment *No-show* »

---

# 7. MODÈLE DE DONNÉES

## 7.1 Entités principales

```
Organization
  ├── User (via Membership: role)
  ├── Subscription ── Invoice
  ├── BrandKit
  ├── Contact ──┬── Household
  │             └── Tag (n-n)
  ├── Template
  └── Event
       ├── SubEvent
       ├── Form
       │    └── FormField ── FieldOption
       │         └── ConditionalRule
       ├── EventPage ── PageSection
       ├── TicketType ── PriceTier
       ├── PromoCode
       ├── Invitation
       ├── Registration ──┬── RegistrationAnswer
       │                  ├── Attendee (participant réel)
       │                  └── Order ──┬── OrderItem
       │                              ├── Payment
       │                              └── Refund
       ├── Ticket (QR unique) ── CheckIn
       ├── Donation
       ├── SeatingPlan ── Table ── Seat
       ├── Campaign ── Message ── MessageEvent (ouvert/cliqué)
       ├── Automation ── AutomationRun
       └── AuditLog
```

## 7.2 Entités clés — champs essentiels

### `Event`
`id`, `organization_id`, `slug`, `title`, `subtitle`, `description`, `type`, `status`, `start_at`, `end_at`, `timezone`, `venue_id`, `is_online`, `online_url`, `capacity`, `registration_opens_at`, `registration_closes_at`, `access_mode`, `password_hash`, `requires_approval`, `allow_waitlist`, `allow_guest_edit`, `edit_deadline`, `currency`, `brand_kit_id`, `parent_event_id`, `created_by`, `created_at`, `updated_at`, `deleted_at`

### `Contact`
`id`, `organization_id`, `household_id`, `first_name`, `last_name`, `email`, `phone_e164`, `company`, `job_title`, `preferred_language`, `preferred_channel`, `custom_fields` (JSONB), `email_consent`, `sms_consent`, `whatsapp_consent`, `consent_source`, `consent_at`, `unsubscribed_at`, `engagement_score`, `created_at`, `updated_at`, `deleted_at`

### `Registration`
`id`, `event_id`, `contact_id`, `sub_event_ids[]`, `status`, `registered_at`, `responded_at`, `guest_count`, `plus_ones[]`, `source`, `utm` (JSONB), `ip_address`, `user_agent`, `locale`, `notes`, `approved_by`, `waitlist_position`, `cancelled_at`, `cancellation_reason`

### `Ticket`
`id`, `order_id`, `ticket_type_id`, `attendee_id`, `qr_token` (unique, signé), `status` (valide / utilisé / annulé / transféré), `price_paid`, `fees`, `tax`, `transferred_from`, `issued_at`, `used_at`

### `CheckIn`
`id`, `ticket_id`, `event_id`, `sub_event_id`, `terminal_id`, `operator_id`, `direction` (in/out), `method` (scan/recherche/manuel/kiosque), `checked_in_at`, `synced_at`, `device_local_id`, `conflict_flag`

> **Note d'implémentation** : `device_local_id` et `synced_at` sont indispensables au mode hors ligne. L'identifiant est généré côté appareil (UUID v7), la synchronisation est idempotente.

## 7.3 Règles d'intégrité

- Suppression logique (`deleted_at`) partout, purge définitive après 30 jours
- Un `qr_token` est un JWT signé, à usage unique, non devinable, révocable
- Les montants sont stockés en **entiers (centimes)**, jamais en flottant
- Toutes les dates en UTC, avec le fuseau de l'événement stocké séparément
- Historisation des versions de formulaire : une réponse reste liée à la version du formulaire au moment de la soumission
- Cloisonnement multi-tenant strict : chaque requête filtre obligatoirement sur `organization_id` (row-level security)

---

# 8. ARCHITECTURE TECHNIQUE

## 8.1 Vue d'ensemble

```
                        [ CDN / WAF ]
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
  [Site vitrine]      [App organisateur]     [Pages invité]
   Next.js SSG          React SPA            Next.js SSR
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              │
                      [ API Gateway ]
                     REST + Auth + Rate limit
                              │
      ┌───────────┬───────────┼───────────┬────────────┐
      │           │           │           │            │
  [Core API] [Payments]  [Messaging]  [Check-in]   [Analytics]
      │           │           │           │            │
      └───────────┴───────────┼───────────┴────────────┘
                              │
        ┌─────────────┬───────┴───────┬──────────────┐
   [PostgreSQL]    [Redis]      [S3/Object]    [File d'attente]
    principal      cache/         médias         jobs async
                   sessions
```

## 8.2 Choix technologiques recommandés

| Couche | Techno | Justification |
|---|---|---|
| Front organisateur | React 18 + TypeScript + Vite | Écosystème, recrutement, performance |
| Front public | Next.js (SSR/SSG) | SEO des pages événement, temps de chargement |
| UI | Tailwind CSS + composants headless | Rapidité, cohérence, thématisation |
| Backend | Node.js (NestJS) **ou** PHP 8.3 (Laravel) | NestJS pour le typage bout en bout ; Laravel si l'équipe est déjà PHP |
| Base de données | PostgreSQL 16 | JSONB pour les champs dynamiques, RLS, robustesse |
| Cache / sessions | Redis | Compteurs temps réel, verrous de stock |
| File d'attente | BullMQ ou RabbitMQ | E-mails, exports, synchro, webhooks |
| Recherche | PostgreSQL full-text (MVP) → Meilisearch | Recherche invité instantanée |
| Stockage fichiers | S3 compatible (AWS S3, Cloudflare R2) | Coût, CDN intégré |
| Temps réel | WebSocket (Socket.io) ou SSE | Dashboard live, check-in multi-postes |
| Mobile check-in | React Native + SQLite (WatermelonDB) | Hors ligne, une base de code |
| E-mail transactionnel | Amazon SES / Postmark / Brave | Coût vs délivrabilité |
| Paiement | Stripe + agrégateur local | Couverture internationale + Mobile Money |
| Observabilité | Sentry + OpenTelemetry + Grafana | Erreurs, traces, métriques |
| CI/CD | GitHub Actions | Standard, intégré |
| Infrastructure | Conteneurs (Docker) sur Kubernetes ou PaaS | Élasticité les jours de pointe |

## 8.3 Principes d'architecture

1. **Modulaire, pas microservices dès le départ.** Un monolithe bien découpé en modules avec des frontières nettes. On extrait un service (paiement, messagerie) seulement quand la charge le justifie.
2. **API-first.** L'interface web consomme la même API publique que les intégrateurs. Cela garantit que l'API est réellement complète.
3. **Traitement asynchrone systématique** pour tout ce qui est lent : envois de masse, exports, génération de PDF, synchro CRM.
4. **Idempotence** obligatoire sur toutes les opérations financières et de synchronisation.
5. **Cloisonnement multi-tenant** au niveau base de données (row-level security PostgreSQL), pas seulement applicatif.
6. **Feature flags** pour activer/désactiver des fonctionnalités par plan et par organisation.

## 8.4 Gestion des pics de charge

Un événement à forte demande peut générer un pic brutal (ouverture de billetterie). Prévoir :
- Salle d'attente virtuelle (file d'attente) au-delà d'un seuil
- Verrouillage optimiste sur le stock de billets
- Mise en cache agressive des pages publiques
- Auto-scaling horizontal de l'API
- Découplage strict paiement / émission de billet

## 8.5 Environnements

`local` → `développement` → `recette (staging)` → `production`
Base de recette anonymisée à partir de la production. Aucune donnée réelle en dehors de la production.

---

# 9. INTÉGRATIONS ET API

*(voir M9 pour le détail fonctionnel)*

**Exigences transverses** :
- Chaque intégration a un écran de configuration avec test de connexion
- Mappage de champs personnalisable (champ formulaire ↔ champ CRM)
- Journal de synchronisation avec erreurs explicites et rejeu
- Synchronisation bidirectionnelle quand c'est pertinent (CRM)
- Déconnexion propre sans perte de données

---

# 10. SÉCURITÉ, CONFORMITÉ ET RGPD

## 10.1 Sécurité applicative

| Réf | Exigence |
|---|---|
| S-01 | HTTPS obligatoire, TLS 1.3, HSTS |
| S-02 | Mots de passe hachés en Argon2id |
| S-03 | Protection CSRF, XSS (CSP stricte), injection SQL (requêtes préparées) |
| S-04 | Limitation de débit par IP et par compte sur les endpoints sensibles |
| S-05 | Protection anti-bot sur les formulaires publics (challenge invisible) |
| S-06 | Analyse antivirus des fichiers téléversés |
| S-07 | Secrets en coffre-fort (Vault / Secrets Manager), jamais dans le code |
| S-08 | Chiffrement au repos de la base et des sauvegardes (AES-256) |
| S-09 | Chiffrement applicatif des champs marqués « sensibles » |
| S-10 | Tests d'intrusion annuels + scan de dépendances en continu |
| S-11 | Politique de divulgation responsable des vulnérabilités |
| S-12 | Sauvegardes chiffrées quotidiennes, restauration testée trimestriellement |

## 10.2 Conformité RGPD et protection des données

| Réf | Exigence |
|---|---|
| P-01 | Base légale explicite pour chaque traitement |
| P-02 | Consentement granulaire par canal (e-mail / SMS / WhatsApp) horodaté |
| P-03 | Droit d'accès : export des données d'une personne en un clic |
| P-04 | Droit à l'effacement : suppression avec anonymisation des historiques |
| P-05 | Droit de rectification : l'invité peut modifier ses données |
| P-06 | Portabilité : export au format machine (JSON) |
| P-07 | Durées de conservation paramétrables par type de donnée |
| P-08 | Suppression automatique après la durée définie |
| P-09 | Registre des traitements généré |
| P-10 | DPA (accord de traitement) type téléchargeable |
| P-11 | Localisation des données paramétrable (UE, US, Afrique) |
| P-12 | Notification de violation de données sous 72 h (procédure définie) |
| P-13 | Aucune revente ni location de données — engagement contractuel |
| P-14 | Bannière cookies conforme avec gestion granulaire |

## 10.3 Certifications visées

- **SOC 2 Type II** — indispensable pour vendre aux grands comptes (18 à 24 mois après lancement)
- **ISO 27001** — V3
- **PCI-DSS SAQ-A** — via délégation totale du paiement au prestataire (aucune donnée de carte ne transite par la plateforme)
- Conformité aux réglementations locales de protection des données selon les marchés visés

## 10.4 Sécurité de l'événement lui-même

- Mot de passe global ou individuel par invité
- Liste fermée : seules les personnes de la liste peuvent s'inscrire
- QR à usage unique avec détection de duplication
- Masquage de la liste des participants aux autres invités
- Journalisation de tous les accès aux données d'invités
- Mode « événement sensible » : chiffrement renforcé, accès restreint, pas d'indexation

---

# 11. UX / UI ET DESIGN SYSTEM

## 11.1 Principes de conception

1. **Le premier événement en moins de 30 minutes.** Assistant guidé, valeurs par défaut intelligentes, rien d'obligatoire qui ne soit indispensable.
2. **L'invité ne doit jamais réfléchir.** Formulaire lisible sur mobile, 3 clics maximum pour un RSVP simple, pas de création de compte imposée.
3. **Progressivité.** Un débutant voit une interface simple ; les options avancées se révèlent quand on en a besoin.
4. **Le jour J prime sur tout.** L'interface de check-in doit être utilisable par un bénévole, sous stress, avec une main, dans le bruit et la pénombre.

## 11.2 Design system

- Bibliothèque de composants documentée (Storybook)
- Jetons de design (couleurs, espacements, typographie, ombres, rayons)
- Modes clair et sombre
- Grille responsive : mobile 360px, tablette 768px, desktop 1280px, large 1600px
- États systématiques pour chaque composant : normal, survol, focus, actif, désactivé, chargement, erreur, vide

## 11.3 Accessibilité

Conformité **WCAG 2.1 niveau AA** :
- Contraste minimum 4,5:1
- Navigation complète au clavier
- Compatibilité lecteurs d'écran (ARIA)
- Textes alternatifs sur toutes les images
- Aucune information portée par la couleur seule
- Zones tactiles ≥ 44×44 px
- Respect de `prefers-reduced-motion`

## 11.4 Optimisation mobile

Sachant que la majorité des invités répondent depuis un téléphone :
- Formulaire invité conçu mobile-first
- Poids de page < 500 Ko sur la page RSVP
- Fonctionnement correct en 3G
- Version allégée automatique en cas de connexion lente
- Billet ajoutable au portefeuille du téléphone (Apple Wallet / Google Wallet)

---

# 12. EXIGENCES NON FONCTIONNELLES

| Domaine | Exigence | Cible |
|---|---|---|
| **Disponibilité** | Uptime mensuel | ≥ 99,9 % (99,95 % Enterprise) |
| | Page de statut publique | Obligatoire |
| | RTO / RPO | 4 h / 1 h |
| **Performance** | Chargement page RSVP | < 2 s (3G) |
| | Réponse API (p95) | < 300 ms |
| | Scan de check-in | < 1 s |
| | Recherche invité sur 10 000 | < 500 ms |
| | Génération d'un export 50 000 lignes | < 60 s (asynchrone) |
| **Scalabilité** | Inscriptions simultanées par événement | 5 000 |
| | Invités par événement | 100 000 |
| | E-mails par heure | 500 000 |
| | Check-ins par minute | 1 000 |
| **Compatibilité** | Navigateurs | 2 dernières versions Chrome, Safari, Firefox, Edge |
| | Mobile | iOS 15+, Android 9+ |
| **Localisation** | Langues d'interface au lancement | FR, EN |
| | Devises | Multi-devises avec taux configurable |
| | Fuseaux horaires | Tous, gestion explicite |

---

# 13. MODÈLE ÉCONOMIQUE ET FACTURATION

## 13.1 Structure tarifaire recommandée

Trois grilles distinctes, comme le fait RSVPify — c'est un choix stratégique pertinent car les trois marchés n'ont ni le même budget ni les mêmes attentes.

### Grille A — Entreprises et organisations

| Plan | Cible | Inscriptions/mois | Fonctionnalités clés |
|---|---|---|---|
| **Découverte** (gratuit) | Test | 100 | 1 événement, RSVP de base, marque visible |
| **Essentiel** | TPE, associations | 300 | Questions personnalisées, plan de table, e-mails avancés |
| **Pro** | PME, ONG | 1 000 | Check-in, collaborateurs, capacités, WhatsApp |
| **Business** | Entreprises | 3 000 | Marque réduite, embed, automatisations, API |
| **Entreprise** | Grands comptes | Sur mesure | White-label, SSO, CRM natif, kiosque, account manager |

### Grille B — Billetterie
**0 abonnement. Commission par billet vendu** (recommandation : 2 % + frais fixe local), avec option de répercussion sur l'acheteur.
Tout événement billetté débloque automatiquement le niveau Business — c'est le levier d'acquisition le plus efficace du modèle RSVPify, à reprendre.

### Grille C — Événements personnels
Gratuit jusqu'à 100 invités, puis deux paliers bas coût par événement (pas d'abonnement récurrent — un mariage ne se reproduit pas tous les mois).

### Remises structurelles
- Associations et ONG : −40 %
- Établissements d'enseignement : −30 %
- Engagement annuel : −20 à −35 %
- Programme partenaire / agence : tarification revendeur avec marge

## 13.2 Gestion technique de la facturation

- Moteur de plans avec matrice de fonctionnalités (feature flags)
- Compteurs d'usage transactionnels et fiables
- Prorata automatique lors des changements de plan
- Relance automatique en cas d'échec de prélèvement (J+1, J+3, J+7, puis suspension)
- Période de grâce de 15 jours avant restriction
- Facturation multi-devises avec TVA par pays

---

# 14. INNOVATIONS ET DIFFÉRENCIATEURS PROPOSÉS

> Cette section est le cœur de la valeur ajoutée du projet. Reproduire RSVPify à l'identique ne suffit pas à prendre des parts de marché : il faut résoudre ce que RSVPify résout mal ou pas du tout.

## 14.1 Les faiblesses identifiées chez RSVPify

| Faiblesse | Opportunité |
|---|---|
| Communication centrée e-mail | WhatsApp natif |
| Paiement uniquement Stripe (carte) | Mobile Money, paiement mixte |
| Suppose une connexion permanente | Mode hors ligne complet |
| Tarification élevée en entrée de gamme haut (39 $ → 409 $/mois) | Palier intermédiaire, tarification à l'événement |
| Interface uniquement en anglais | Multilingue réel, y compris langues locales |
| Pas de gestion des intervenants | Module intervenants / programme |
| Pas de gestion budgétaire | Suivi de budget événement |
| Pas de réseau entre organisateurs | Communauté, modèles partagés |
| IA en démonstration, pas encore mature | IA opérationnelle dès le lancement |

## 14.2 Différenciateurs majeurs proposés

### D1 — WhatsApp comme canal de premier rang ⭐⭐⭐
Sur de nombreux marchés (Afrique, Amérique latine, Asie du Sud, Moyen-Orient), l'e-mail est marginal. WhatsApp est **le** canal.

**Implémentation** :
- Invitation envoyée par WhatsApp avec bouton de réponse rapide
- RSVP possible **sans quitter WhatsApp** (Oui / Non / Peut-être en boutons)
- Billet avec QR envoyé en image WhatsApp
- Rappel J-1 automatique
- Bot conversationnel répondant aux questions courantes (horaire, lieu, tenue, parking)
- Conformité stricte aux modèles de message approuvés par Meta

**Impact estimé** : +40 % de taux de réponse sur les marchés concernés.

### D2 — Mode hors ligne complet ⭐⭐⭐
Pas seulement le check-in : **la consultation et la modification de la liste d'invités** hors ligne, avec synchronisation par fusion.
Un salon dans un sous-sol, un gala dans une salle sans wifi, une conférence dans une zone à faible couverture : c'est un cas d'usage massif et mal servi.

### D3 — Paiements réellement locaux ⭐⭐⭐
- Mobile Money natif (M-Pesa, Orange Money, Airtel Money, MTN MoMo, Wave)
- **Paiement mixte** : une partie en ligne, le solde à l'entrée
- **Enregistrement du paiement en espèces** au check-in avec reçu numérique
- Paiement par un tiers (une entreprise paie pour ses 10 collaborateurs)
- Rapprochement automatique des virements par référence unique

### D4 — Assistant IA opérationnel, pas décoratif ⭐⭐
Trois usages concrets :
1. **Création** — « Crée un événement pour la conférence annuelle des partenaires, 300 personnes, le 15 mars à Kinshasa, avec inscription payante à 3 niveaux et un formulaire demandant l'entreprise, la fonction et les préférences alimentaires » → événement complet généré en brouillon.
2. **Analyse** — questions en langage naturel sur ses données, avec graphiques générés.
3. **Rédaction** — génération et adaptation des e-mails et messages WhatsApp au ton de la marque et à la langue de chaque invité.

Plus un **serveur MCP** permettant de connecter la plateforme à l'assistant IA de son choix.

### D5 — Placement de table intelligent ⭐⭐
Au-delà du drag-and-drop : un **optimiseur** qui place les invités selon des contraintes déclarées (garder les familles ensemble, mélanger les entreprises, isoler deux personnes en conflit, équilibrer hommes/femmes, placer les VIP près de la scène), avec plusieurs propositions comparables et retouche manuelle.

### D6 — Module intervenants et programme ⭐⭐
Absent chez RSVPify, indispensable pour les conférences :
- Fiches intervenants (bio, photo, réseaux, session)
- Programme multi-salles avec conflits détectés
- Portail intervenant (dépôt de support, confirmation de créneau, informations logistiques)
- Appel à contributions (soumission de sujets, évaluation, sélection)
- Programme personnalisé par participant (« mon agenda »)

### D7 — Suivi budgétaire de l'événement ⭐⭐
Saisie des postes de dépense (lieu, traiteur, technique, communication, personnel), comparaison prévu/réalisé, calcul automatique du seuil de rentabilité, alerte de dépassement. C'est ce qui transforme l'outil d'un « outil d'inscription » en « outil de pilotage », et justifie un prix plus élevé.

### D8 — Networking entre participants ⭐
- Annuaire des participants (avec consentement explicite)
- Suggestions de mise en relation par centres d'intérêt
- Prise de rendez-vous entre participants pendant l'événement
- Échange de coordonnées par scan de badge entre participants
- Messagerie interne à l'événement

### D9 — Application participant en marque blanche ⭐
Pour les gros événements : une app (ou PWA) aux couleurs de l'organisateur avec programme, plan, notifications, networking, questions au conférencier, sondages en direct.

### D10 — Portail agence multi-clients ⭐
Sous-comptes clients isolés, facturation refacturable, marque de l'agence, modèles partagés, tableau de bord consolidé du portefeuille. RSVPify a une page « agences » mais pas un vrai produit agence. C'est un segment à forte valeur.

### D11 — Bibliothèque de modèles communautaire ⭐
Les organisateurs publient et partagent leurs modèles d'événement. Effet de réseau, contenu SEO gratuit, réduction du temps de création.

### D12 — Durabilité et empreinte ⭐
Calcul de l'empreinte carbone de l'événement (déplacements déclarés, repas, impressions), rapport RSE exportable, encouragement au covoiturage. De plus en plus exigé dans les appels d'offres d'entreprises.

## 14.3 Priorisation des différenciateurs

| Différenciateur | Impact | Effort | Priorité |
|---|:---:|:---:|:---:|
| D1 WhatsApp | Très élevé | Moyen | **1** |
| D2 Hors ligne | Élevé | Moyen | **2** |
| D3 Paiements locaux | Très élevé | Moyen | **3** |
| D5 Placement intelligent | Moyen | Faible | **4** |
| D4 IA opérationnelle | Élevé | Élevé | **5** |
| D6 Intervenants/programme | Élevé | Moyen | 6 |
| D7 Budget | Moyen | Faible | 7 |
| D10 Portail agence | Élevé | Moyen | 8 |
| D8 Networking | Moyen | Élevé | 9 |
| D9 App participant | Moyen | Élevé | 10 |
| D11 Communauté | Faible | Faible | 11 |
| D12 Durabilité | Faible | Faible | 12 |

---

# 15. DÉCOUPAGE EN LOTS ET ROADMAP

## LOT 1 — MVP (mois 1 à 4)

**Objectif** : un organisateur peut créer un événement, inviter, collecter des réponses et accueillir ses invités.

- M0 : compte, organisation, rôles de base (propriétaire, éditeur, accueil)
- M1 : création d'événement, 10 modèles, sous-événements simples
- M2 : constructeur de formulaire, 12 types de champs, logique conditionnelle simple, capacités
- M3 : import de contacts, foyers, tags, +1
- M4 : e-mail (invitation, confirmation, rappel), variables de fusion
- M5 : billetterie carte + Mobile Money, dons simples
- M6 : page événement (1 modèle configurable), charte graphique
- M7 : check-in web + mobile avec **mode hors ligne**, badges PDF
- M8 : tableau de bord temps réel, export CSV
- Facturation : plan gratuit + 2 plans payants

**Livrable** : plateforme utilisable en production sur un vrai événement pilote.

## LOT 2 — Consolidation (mois 5 à 7)

- **WhatsApp Business API** (D1)
- Plan de table complet + placement assisté (D5)
- Séquences automatisées de communication
- Logique conditionnelle avancée (tags, segments)
- Constructeur de page événement complet (multi-blocs)
- Liste d'attente, approbation, codes promo
- Kiosque self check-in, impression thermique
- Intégrations : Google Calendar, GA4, Zapier
- API publique v1 + webhooks
- Multilingue (FR/EN/ES/PT)
- Application mobile publiée sur les stores

## LOT 3 — Montée en gamme (mois 8 à 12)

- Assistant IA + serveur MCP (D4)
- Module intervenants et programme (D6)
- Intégrations CRM natives (HubSpot, Salesforce)
- Suivi budgétaire et ROI (D7)
- Portail agence multi-clients (D10)
- White-label total, domaine personnalisé
- SSO SAML, journal d'audit avancé
- Rapports comparatifs et tableau de bord de portefeuille
- Démarrage de la certification SOC 2

## LOT 4 — Expansion (année 2)

- Networking participants (D8)
- Application participant en marque blanche (D9)
- Marketplace / Event Hub public
- Bibliothèque communautaire (D11)
- Module durabilité (D12)
- Événements hybrides et intégration visio
- Marketplace d'extensions tierces

---

# 16. ORGANISATION PROJET, CHARGES ET BUDGET

## 16.1 Équipe recommandée

| Rôle | Charge | Phase |
|---|---|---|
| Chef de produit / PO | 100 % | Toutes |
| Architecte / Lead développeur | 100 % | Toutes |
| Développeur backend | ×2 à 100 % | Toutes |
| Développeur frontend | ×2 à 100 % | Toutes |
| Développeur mobile | 100 % | Lot 1-2, puis 50 % |
| Designer UI/UX | 100 % lot 1, puis 50 % | Toutes |
| DevOps / SRE | 50 % | Toutes |
| QA / testeur | 100 % à partir du mois 2 | Toutes |
| Rédacteur technique | 30 % | À partir du lot 2 |

## 16.2 Estimation de charge (jours-homme)

| Lot | Backend | Frontend | Mobile | Design | QA | DevOps | **Total** |
|---|---:|---:|---:|---:|---:|---:|---:|
| Lot 1 — MVP | 180 | 150 | 70 | 60 | 60 | 40 | **560** |
| Lot 2 — Consolidation | 140 | 130 | 50 | 40 | 50 | 25 | **435** |
| Lot 3 — Montée en gamme | 170 | 120 | 30 | 35 | 55 | 30 | **440** |
| **Total 12 mois** | **490** | **400** | **150** | **135** | **165** | **95** | **1 435 j/h** |

*Estimation hors gestion de projet (à ajouter ~15 %) et hors imprévus (provision recommandée : 15 %).*

## 16.3 Coûts récurrents d'exploitation (mensuel, à maturité)

| Poste | Estimation |
|---|---|
| Infrastructure cloud (calcul, base, stockage) | 400 – 1 500 $ |
| CDN et bande passante | 100 – 400 $ |
| E-mail transactionnel (500k/mois) | 200 – 600 $ |
| WhatsApp Business API | Variable, à la conversation |
| SMS | À l'usage |
| Observabilité et monitoring | 100 – 300 $ |
| Sauvegardes et PRA | 100 – 300 $ |
| Licences et outils tiers | 300 – 800 $ |
| Certification SOC 2 (annuel) | 20 000 – 40 000 $ |

## 16.4 Méthodologie

- Agile Scrum, sprints de 2 semaines
- Démonstration en fin de chaque sprint
- Revue de code obligatoire, 2 approbations pour la production
- Intégration continue avec tests automatisés bloquants
- Déploiement continu en recette, hebdomadaire en production
- Rétrospective à chaque fin de sprint

---

# 17. RECETTE, TESTS ET CRITÈRES D'ACCEPTATION

## 17.1 Stratégie de test

| Niveau | Couverture cible | Outils |
|---|---|---|
| Tests unitaires | ≥ 75 % du code métier | Jest / PHPUnit |
| Tests d'intégration | 100 % des endpoints API | Supertest / Pest |
| Tests end-to-end | 100 % des parcours critiques | Playwright |
| Tests de charge | Scénarios de pic | k6 / Gatling |
| Tests de sécurité | Continu + audit annuel | OWASP ZAP, Snyk |
| Tests d'accessibilité | Toutes les pages publiques | axe-core |
| Tests manuels exploratoires | Chaque sprint | — |

## 17.2 Parcours critiques à tester systématiquement

1. Créer un événement → publier → s'inscrire → recevoir la confirmation
2. Acheter un billet par carte → recevoir le billet → le scanner au check-in
3. Acheter un billet par Mobile Money (y compris échec et paiement en attente)
4. Import de 5 000 contacts → envoi de masse → suivi de délivrabilité
5. Check-in de 500 invités en mode hors ligne → resynchronisation
6. Deux postes de check-in scannent le même invité simultanément
7. Capacité atteinte → bascule liste d'attente → désistement → promotion automatique
8. Annulation d'événement → remboursement de masse → notification
9. Demande d'effacement RGPD complète
10. Changement de plan avec dépassement de quota

## 17.3 Critères d'acceptation du MVP

| Critère | Seuil |
|---|---|
| Un organisateur non formé crée un événement complet | < 30 min, sans assistance |
| Taux de complétion du formulaire invité (test utilisateur) | > 85 % |
| Check-in de 200 invités en conditions réelles | 0 perte de donnée |
| Temps moyen par check-in | < 8 s |
| Aucune vulnérabilité critique ou élevée | Audit de sécurité |
| Score Lighthouse page RSVP mobile | > 90 |
| Conformité WCAG AA sur les parcours publics | Validée |
| Documentation API complète et testée | 100 % des endpoints |

## 17.4 Événement pilote

Avant la mise en marché, un **événement réel** de 200 à 500 personnes doit être organisé de bout en bout sur la plateforme, avec l'équipe produit présente sur place. C'est la seule recette qui compte vraiment.

---

# 18. MAINTENANCE, SUPPORT ET KPI

## 18.1 Support

| Niveau | Canal | Délai de réponse | Plans |
|---|---|---|---|
| N0 | Centre d'aide, FAQ, tutoriels vidéo | — | Tous |
| N1 | Chat / e-mail | 24 h | Payants |
| N1+ | Chat prioritaire | 4 h | Business |
| N2 | Téléphone / visio | 2 h | Entreprise |
| N3 | Account manager dédié | 1 h | Entreprise |

**Astreinte jour J** : pour les plans Entreprise, une hotline dédiée pendant la durée de l'événement. C'est un argument commercial décisif — un organisateur ne pardonne pas une panne à 19h le soir du gala.

## 18.2 Engagements de service (SLA)

| Sévérité | Définition | Prise en charge | Résolution |
|---|---|---|---|
| S1 — Critique | Plateforme inaccessible, paiements HS, check-in HS pendant un événement | 15 min | 4 h |
| S2 — Majeur | Fonction majeure dégradée | 2 h | 24 h |
| S3 — Mineur | Fonction secondaire, contournement existant | 8 h | 5 j |
| S4 — Cosmétique | Affichage, confort | 24 h | Prochaine version |

## 18.3 KPI produit à suivre

**Acquisition** : visiteurs, taux d'inscription, coût d'acquisition, sources.
**Activation** : % de comptes ayant publié un événement dans les 7 jours, temps jusqu'au premier événement publié.
**Rétention** : % d'organisateurs créant un 2e événement, rétention à 3/6/12 mois, taux d'attrition.
**Revenu** : MRR, ARPU, valeur vie client, taux de conversion gratuit → payant, revenus de commission billetterie.
**Usage** : événements créés/mois, inscriptions traitées, e-mails envoyés, check-ins effectués.
**Satisfaction** : NPS organisateur, NPS invité, CSAT support, volume de tickets par compte.
**Technique** : uptime, latence p95, taux d'erreur, délivrabilité e-mail, taux d'échec de paiement.

---

# 19. ANNEXES

## Annexe A — Glossaire

| Terme | Définition |
|---|---|
| RSVP | Réponse à une invitation (*Répondez s'il vous plaît*) |
| Check-in | Enregistrement de l'arrivée d'un participant |
| No-show | Personne confirmée qui ne s'est pas présentée |
| Walk-in | Personne se présentant sans inscription préalable |
| Foyer | Groupe de contacts répondant ensemble (famille) |
| Sous-événement | Activité au sein d'un événement, avec inscription propre |
| Marque blanche | Suppression de toute mention de la plateforme |
| Multi-tenant | Architecture où plusieurs clients partagent l'infrastructure avec isolation stricte |
| MCP | Protocole permettant à un assistant IA de se connecter à une source de données |
| Idempotence | Propriété d'une opération qui, répétée, produit le même résultat |

## Annexe B — Matrice fonctionnalités × plans

*(à formaliser en tableau détaillé lors du cadrage tarifaire définitif — servir de source unique de vérité pour les feature flags)*

## Annexe C — Risques projet

| Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|
| Complexité sous-estimée du moteur de formulaire | Élevée | Élevé | Prototype dès le sprint 1, périmètre MVP réduit à 12 types de champs |
| Délivrabilité e-mail insuffisante | Moyenne | Élevé | Prestataire réputé, configuration DNS assistée, chauffe progressive |
| Approbation WhatsApp Business API longue | Élevée | Moyen | Démarrer la démarche dès le mois 1 |
| Fiabilité des agrégateurs Mobile Money | Moyenne | Élevé | Multi-fournisseurs, réconciliation automatique, mode dégradé |
| Panne le jour d'un événement client | Faible | Critique | Mode hors ligne, astreinte, plan de continuité, communication de crise préparée |
| Concurrence établie (Cvent, Eventbrite) | Certaine | Moyen | Positionnement sur les différenciateurs D1-D3, marchés mal servis |
| Dérive du périmètre | Élevée | Élevé | Périmètre MVP gelé contractuellement, demandes en backlog V2 |

## Annexe D — Références et sources

- RSVPify — analyse fonctionnelle et tarifaire (rsvpify.com, août 2026)
- Cvent, Eventbrite, Swoogo, Splash, Bizzabo — analyse concurrentielle
- RGPD (Règlement UE 2016/679)
- WCAG 2.1 niveau AA
- OWASP Top 10
- schema.org/Event

---

**Fin du document**

*Ce cahier des charges est un document vivant. Chaque évolution du périmètre doit faire l'objet d'un avenant validé par le comité de pilotage.*
