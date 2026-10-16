/**
 * Lit l'identifiant (billet ou invité RSVP) contenu dans le JWT scanné,
 * SANS vérifier sa signature — la vérification complète (signature +
 * expiration + statut + non-réutilisation, §4.6 du CLAUDE.md) reste la
 * responsabilité exclusive du serveur au moment de la synchronisation
 * (SyncCheckIns côté Laravel, qui reçoit désormais le jeton brut, voir
 * syncPendingCheckIns). Le poste hors ligne n'a par construction aucun
 * moyen de vérifier une signature sans embarquer le secret de signature
 * dans l'application — inacceptable pour un jeton côté client. Ici, on
 * fait juste confiance au local pour mettre à jour l'affichage optimiste ;
 * un jeton falsifié est de toute façon rejeté au moment réel du check-in
 * serveur, plus tard — jamais marqué définitivement accepté avant ça.
 */
export interface ScannedGuest {
  type: 'ticket' | 'attendee';
  id: number;
}

export function decodeScannedGuest(token: string): ScannedGuest | null {
  const parts = token.split('.');

  if (parts.length !== 3) {
    return null;
  }

  try {
    const payload = JSON.parse(base64UrlDecode(parts[1])) as { tid?: unknown; aid?: unknown };

    if (typeof payload.tid === 'number') {
      return { type: 'ticket', id: payload.tid };
    }

    if (typeof payload.aid === 'number') {
      return { type: 'attendee', id: payload.aid };
    }

    return null;
  } catch {
    return null;
  }
}

function base64UrlDecode(value: string): string {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(value.length + ((4 - (value.length % 4)) % 4), '=');

  return atob(base64);
}
