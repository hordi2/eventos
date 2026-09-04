import { Q } from '@nozbe/watermelondb';
import * as Crypto from 'expo-crypto';
import { database } from './database';
import Guest from './models/Guest';
import PendingCheckIn from './models/PendingCheckIn';

export type LocalCheckInResult =
  | { status: 'accepted'; guest: Guest }
  | { status: 'conflict'; guest: Guest }
  | { status: 'not_found' };

/**
 * Écriture 100 % locale (T-061b, AC : « mode avion complet... zéro perte »)
 * — aucun appel réseau ici, la synchronisation est T-061c. L'alerte anti-
 * fraude (M7.1.11) se lit directement sur la table `guests` déjà en local :
 * pas besoin du serveur pour savoir qu'un invité est déjà entré.
 *
 * device_local_id est généré ici (jamais côté serveur) : c'est la clé
 * d'idempotence de la synchronisation par lot (§4.4 CLAUDE.md, RecordCheckIn
 * côté API), déjà valable avant même que l'appareil ne soit reconnecté.
 */
export async function recordLocalCheckIn(
  eventId: number,
  guestType: 'attendee' | 'ticket',
  remoteId: number,
): Promise<LocalCheckInResult> {
  const guestsCollection = database.get<Guest>('guests');
  const matches = await guestsCollection
    .query(Q.where('event_id', eventId), Q.where('guest_type', guestType), Q.where('remote_id', remoteId))
    .fetch();
  const guest = matches[0];

  if (guest === undefined) {
    return { status: 'not_found' };
  }

  if (guest.checkedIn) {
    return { status: 'conflict', guest };
  }

  const recordedAt = new Date().toISOString();
  const deviceLocalId = Crypto.randomUUID();

  await database.write(async () => {
    await guest.update((record) => {
      record.checkedIn = true;
      record.checkedInAt = recordedAt;
    });

    await database.get<PendingCheckIn>('pending_check_ins').create((record) => {
      record.eventId = eventId;
      record.guestType = guestType;
      record.remoteId = remoteId;
      record.deviceLocalId = deviceLocalId;
      record.recordedAt = recordedAt;
      record.synced = false;
    });
  });

  return { status: 'accepted', guest };
}
