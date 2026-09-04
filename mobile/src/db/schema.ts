import { appSchema, tableSchema } from '@nozbe/watermelondb';

/**
 * `guests` (T-061a) : la liste téléchargée depuis l'API de check-in (T-060).
 * remote_id + guest_type identifient l'invité côté serveur (attendee_id ou
 * ticket_id, jamais les deux) — voir GuestData côté Laravel.
 *
 * `pending_check_ins` (T-061b) : chaque scan/check-in enregistré en local,
 * en attente d'envoi au serveur. device_local_id est l'identifiant
 * d'idempotence déjà utilisé par l'API (§4.4 du CLAUDE.md, RecordCheckIn) —
 * généré ici, jamais côté serveur, pour que l'écriture locale n'ait besoin
 * d'aucune connexion. synced distingue les lignes encore à envoyer (T-061c)
 * de celles déjà confirmées par le serveur, conservées pour l'historique.
 */
export const schema = appSchema({
  version: 2,
  tables: [
    tableSchema({
      name: 'guests',
      columns: [
        { name: 'event_id', type: 'number', isIndexed: true },
        { name: 'guest_type', type: 'string' },
        { name: 'remote_id', type: 'number', isIndexed: true },
        { name: 'name', type: 'string' },
        { name: 'email', type: 'string', isOptional: true },
        { name: 'phone', type: 'string', isOptional: true },
        { name: 'checked_in', type: 'boolean' },
        { name: 'checked_in_at', type: 'string', isOptional: true },
      ],
    }),
    tableSchema({
      name: 'pending_check_ins',
      columns: [
        { name: 'event_id', type: 'number', isIndexed: true },
        { name: 'guest_type', type: 'string' },
        { name: 'remote_id', type: 'number' },
        { name: 'device_local_id', type: 'string', isIndexed: true },
        { name: 'recorded_at', type: 'string' },
        { name: 'synced', type: 'boolean', isIndexed: true },
      ],
    }),
  ],
});
