# T-078 — Checklist de préparation de l'événement pilote

> Ce document accompagne T-078 du backlog (`docs/backlog-lot1-mvp.md`).
> Il ne remplace pas l'événement lui-même : c'est un vrai événement de 200 à
> 500 personnes, avec une équipe présente sur place, qui doit avoir lieu.
> Cette checklist est l'outillage de préparation que le logiciel seul peut
> fournir — le test de charge automatisé (`tests/Pilot/CheckInLoadTest.php`)
> couvre la partie « le serveur n'est pas le goulot d'étranglement » de l'AC
> « temps moyen de check-in < 8 s » ; tout le reste (réseau du lieu,
> imprimante, formation des bénévoles...) ne peut être vérifié que sur le
> terrain.

## J-30 à J-14

- [ ] Événement créé et publié sur la plateforme, formulaire d'inscription
      finalisé et testé de bout en bout par une personne qui n'a pas participé
      à sa construction.
- [ ] Charte graphique de l'organisation appliquée (logo, couleur) — page
      événement et e-mails.
- [ ] Si billetterie payante : types de billets et paliers créés, un achat de
      test effectué en carte **et** en Mobile Money jusqu'à réception du
      billet.
- [ ] Modèles WhatsApp soumis à l'approbation Meta si ce canal doit être
      utilisé — l'approbation peut prendre plusieurs jours, à lancer tôt.
- [ ] `tests/Pilot/CheckInLoadTest.php` exécuté sur l'environnement qui
      servira réellement le jour J (pas seulement en local) :
      `./vendor/bin/pest tests/Pilot`.

## J-14 à J-7

- [ ] Application mobile de check-in installée et testée sur tous les
      appareils qui serviront le jour J (nombre à définir selon l'affluence
      attendue aux points d'entrée).
- [ ] Chaque appareil authentifié une première fois **avec connexion**, pour
      vérifier que le jeton et la liste des invités se téléchargent
      correctement.
- [ ] Répétition à blanc : couper le réseau de l'appareil, scanner plusieurs
      billets de test, reconnecter, vérifier que la synchronisation se fait
      sans doublon.
- [ ] Export CSV de la liste complète des invités généré et imprimé — plan de
      secours total si tous les appareils tombent en panne en même temps.
- [ ] Alimentation : nombre de prises/batteries externes suffisant pour tenir
      toute la durée de l'événement sans recharge.
- [ ] Contacts d'astreinte technique identifiés et communiqués à l'équipe sur
      place (qui appeler, dans quel ordre, en cas de panne S1/S2 — voir le
      gabarit de compte rendu pour les niveaux de sévérité).

## J-1

- [ ] Page de statut publique (`/status`, T-076) vérifiée verte.
- [ ] Tous les appareils de check-in chargés à 100 % et la liste des invités
      re-synchronisée une dernière fois (dernières inscriptions incluses).
- [ ] Export CSV papier de secours réimprimé avec les toutes dernières
      inscriptions.
- [ ] Rôles assignés : qui scanne, qui gère les walk-ins, qui gère les
      incidents techniques, qui remplit le compte rendu en temps réel.

## Jour J

- [ ] Le compte rendu d'incidents (`docs/evenement-pilote-compte-rendu-gabarit.md`)
      est ouvert et tenu à jour en continu par une personne dédiée, pas
      reconstitué de mémoire après coup.
- [ ] Un membre de l'équipe surveille `/status` pendant toute la durée de
      l'affluence aux points d'entrée.
- [ ] Toute bascule sur le plan de secours papier est notée avec l'heure et
      la raison, même si elle est brève.

## Après l'événement

- [ ] Compte rendu finalisé : incidents classés par sévérité (§18.2 du
      cahier des charges), zéro perte de donnée confirmée (comparer le
      nombre de check-ins enregistrés au nombre de passages réels comptés
      sur place), temps moyen de check-in mesuré et comparé au seuil de 8 s.
- [ ] Correctifs bloquants (tout incident S1/S2 avec cause logicielle)
      identifiés, transformés en tickets, et livrés avant la mise en marché
      — c'est la condition explicite de clôture de T-078, pas une option.
