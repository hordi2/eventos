import * as SecureStore from 'expo-secure-store';

const TOKEN_KEY = 'itaza_checkin_token';

/**
 * expo-secure-store (Keychain iOS / Keystore Android) plutôt qu'AsyncStorage :
 * le jeton d'appareil doit survivre à la réinstallation et rester illisible
 * hors du bac à sable de l'application — même exigence que tout jeton
 * d'authentification longue durée.
 */
export async function saveToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function loadToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function clearToken(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}
