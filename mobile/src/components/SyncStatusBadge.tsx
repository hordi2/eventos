import { StyleSheet, Text, View } from 'react-native';
import { describeSyncStatus } from '../sync/describeSyncStatus';

interface Props {
  online: boolean;
  syncing: boolean;
  pendingCount: number;
}

/**
 * Indicateur d'état unique (AC de T-061c) : en ligne / hors ligne / en
 * cours de synchro / N en attente — la logique de priorité vit dans
 * describeSyncStatus (testable sans react-native), ce composant ne fait
 * que l'afficher.
 */
export default function SyncStatusBadge({ online, syncing, pendingCount }: Props) {
  const { label, tone } = describeSyncStatus(online, syncing, pendingCount);

  return (
    <View style={[styles.badge, styles[tone]]}>
      <View style={[styles.dot, styles[`dot_${tone}`]]} />
      <Text style={styles.label}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  online: {
    backgroundColor: '#e6f4ea',
  },
  offline: {
    backgroundColor: '#fce8e6',
  },
  syncing: {
    backgroundColor: '#e8f0fe',
  },
  pending: {
    backgroundColor: '#fef7e0',
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  dot_online: {
    backgroundColor: '#1e7e34',
  },
  dot_offline: {
    backgroundColor: '#c5221f',
  },
  dot_syncing: {
    backgroundColor: '#1a73e8',
  },
  dot_pending: {
    backgroundColor: '#b06000',
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: '#222',
  },
});
