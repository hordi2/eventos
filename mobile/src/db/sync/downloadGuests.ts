import { Q } from '@nozbe/watermelondb';
import { fetchEventGuests } from '../../api/guests';
import { type Guest as ApiGuest } from '../../api/types';
import { database } from '../database';
import GuestModel from '../models/Guest';

/**
 * Téléchargement préalable (M7.1.3, T-061a) : remplace entièrement la liste
 * locale de l'événement par celle de l'API — plus simple et plus sûr qu'un
 * diff incrémental pour ce sous-ticket (aucun check-in local à préserver
 * pendant ce téléchargement, la file d'attente de check-ins arrive avec
 * T-061b, sur une table séparée que ce remplacement ne touche pas).
 */
export async function downloadEventGuests(eventId: number, token: string): Promise<number> {
  const guests = await fetchEventGuests(eventId, token);

  const collection = database.get<GuestModel>('guests');

  await database.write(async () => {
    const existing = await collection.query(Q.where('event_id', eventId)).fetch();

    await database.batch(
      ...existing.map((record) => record.prepareDestroyPermanently()),
      ...guests.map((guest: ApiGuest) =>
        collection.prepareCreate((record) => {
          record.eventId = eventId;
          record.guestType = guest.guest_type;
          record.remoteId = guest.id;
          record.name = guest.name;
          record.email = guest.email;
          record.phone = guest.phone;
          record.checkedIn = guest.checked_in;
          record.checkedInAt = guest.checked_in_at;
        }),
      ),
    );
  });

  return guests.length;
}
