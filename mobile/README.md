# Itaza Check-in — application mobile (T-061a)

Socle de l'application de check-in hors ligne (M7.1, différenciateur D2) :
authentification via l'API de check-in (T-060), téléchargement préalable de la
liste des invités d'un événement dans une base locale (WatermelonDB/SQLite),
recherche instantanée hors ligne, indicateur de connectivité.

Le scan QR et l'écriture d'un check-in en local (T-061b) ainsi que la
synchronisation et la résolution de conflits (T-061c) sont hors périmètre de
ce sous-ticket — la liste téléchargée ici est pour l'instant en lecture seule.

## Stack

- Expo (React Native) + TypeScript
- WatermelonDB (SQLite local, nécessite du code natif — voir plus bas)
- expo-secure-store (jeton d'appareil, chiffré)
- @react-native-community/netinfo (indicateur en ligne/hors ligne)

## Important — limites de vérification de cette implémentation

Cet environnement de développement n'a ni Xcode complet ni Android Studio/SDK
installés : le code n'a **jamais tourné sur un simulateur ou un appareil**
pendant son écriture. Ce qui a été vérifié :

- `npx tsc --noEmit` : aucune erreur de typage.
- `npx expo export --platform ios` et `--platform android` : le bundle Metro
  se construit sans erreur (résolution des imports, JSX, décorateurs
  WatermelonDB) — signal fort que le code est structurellement correct, mais
  **ne garantit ni le rendu visuel, ni le comportement runtime réel** (accès
  au SQLite natif, permissions caméra, etc.).

Avant de considérer T-061a terminé, il faut donc le lancer une fois sur un
poste avec Xcode et/ou Android Studio installés (voir "Lancer l'application"
ci-dessous) et vérifier au minimum : connexion, téléchargement de la liste,
recherche, indicateur de connectivité.

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

## Utilisation

1. Se connecter avec un compte ayant l'habilitation `checkIn` sur
   l'organisation (Owner/Admin/Editor/DoorStaff — même API que le check-in
   web, T-062).
2. Saisir l'identifiant numérique de l'événement (temporaire : un vrai
   sélecteur d'événement n'est pas prévu avant un futur ticket) puis
   "Télécharger la liste".
3. Rechercher un invité par nom, e-mail ou téléphone — entièrement local,
   fonctionne hors ligne une fois la liste téléchargée.
