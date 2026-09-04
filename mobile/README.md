# Itaza Check-in — application mobile (T-061a + T-061b + T-061c)

Application de check-in hors ligne (M7.1, différenciateur D2), complète pour
son périmètre MVP : authentification via l'API de check-in (T-060),
téléchargement préalable de la liste des invités dans une base locale
(WatermelonDB/SQLite), recherche instantanée hors ligne (T-061a) — scan de QR
par caméra et enregistrement d'un check-in entièrement local, avec alerte
immédiate si l'invité est déjà marqué présent (T-061b) — synchronisation
automatique de la file d'attente dès la reconnexion, résolution de conflit,
indicateur d'état complet (T-061c).

## Stack

- Expo (React Native) + TypeScript
- WatermelonDB (SQLite local, nécessite du code natif — voir plus bas)
- expo-secure-store (jeton d'appareil, chiffré)
- expo-camera (scan QR)
- expo-crypto (génération d'UUID pour `device_local_id`)
- @react-native-community/netinfo (détection de connectivité)

## Comment fonctionne la synchronisation (T-061c)

Chaque check-in local (scan ou mode liste) pose une ligne dans
`pending_check_ins` (non envoyée). `useSyncEngine` (un hook par écran, monté
avec l'événement courant) déclenche l'envoi par lot vers
`POST /events/{id}/check-ins/sync` :

- automatiquement à la reconnexion (transition hors ligne -> en ligne détectée
  par NetInfo),
- par un minuteur toutes les 30 s tant que l'appareil est en ligne (rattrape
  les cas où NetInfo manquerait une transition),
- juste après chaque check-in local si déjà en ligne, pour vider la file au
  plus vite sans attendre le prochain tick.

Un « conflict » renvoyé par le serveur (un autre poste a déjà enregistré cet
invité entre-temps) n'est pas un échec de synchronisation : la ligne locale
est tout de même marquée comme envoyée, seule une vraie erreur réseau/serveur
la laisse en attente pour une prochaine tentative. `device_local_id` (généré
au moment du scan, jamais côté serveur) garantit qu'un même check-in rejoué
plusieurs fois ne crée jamais de doublon (§4.4 CLAUDE.md).

## Important — limites de vérification de cette implémentation

Cet environnement de développement n'a ni Xcode complet ni Android Studio/SDK
installés : le code n'a **jamais tourné sur un simulateur ou un appareil**
pendant son écriture. Ce qui a été vérifié :

- `npm run typecheck` (`tsc --noEmit`) : aucune erreur de typage.
- `npx expo export --platform ios` et `--platform android` : le bundle Metro
  se construit sans erreur (résolution des imports, JSX, décorateurs
  WatermelonDB) — signal fort que le code est structurellement correct, mais
  **ne garantit ni le rendu visuel, ni le comportement runtime réel** (accès
  au SQLite natif, permissions caméra, vraie transition réseau NetInfo, etc.).
- `npm test` : tests unitaires Jest sur la logique pure sans dépendance
  native — décodage du jeton QR et priorité d'affichage de l'indicateur de
  synchronisation. Tout le reste (WatermelonDB, expo-camera, NetInfo...) ne
  peut être testé que sur un appareil ou un simulateur réel, hors de portée
  de cet environnement.

Avant de considérer T-061a/T-061b/T-061c terminés, il faut donc lancer
l'application une fois sur un poste avec Xcode et/ou Android Studio installés
(voir "Lancer l'application" ci-dessous) et vérifier au minimum : connexion,
téléchargement de la liste, recherche, scan d'un vrai QR de billet, alerte sur
un second scan du même billet, mode avion suivi d'une reconnexion (la file
d'attente doit se vider automatiquement), et l'indicateur dans ses quatre
états (en ligne / hors ligne / synchronisation / N en attente).

## Configuration

```bash
cp .env.example .env
# éditer EXPO_PUBLIC_API_URL — jamais localhost pour un appareil physique
```

## Installer

```bash
npm install
```

## Lancer l'application

WatermelonDB embarque du code natif (accès SQLite via JSI) : **incompatible
avec Expo Go**. Il faut un build de développement :

```bash
npx expo prebuild
npx expo run:ios      # nécessite Xcode
npx expo run:android  # nécessite Android Studio / le SDK Android
```

## Tests

```bash
npm run typecheck
npm test
```

## Utilisation

1. Se connecter avec un compte ayant l'habilitation `checkIn` sur
   l'organisation (Owner/Admin/Editor/DoorStaff — même API que le check-in
   web, T-062).
2. Saisir l'identifiant numérique de l'événement (temporaire : un vrai
   sélecteur d'événement n'est pas prévu avant un futur ticket) puis
   "Télécharger la liste".
3. Rechercher un invité par nom, e-mail ou téléphone — entièrement local,
   fonctionne hors ligne une fois la liste téléchargée. Bouton "Check-in"
   pour enregistrer sans scanner (mode liste, M7.1.12).
4. Bouton "Scanner" pour ouvrir la caméra : chaque billet scanné est
   enregistré localement, avec retour visuel immédiat (accepté / déjà
   enregistré / illisible). Fonctionne entièrement en mode avion.
5. L'indicateur en haut de l'écran (en ligne / hors ligne / synchronisation /
   N en attente) reflète l'état de la file de check-ins non encore envoyés au
   serveur — aucune action manuelle n'est nécessaire pour la vider une fois
   la connexion revenue.
