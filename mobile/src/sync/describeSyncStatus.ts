export type SyncStatusTone = 'online' | 'offline' | 'syncing' | 'pending';

export interface SyncStatusDescription {
  label: string;
  tone: SyncStatusTone;
}

/**
 * Logique pure de l'indicateur d'état (AC de T-061c) : en ligne / hors
 * ligne / en cours de synchro / N en attente — séparée de SyncStatusBadge
 * pour rester testable sans dépendre de react-native.
 *
 * Ordre de priorité d'affichage : hors ligne prime sur tout (aucun intérêt
 * à afficher "en attente" quand on ne peut de toute façon rien envoyer),
 * la synchro en cours prime sur "en attente" (elle est justement en train
 * de vider cette file).
 */
export function describeSyncStatus(online: boolean, syncing: boolean, pendingCount: number): SyncStatusDescription {
  if (!online) {
    return { label: 'Hors ligne', tone: 'offline' };
  }

  if (syncing) {
    return { label: 'Synchronisation…', tone: 'syncing' };
  }

  if (pendingCount > 0) {
    return { label: `${pendingCount} en attente`, tone: 'pending' };
  }

  return { label: 'En ligne', tone: 'online' };
}
