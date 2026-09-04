import { Q } from '@nozbe/watermelondb';
import { database } from './database';
import GuestModel from './models/Guest';

/**
 * Recherche locale instantanée (AC de T-061a) : SQLite, aucune connexion
 * réseau. LIKE insensible à la casse via Q.like + Q.sanitizeLikeString,
 * cohérent avec le "ilike" déjà utilisé côté serveur (GetEventGuestList).
 */
export async function searchLocalGuests(eventId: number, term: string): Promise<GuestModel[]> {
  const collection = database.get<GuestModel>('guests');

  if (term.trim() === '') {
    return collection.query(Q.where('event_id', eventId), Q.sortBy('name', Q.asc)).fetch();
  }

  const pattern = `%${Q.sanitizeLikeString(term.trim())}%`;

  return collection
    .query(
      Q.where('event_id', eventId),
      Q.or(Q.where('name', Q.like(pattern)), Q.where('email', Q.like(pattern)), Q.where('phone', Q.like(pattern))),
      Q.sortBy('name', Q.asc),
    )
    .fetch();
}
