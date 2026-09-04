import { apiRequest } from './client';
import { type CheckInScan, type CheckInSyncResult } from './types';

interface SyncResponse {
  data: CheckInSyncResult[];
}

/**
 * Synchronisation par lot (T-060, T-061c) : jusqu'à 500 scans par appel
 * (limite validée côté serveur, SyncCheckInsRequest) — recordLocalCheckIn
 * a déjà garanti l'idempotence via device_local_id, un envoi partiel ou
 * rejoué ne crée donc jamais de doublon.
 */
export async function syncCheckIns(eventId: number, token: string, scans: CheckInScan[]): Promise<CheckInSyncResult[]> {
  const response = await apiRequest<SyncResponse>(`/events/${eventId}/check-ins/sync`, {
    method: 'POST',
    token,
    body: { scans },
  });

  return response.data;
}
