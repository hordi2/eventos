import { Q } from '@nozbe/watermelondb';
import { database } from './database';
import PendingCheckIn from './models/PendingCheckIn';

/**
 * "N en attente" de l'indicateur d'état (AC de T-061c).
 */
export async function getPendingCheckInCount(eventId: number): Promise<number> {
  return database
    .get<PendingCheckIn>('pending_check_ins')
    .query(Q.where('event_id', eventId), Q.where('synced', false))
    .fetchCount();
}
