import { Q } from '@nozbe/watermelondb';
import { syncCheckIns } from '../../api/checkIns';
import { type CheckInScan } from '../../api/types';
import { database } from '../database';
import PendingCheckIn from '../models/PendingCheckIn';

const MAX_SCANS_PER_REQUEST = 500;

/**
 * Envoie la file d'attente locale au serveur (T-061c). Un "conflit" renvoyé
 * par l'API n'est pas une erreur de synchronisation : c'est un autre poste
 * qui a déjà enregistré cet invité entre-temps (M7.1.4). Un "rejected"
 * (jeton QR invalide à la vérification serveur, §4.6 CLAUDE.md) est, de la
 * même façon, un verdict définitif et non une panne passagère. La ligne
 * locale est marquée synced dans les trois cas (accepted, conflict,
 * rejected) ; seule une vraie erreur réseau/serveur la laisse en attente
 * pour une prochaine tentative. `guests.checked_in` n'a pas besoin d'être
 * retouché ici : il est déjà à true depuis recordLocalCheckIn, qu'il
 * s'agisse au final d'un accepted ou d'un conflict (un rejected, lui,
 * n'avait jamais mis `checked_in` à true côté serveur — seulement en local,
 * de façon optimiste ; en rester là reste la limite acceptée de l'écriture
 * hors ligne).
 */
export async function syncPendingCheckIns(eventId: number, token: string): Promise<number> {
  const collection = database.get<PendingCheckIn>('pending_check_ins');
  const pending = await collection.query(Q.where('event_id', eventId), Q.where('synced', false)).fetch();

  if (pending.length === 0) {
    return 0;
  }

  const batch = pending.slice(0, MAX_SCANS_PER_REQUEST);
  const scans: CheckInScan[] = batch.map((record) => ({
    ...(record.guestType === 'attendee' ? { attendee_id: record.remoteId } : { ticket_id: record.remoteId }),
    ...(record.qrToken !== null ? { qr_token: record.qrToken } : {}),
    device_local_id: record.deviceLocalId,
    direction: 'check_in',
    recorded_at: record.recordedAt,
  }));

  const results = await syncCheckIns(eventId, token, scans);
  const syncedDeviceLocalIds = new Set(results.map((result) => result.device_local_id));

  await database.write(async () => {
    await database.batch(
      ...batch
        .filter((record) => syncedDeviceLocalIds.has(record.deviceLocalId))
        .map((record) =>
          record.prepareUpdate((updated) => {
            updated.synced = true;
          }),
        ),
    );
  });

  return syncedDeviceLocalIds.size;
}
