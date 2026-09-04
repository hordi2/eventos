import { createTable, schemaMigrations } from '@nozbe/watermelondb/Schema/migrations';

/**
 * v1 -> v2 (T-061b) : ajoute pending_check_ins, la file d'attente des
 * check-ins enregistrés hors ligne. N'affecte jamais `guests` : un appareil
 * déjà en service (T-061a) garde sa liste téléchargée intacte à la mise à
 * jour de l'application.
 */
export const migrations = schemaMigrations({
  migrations: [
    {
      toVersion: 2,
      steps: [
        createTable({
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
    },
  ],
});
