import { addColumns, createTable, schemaMigrations } from '@nozbe/watermelondb/Schema/migrations';

/**
 * v1 -> v2 (T-061b) : ajoute pending_check_ins, la file d'attente des
 * check-ins enregistrés hors ligne. N'affecte jamais `guests` : un appareil
 * déjà en service (T-061a) garde sa liste téléchargée intacte à la mise à
 * jour de l'application.
 *
 * v2 -> v3 : ajoute qr_token à pending_check_ins, pour transmettre le jeton
 * brut scanné à la vérification serveur (faille de contournement du
 * check-in mobile) sans rien perdre des lignes déjà en attente.
 */
export const migrations = schemaMigrations({
  migrations: [
    {
      toVersion: 3,
      steps: [
        addColumns({
          table: 'pending_check_ins',
          columns: [{ name: 'qr_token', type: 'string', isOptional: true }],
        }),
      ],
    },
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
