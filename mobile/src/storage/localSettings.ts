import * as SecureStore from 'expo-secure-store';

const EVENT_ID_KEY = 'itaza_checkin_event_id';

/**
 * Dernier événement utilisé sur ce poste — pas une donnée sensible, mais
 * expo-secure-store est déjà en place pour le jeton (tokenStorage.ts) et
 * évite d'ajouter AsyncStorage comme deuxième mécanisme de stockage pour
 * une seule valeur.
 */
export async function saveEventId(eventId: number): Promise<void> {
  await SecureStore.setItemAsync(EVENT_ID_KEY, String(eventId));
}

export async function loadEventId(): Promise<number | null> {
  const value = await SecureStore.getItemAsync(EVENT_ID_KEY);

  return value !== null ? Number(value) : null;
}
