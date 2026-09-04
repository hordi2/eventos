# Itaza Check-in — application mobile (T-061a + T-061b)

Application de check-in hors ligne (M7.1, différenciateur D2) : authentification
via l'API de check-in (T-060), téléchargement préalable de la liste des invités
d'un événement dans une base locale (WatermelonDB/SQLite), recherche instantanée
hors ligne, indicateur de connectivité (T-061a) — scan de QR par caméra et
enregistrement d'un check-in entièrement local, avec alerte immédiate si
l'invité est déjà marqué présent (T-061b).

La synchronisation avec le serveur (envoi de la file d'attente des check-ins
locaux, résolution de conflits) est T-061c, pas encore fait : un check-in
enregistré ici reste local tant que ce sous-ticket n'est pas livré.

## Stack

- Expo (React Native) + TypeScript
- WatermelonDB (SQLite local, nécessite du code natif — voir plus bas)
- expo-secure-store (jeton d'appareil, chiffré)
- expo-camera (scan QR)
- expo-crypto (génération d'UUID pour `device_local_id`)
- @react-native-community/netinfo (indicateur en ligne/hors ligne)

## Important — limites de vérification de cette implémentation

Cet environnement de développement n'a ni Xcode complet ni Android Studio/SDK
installés : le code n'a **jamais tourné sur un simulateur ou un appareil**
pendant son écriture. Ce qui a été vérifié :

- `npm run typecheck` (`tsc --noEmit`) : aucune erreur de typage.
- `npx expo export --platform ios` et `--platform android` : le bundle Metro
  se construit sans erreur (résolution des imports, JSX, décorateurs
  WatermelonDB) — signal fort que le code est structurellement correct, mais
  **ne garantit ni le rendu visuel, ni le comportement runtime réel** (accès
  au SQLite natif, permissions caméra, lecture effective d'un QR, etc.).
- `npm test` : tests unitaires Jest sur la logique pure sans dépendance
  native — pour l'instant uniquement le décodage du jeton QR
  (`src/api/__tests__/qrToken.test.ts`). Tout le reste (WatermelonDB,
  expo-camera...) ne peut être testé que sur un appareil ou un simulateur
  réel, hors de portée de cet environnement.

Avant de considérer T-061a/T-061b terminés, il faut donc lancer l'application
une fois sur un poste avec Xcode et/ou Android Studio installés (voir "Lancer
l'application" ci-dessous) et vérifier au minimum : connexion, téléchargement
de la liste, recherche, indicateur de connectivité, scan d'un vrai QR de
billet, alerte sur un second scan du même billet, mode avion.

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
