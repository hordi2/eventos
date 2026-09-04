import { describeSyncStatus } from '../describeSyncStatus';

it('affiche hors ligne quand il n\'y a pas de connexion, quel que soit le reste', () => {
  expect(describeSyncStatus(false, true, 5)).toEqual({ label: 'Hors ligne', tone: 'offline' });
  expect(describeSyncStatus(false, false, 0)).toEqual({ label: 'Hors ligne', tone: 'offline' });
});

it('affiche la synchronisation en cours quand elle est active', () => {
  expect(describeSyncStatus(true, true, 3)).toEqual({ label: 'Synchronisation…', tone: 'syncing' });
});

it('affiche le nombre en attente quand hors synchro avec une file non vide', () => {
  expect(describeSyncStatus(true, false, 12)).toEqual({ label: '12 en attente', tone: 'pending' });
});

it('affiche en ligne quand tout est synchronisé', () => {
  expect(describeSyncStatus(true, false, 0)).toEqual({ label: 'En ligne', tone: 'online' });
});
