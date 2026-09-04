import { Database } from '@nozbe/watermelondb';
import SQLiteAdapter from '@nozbe/watermelondb/adapters/sqlite';
import { migrations } from './migrations';
import Guest from './models/Guest';
import PendingCheckIn from './models/PendingCheckIn';
import { schema } from './schema';

/**
 * WatermelonDB embarque du code natif (JSI) : nécessite un build de
 * développement (expo-dev-client) et ne fonctionne pas dans Expo Go — voir
 * mobile/README.md.
 */
const adapter = new SQLiteAdapter({
  schema,
  migrations,
  jsi: true,
});

export const database = new Database({
  adapter,
  modelClasses: [Guest, PendingCheckIn],
});
