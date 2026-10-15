/**
 * Service worker de l'application participant (D9).
 *
 * Volontairement minimal : aucune mise en cache applicative (les pages sont
 * personnalisées par lien signé et à durée limitée, les mettre en cache
 * reviendrait à figer les données d'un invité pour les autres). Il ne fait
 * que rendre la page installable et afficher les notifications poussées.
 */

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Un gestionnaire 'fetch', même passif, reste exigé par certains navigateurs
// pour qu'une page se déclare installable.
self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

self.addEventListener('push', (event) => {
    // Le message poussé ne porte aucun contenu (§ ExchangePushNotification) :
    // seule sa réception compte, le texte vient toujours d'ici.
    const title = 'Nouvelle information pour votre événement';
    const options = { body: 'Ouvrez votre application pour la lire.' };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            for (const client of clients) {
                if ('focus' in client) {
                    return client.focus();
                }
            }

            return self.clients.openWindow('/');
        }),
    );
});
