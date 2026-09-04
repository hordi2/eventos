/**
 * Jest ciblé sur la logique pure uniquement (ex. décodage du QR) : le reste
 * du code dépend de modules natifs (WatermelonDB, expo-camera...) qui ne
 * tournent que sur un appareil ou un simulateur réel, indisponibles dans cet
 * environnement — voir README.md. testEnvironment "node" plutôt que le
 * preset jest-expo, pour ne pas dépendre des mocks React Native ici.
 */
module.exports = {
  preset: 'ts-jest',
  testEnvironment: 'node',
  testMatch: ['**/__tests__/**/*.test.ts'],
};
