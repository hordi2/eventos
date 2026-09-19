# À faire plus tard

Ce qui ne bloque rien aujourd'hui, mais qu'il ne faudra pas oublier. Cocher
chaque ligne une fois faite.

## Le jour de la mise en ligne

Le projet n'est pas encore hébergé : ces réglages se posent chez l'hébergeur
(fichier `.env` du serveur ou page « Variables d'environnement »).

- [ ] **Clé Pexels** : ajouter `PEXELS_API_KEY`. Sans elle, l'onglet
      « Bibliothèque » du constructeur affiche seulement une explication. La
      clé actuelle est apparue dans une conversation : en créer une nouvelle
      sur pexels.com/api pour la production est plus prudent.
- [ ] **Antivirus ClamAV** : un démon `clamd` joignable, et son adresse dans
      `CLAMAV_ADDRESS`. Sans lui, les fichiers joints des invités ne sont
      jamais déclarés sains et restent inaccessibles à l'organisateur.
- [ ] **Traitement en arrière-plan** : un worker de file d'attente (Horizon)
      qui tourne en permanence — analyse antivirus, e-mails et exports en
      dépendent.
- [ ] **Tâches planifiées** : `php artisan schedule:run` chaque minute
      (purge des fichiers jamais rattachés à une inscription, entre autres).
- [ ] **Images publiques** : le disque `public` doit être servi —
      `php artisan storage:link`, ou un disque R2 public. Logos, fonds,
      images des blocs et « Mes images » en dépendent.
- [ ] **Réglages PHP** : extensions `gd` et `exif` (redimensionnement des
      images), `upload_max_filesize` ≥ 12M et `post_max_size` ≥ 64M (fichiers
      joints). Déjà réglés dans l'image Docker du projet.

## Dans le code (tickets à créer)

- [ ] **Publier un événement sans formulaire** : la publication d'un
      événement n'exige pas encore un formulaire publié (critère du cahier
      des charges, M1.2). Un commentaire l'annonce dans `PublishEvent` depuis
      le début du projet ; un invité tombe aujourd'hui sur une page
      introuvable.
- [ ] **Rapports par formulaire** : avec plusieurs formulaires, « Réponses
      aux questions », « Préférences alimentaires » et l'export réunissent
      les questions de tous les formulaires (rapprochées par clé). Un filtre
      « un seul formulaire » pourra venir si le besoin se présente.

## Sur le Mac

- [ ] **Sortir le projet d'iCloud** : le dossier est dans « Documents »,
      synchronisé avec iCloud, dont le stockage est saturé — tous les
      fichiers du projet affichent « Erreur » dans le Finder. iCloud peut
      retirer des fichiers du Mac ou créer des doublons, jusque dans
      l'historique git. À faire ensemble, jamais pendant qu'une session
      travaille sur le projet.
