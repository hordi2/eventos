import { useNetInfo } from '@react-native-community/netinfo';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import { getPendingCheckInCount } from '../db/getPendingCheckInCount';
import { syncPendingCheckIns } from '../db/sync/syncPendingCheckIns';

export interface SyncEngine {
  online: boolean;
  syncing: boolean;
  pendingCount: number;
  refreshPendingCount: () => void;
  triggerSync: () => void;
}

// Ni trop rapproché (consommation batterie, AC de T-061b/c : 4 h en continu
// sur un téléphone d'entrée de gamme — un appel réseau toutes les 30 s reste
// négligeable) ni trop espacé (la file d'attente ne doit pas s'accumuler
// silencieusement en cas de reconnexion manquée par NetInfo).
const PERIODIC_SYNC_INTERVAL_MS = 30_000;

/**
 * Synchronisation automatique (T-061c, AC : « reconnexion -> synchronisation
 * automatique... sans action manuelle »). Un seul déclencheur actif à la
 * fois (syncingRef) : la reconnexion et le minuteur périodique peuvent
 * coïncider, jamais deux envois concurrents de la même file.
 */
export function useSyncEngine(eventId: number | null): SyncEngine {
  const { token } = useAuth();
  const netInfo = useNetInfo();
  const online = netInfo.isConnected === true && netInfo.isInternetReachable !== false;

  const [syncing, setSyncing] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);
  const syncingRef = useRef(false);
  const wasOnlineRef = useRef(online);

  const refreshPendingCount = useCallback(() => {
    if (eventId !== null) {
      getPendingCheckInCount(eventId).then(setPendingCount);
    }
  }, [eventId]);

  const triggerSync = useCallback(() => {
    if (eventId === null || token === null || !online || syncingRef.current) {
      return;
    }

    syncingRef.current = true;
    setSyncing(true);

    syncPendingCheckIns(eventId, token)
      .catch(() => {
        // Échec réseau/serveur : la file reste intacte, retentée à la
        // prochaine reconnexion ou au prochain minuteur périodique.
      })
      .finally(() => {
        syncingRef.current = false;
        setSyncing(false);
        refreshPendingCount();
      });
  }, [eventId, token, online, refreshPendingCount]);

  useEffect(() => {
    refreshPendingCount();
  }, [refreshPendingCount]);

  useEffect(() => {
    if (online && !wasOnlineRef.current) {
      triggerSync();
    }

    wasOnlineRef.current = online;
  }, [online, triggerSync]);

  useEffect(() => {
    if (!online) {
      return;
    }

    const interval = setInterval(triggerSync, PERIODIC_SYNC_INTERVAL_MS);

    return () => clearInterval(interval);
  }, [online, triggerSync]);

  return { online, syncing, pendingCount, refreshPendingCount, triggerSync };
}
