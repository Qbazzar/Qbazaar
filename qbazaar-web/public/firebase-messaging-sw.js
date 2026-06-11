/**
 * Firebase Cloud Messaging service worker — background web push.
 *
 * Service workers can't consume ES modules from npm, so this uses the compat
 * importScripts builds, pinned to the same major.minor.patch as the
 * `firebase` package in package.json (keep them in lockstep when upgrading).
 *
 * Service workers also can't read NEXT_PUBLIC_ env, so `lib/push/fcm.ts`
 * forwards the public Firebase config in the registration URL query string
 * (`/firebase-messaging-sw.js?config=<encoded JSON>`). Env stays the single
 * source of truth; nothing is hardcoded here. When the config is absent or
 * malformed the worker installs as an inert no-op.
 */
/* eslint-disable no-undef */
importScripts(
  'https://www.gstatic.com/firebasejs/12.14.0/firebase-app-compat.js',
);
importScripts(
  'https://www.gstatic.com/firebasejs/12.14.0/firebase-messaging-compat.js',
);

function readConfigFromQueryString() {
  try {
    const params = new URLSearchParams(self.location.search);
    const raw = params.get('config');
    if (!raw) return null;
    const config = JSON.parse(raw);
    return config && config.apiKey && config.projectId && config.appId
      ? config
      : null;
  } catch (_err) {
    return null;
  }
}

const firebaseConfig = readConfigFromQueryString();

if (firebaseConfig) {
  firebase.initializeApp(firebaseConfig);
  const messaging = firebase.messaging();

  // Payload contract (qbazaar-api FCM channel): notification {title, body}
  // plus data {category, cta_url}.
  messaging.onBackgroundMessage(function (payload) {
    const notification = payload.notification || {};
    const data = payload.data || {};
    self.registration.showNotification(notification.title || 'QBazaar', {
      body: notification.body || '',
      icon: '/brand/logo.png',
      data: { cta_url: data.cta_url || '/' },
    });
  });
}

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const ctaUrl =
    (event.notification.data && event.notification.data.cta_url) || '/';

  event.waitUntil(
    clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then(function (windowClients) {
        // Prefer focusing an existing QBazaar tab over spawning another one.
        for (const client of windowClients) {
          if ('focus' in client) {
            if ('navigate' in client) {
              // navigate() rejects for uncontrolled clients or cross-origin
              // cta_urls — swallow it, focusing the tab is still the win.
              client.navigate(ctaUrl).catch(function () {});
            }
            return client.focus();
          }
        }
        return clients.openWindow(ctaUrl);
      }),
  );
});
