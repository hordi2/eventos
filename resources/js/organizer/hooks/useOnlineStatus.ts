import { useSyncExternalStore } from 'react';

function subscribe(callback: () => void): () => void {
    window.addEventListener('online', callback);
    window.addEventListener('offline', callback);

    return () => {
        window.removeEventListener('online', callback);
        window.removeEventListener('offline', callback);
    };
}

/**
 * État réseau du navigateur, utile pour distinguer un scan jamais parti
 * (hors connexion) d'un scan parti mais resté sans réponse.
 */
export default function useOnlineStatus(): boolean {
    return useSyncExternalStore(subscribe, () => navigator.onLine, () => true);
}
