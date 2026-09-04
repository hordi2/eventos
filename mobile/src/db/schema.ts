import { appSchema, tableSchema } from '@nozbe/watermelondb';

/**
 * Une seule table pour ce sous-ticket (T-061a) : la liste des invités
 * téléchargée depuis l'API de check-in (T-060). remote_id + guest_type
 * identifient l'invité côté serveur (attendee_id ou ticket_id, jamais les
 * deux) — voir GuestData côté Laravel. La file d'attente des check-ins
 * enregistrés localement (pending_check_ins) est ajoutée par T-061b, pas
 * ici : ce ticket ne fait que le socle et le téléchargement.
 */
export const schema = appSchema({
  version: 1,
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
  ],
});
