import { decodeScannedGuest } from '../qrToken';

function makeToken(payload: Record<string, unknown>): string {
  const header = base64UrlEncode(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = base64UrlEncode(JSON.stringify(payload));

  // Signature non vérifiée par decodeScannedGuest (voir son docblock) :
  // n'importe quelle troisième partie suffit pour ce test.
  return `${header}.${body}.signature`;
}

function base64UrlEncode(value: string): string {
  return Buffer.from(value, 'utf-8').toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

it('lit l\'identifiant de billet (tid) depuis un jeton bien formé', () => {
  const token = makeToken({ tid: 42, jti: 'abc', iat: 1, exp: 2 });

  expect(decodeScannedGuest(token)).toEqual({ type: 'ticket', id: 42 });
});

it("lit l'identifiant d'invité RSVP (aid) depuis un jeton bien formé", () => {
  const token = makeToken({ aid: 7, iat: 1, exp: 2 });

  expect(decodeScannedGuest(token)).toEqual({ type: 'attendee', id: 7 });
});

it('préfère tid quand un jeton porte les deux (ne devrait jamais arriver côté serveur)', () => {
  const token = makeToken({ tid: 42, aid: 7 });

  expect(decodeScannedGuest(token)).toEqual({ type: 'ticket', id: 42 });
});

it('renvoie null pour un jeton qui n\'a que deux segments', () => {
  expect(decodeScannedGuest('abc.def')).toBeNull();
});

it('renvoie null pour un jeton dont le contenu décodé n\'est pas du JSON valide', () => {
  expect(decodeScannedGuest('abc.def.ghi')).toBeNull();
});

it('renvoie null quand la charge utile ne porte ni tid ni aid numérique', () => {
  const token = makeToken({ jti: 'abc' });

  expect(decodeScannedGuest(token)).toBeNull();
});

it("renvoie null quand tid n'est pas un nombre", () => {
  const token = makeToken({ tid: 'quarante-deux' });

  expect(decodeScannedGuest(token)).toBeNull();
});
