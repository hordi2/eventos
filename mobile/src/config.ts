/**
 * URL de base de l'API de check-in (T-060). EXPO_PUBLIC_API_URL est lue au
 * build (convention Expo pour les variables exposées au bundle JS) — à
 * définir dans un fichier .env local pointant vers l'instance Laravel
 * accessible depuis l'appareil (jamais localhost sur un téléphone physique,
 * voir README.md).
 */
export const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1';
