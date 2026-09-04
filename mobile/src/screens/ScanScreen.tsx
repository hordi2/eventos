import { CameraView, useCameraPermissions } from 'expo-camera';
import { useRef, useState } from 'react';
import { ActivityIndicator, Button, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { decodeTicketIdFromQrToken } from '../api/qrToken';
import { recordLocalCheckIn, type LocalCheckInResult } from '../db/recordLocalCheckIn';

interface Props {
  eventId: number;
  onSwitchToList: () => void;
}

type Feedback = { type: 'accepted' | 'conflict' | 'error'; message: string };

// Après un scan traité, on ignore les nouvelles détections pendant ce délai :
// la caméra continue de voir le même QR pendant que l'utilisateur l'éloigne,
// sans quoi le même billet serait renvoyé en boucle (AC : scan < 1 s, mais
// pas de spam d'écritures locales identiques).
const COOLDOWN_MS = 1500;

/**
 * Écran lisible en pénombre, utilisable à une main (AC de T-061b) : fond
 * sombre, retour visuel en grand texte à haut contraste, un seul bouton
 * pour basculer en mode liste — pas de geste complexe.
 */
export default function ScanScreen({ eventId, onSwitchToList }: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [feedback, setFeedback] = useState<Feedback | null>(null);
  const processingRef = useRef(false);

  async function handleScan(data: string) {
    if (processingRef.current) {
      return;
    }

    processingRef.current = true;

    const ticketId = decodeTicketIdFromQrToken(data);

    if (ticketId === null) {
      setFeedback({ type: 'error', message: 'QR illisible.' });
      releaseAfterCooldown();

      return;
    }

    const result = await recordLocalCheckIn(eventId, 'ticket', ticketId);
    setFeedback(describeResult(result));
    releaseAfterCooldown();
  }

  function releaseAfterCooldown() {
    setTimeout(() => {
      processingRef.current = false;
    }, COOLDOWN_MS);
  }

  if (!permission) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color="#fff" />
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={styles.center}>
        <Text style={styles.permissionText}>L'accès à la caméra est nécessaire pour scanner les billets.</Text>
        <Button title="Autoriser la caméra" onPress={() => void requestPermission()} />
        <TouchableOpacity onPress={onSwitchToList} style={styles.listButtonAlt}>
          <Text style={styles.listButtonLabel}>Utiliser la recherche à la place</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={(event) => void handleScan(event.data)}
      />

      {feedback !== null && (
        <View style={[styles.feedback, feedbackStyle(feedback.type)]}>
          <Text style={styles.feedbackText}>{feedback.message}</Text>
        </View>
      )}

      <TouchableOpacity style={styles.listButton} onPress={onSwitchToList}>
        <Text style={styles.listButtonLabel}>Mode liste</Text>
      </TouchableOpacity>
    </View>
  );
}

function describeResult(result: LocalCheckInResult): Feedback {
  switch (result.status) {
    case 'accepted':
      return { type: 'accepted', message: `${result.guest.name}\nEntrée acceptée` };
    case 'conflict':
      return { type: 'conflict', message: `${result.guest.name}\nDéjà enregistré` };
    case 'not_found':
      return { type: 'error', message: "Ce billet n'appartient pas à cet événement." };
  }
}

function feedbackStyle(type: Feedback['type']) {
  if (type === 'accepted') {
    return styles.feedbackAccepted;
  }

  return type === 'conflict' ? styles.feedbackConflict : styles.feedbackError;
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000',
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
    gap: 16,
    backgroundColor: '#000',
  },
  permissionText: {
    color: '#fff',
    fontSize: 16,
    textAlign: 'center',
  },
  feedback: {
    position: 'absolute',
    top: 64,
    left: 16,
    right: 16,
    borderRadius: 12,
    padding: 20,
  },
  feedbackAccepted: {
    backgroundColor: 'rgba(30,126,52,0.92)',
  },
  feedbackConflict: {
    backgroundColor: 'rgba(197,34,31,0.92)',
  },
  feedbackError: {
    backgroundColor: 'rgba(60,60,60,0.92)',
  },
  feedbackText: {
    color: '#fff',
    fontSize: 22,
    fontWeight: '700',
    textAlign: 'center',
  },
  listButton: {
    position: 'absolute',
    bottom: 48,
    alignSelf: 'center',
    backgroundColor: 'rgba(255,255,255,0.92)',
    paddingHorizontal: 24,
    paddingVertical: 14,
    borderRadius: 999,
  },
  listButtonAlt: {
    marginTop: 8,
  },
  listButtonLabel: {
    color: '#111',
    fontWeight: '600',
    fontSize: 16,
  },
});
