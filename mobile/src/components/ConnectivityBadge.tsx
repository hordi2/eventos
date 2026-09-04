import { useNetInfo } from '@react-native-community/netinfo';
import { StyleSheet, Text, View } from 'react-native';

/**
 * Indicateur d'état de connectivité (AC de T-061a) : en ligne / hors ligne
 * uniquement ici — "en cours de synchro" et "N en attente" arrivent avec la
 * file d'attente de check-ins (T-061c).
 */
export default function ConnectivityBadge() {
  const netInfo = useNetInfo();
  const online = netInfo.isConnected === true && netInfo.isInternetReachable !== false;

  return (
    <View style={[styles.badge, online ? styles.online : styles.offline]}>
      <View style={[styles.dot, online ? styles.dotOnline : styles.dotOffline]} />
      <Text style={styles.label}>{online ? 'En ligne' : 'Hors ligne'}</Text>
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
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 6,
  },
  dotOnline: {
    backgroundColor: '#1e7e34',
  },
  dotOffline: {
    backgroundColor: '#c5221f',
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: '#222',
  },
});
