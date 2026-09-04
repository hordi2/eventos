import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { ApiError } from '../api/ApiError';
import { useAuth } from '../auth/AuthContext';
import SyncStatusBadge from '../components/SyncStatusBadge';
import GuestModel from '../db/models/Guest';
import { recordLocalCheckIn } from '../db/recordLocalCheckIn';
import { searchLocalGuests } from '../db/searchGuests';
import { downloadEventGuests } from '../db/sync/downloadGuests';
import { loadEventId, saveEventId } from '../storage/localSettings';
import { useSyncEngine } from '../sync/useSyncEngine';
import ScanScreen from './ScanScreen';

export default function GuestListScreen() {
  const { token, logout } = useAuth();
  const [mode, setMode] = useState<'list' | 'scan'>('list');
  const [eventId, setEventId] = useState<number | null>(null);
  const [eventIdInput, setEventIdInput] = useState('');
  const [guests, setGuests] = useState<GuestModel[]>([]);
  const [search, setSearch] = useState('');
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lastDownloadCount, setLastDownloadCount] = useState<number | null>(null);
  const sync = useSyncEngine(eventId);

  useEffect(() => {
    loadEventId().then((stored) => {
      if (stored !== null) {
        setEventId(stored);
        setEventIdInput(String(stored));
      }
    });
  }, []);

  const refreshLocalGuests = useCallback((currentEventId: number, term: string) => {
    searchLocalGuests(currentEventId, term).then(setGuests);
  }, []);

  useEffect(() => {
    if (eventId !== null) {
      refreshLocalGuests(eventId, search);
    }
  }, [eventId, search, refreshLocalGuests]);

  async function handleDownload() {
    const parsedEventId = Number(eventIdInput.trim());

    if (!Number.isInteger(parsedEventId) || parsedEventId <= 0 || token === null) {
      setError("Identifiant d'événement invalide.");

      return;
    }

    setError(null);
    setDownloading(true);

    try {
      const count = await downloadEventGuests(parsedEventId, token);
      await saveEventId(parsedEventId);
      setEventId(parsedEventId);
      setLastDownloadCount(count);
      refreshLocalGuests(parsedEventId, search);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : 'Téléchargement impossible.');
    } finally {
      setDownloading(false);
    }
  }

  async function handleCheckIn(guest: GuestModel) {
    if (eventId === null) {
      return;
    }

    await recordLocalCheckIn(eventId, guest.guestType, guest.remoteId);
    refreshLocalGuests(eventId, search);
    sync.refreshPendingCount();
    sync.triggerSync();
  }

  if (mode === 'scan' && eventId !== null) {
    return (
      <ScanScreen
        eventId={eventId}
        onSwitchToList={() => setMode('list')}
        onCheckInRecorded={() => {
          sync.refreshPendingCount();
          sync.triggerSync();
        }}
      />
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <SyncStatusBadge online={sync.online} syncing={sync.syncing} pendingCount={sync.pendingCount} />
        <TouchableOpacity onPress={() => void logout()}>
          <Text style={styles.logout}>Se déconnecter</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.downloadRow}>
        <TextInput
          style={styles.eventInput}
          placeholder="ID événement"
          keyboardType="number-pad"
          value={eventIdInput}
          onChangeText={setEventIdInput}
        />
        <TouchableOpacity style={styles.downloadButton} onPress={() => void handleDownload()} disabled={downloading}>
          {downloading ? <ActivityIndicator color="#fff" /> : <Text style={styles.downloadLabel}>Télécharger la liste</Text>}
        </TouchableOpacity>
      </View>

      {error !== null && <Text style={styles.error}>{error}</Text>}
      {lastDownloadCount !== null && <Text style={styles.info}>{lastDownloadCount} invités téléchargés.</Text>}

      {eventId !== null && (
        <View style={styles.searchRow}>
          <TextInput
            style={styles.searchInput}
            placeholder="Rechercher (nom, e-mail, téléphone)"
            value={search}
            onChangeText={setSearch}
          />
          <TouchableOpacity style={styles.scanButton} onPress={() => setMode('scan')}>
            <Text style={styles.scanLabel}>Scanner</Text>
          </TouchableOpacity>
        </View>
      )}

      <FlatList
        data={guests}
        keyExtractor={(guest) => `${guest.guestType}-${guest.remoteId}`}
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View>
              <Text style={styles.name}>{item.name}</Text>
              <Text style={styles.contact}>{item.email ?? item.phone ?? '—'}</Text>
            </View>
            {item.checkedIn ? (
              <Text style={styles.statusIn}>Enregistré</Text>
            ) : (
              <TouchableOpacity style={styles.checkInButton} onPress={() => void handleCheckIn(item)}>
                <Text style={styles.checkInLabel}>Check-in</Text>
              </TouchableOpacity>
            )}
          </View>
        )}
        ListEmptyComponent={
          <Text style={styles.empty}>
            {eventId === null ? "Saisissez un identifiant d'événement puis téléchargez la liste." : 'Aucun invité.'}
          </Text>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    paddingTop: 56,
    paddingHorizontal: 16,
    backgroundColor: '#fff',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  logout: {
    color: '#c5221f',
    fontSize: 13,
  },
  downloadRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 8,
  },
  eventInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    padding: 10,
  },
  downloadButton: {
    backgroundColor: '#111',
    borderRadius: 8,
    paddingHorizontal: 16,
    justifyContent: 'center',
  },
  downloadLabel: {
    color: '#fff',
    fontWeight: '600',
  },
  error: {
    color: '#c5221f',
    marginBottom: 8,
  },
  info: {
    color: '#1e7e34',
    marginBottom: 8,
  },
  searchRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  searchInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    padding: 10,
  },
  scanButton: {
    backgroundColor: '#111',
    borderRadius: 8,
    paddingHorizontal: 16,
    justifyContent: 'center',
  },
  scanLabel: {
    color: '#fff',
    fontWeight: '600',
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
  },
  contact: {
    fontSize: 13,
    color: '#666',
  },
  statusIn: {
    color: '#1e7e34',
    fontSize: 13,
    fontWeight: '600',
  },
  checkInButton: {
    backgroundColor: '#111',
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  checkInLabel: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '600',
  },
  empty: {
    textAlign: 'center',
    color: '#888',
    marginTop: 32,
  },
});
