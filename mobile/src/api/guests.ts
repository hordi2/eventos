import { apiRequest } from './client';
import { type Guest } from './types';

/**
 * GuestResource::collection(...)->response() (T-060) enveloppe toujours la
 * liste dans une clé "data".
 */
interface GuestListResponse {
  data: Guest[];
}

export async function fetchEventGuests(eventId: number, token: string): Promise<Guest[]> {
  const response = await apiRequest<GuestListResponse>(`/events/${eventId}/guests`, { token });

  return response.data;
}
