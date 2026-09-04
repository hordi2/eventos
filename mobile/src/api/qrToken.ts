/**
 * Lit l'identifiant de billet contenu dans le JWT scanné, SANS vérifier sa
 * signature — la vérification complète (signature + expiration + statut +
 * non-réutilisation, §4.6 du CLAUDE.md) reste la responsabilité exclusive du
 * serveur au moment de la synchronisation (RecordCheckIn, T-060). Le poste
 * hors ligne n'a par construction aucun moyen de vérifier une signature
 * sans embarquer le secret de signature dans l'application — inacceptable
 * pour un jeton côté client. Ici, on fait juste confiance au local pour
 * mettre à jour l'affichage optimiste ; un jeton falsifié serait de toute
 * façon rejeté au moment réel du check-in serveur, plus tard.
 */
export function decodeTicketIdFromQrToken(token: string): number | null {
  const parts = token.split('.');

  if (parts.length !== 3) {
    return null;
  }

  try {
    const payload = JSON.parse(base64UrlDecode(parts[1])) as { tid?: unknown };

    return typeof payload.tid === 'number' ? payload.tid : null;
  } catch {
    return null;
  }
}

function base64UrlDecode(value: string): string {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(value.length + ((4 - (value.length % 4)) % 4), '=');

  return atob(base64);
}
