/**
 * Lazy FCM web-push client.
 *
 * Mirrors the Echo client's philosophy (`lib/echo/client.ts`): build paths
 * must not fail when env vars are missing — every entry point funnels through
 * `isPushConfigured()` and no-ops cleanly when the FCM project isn't wired
 * up, which is today's shipping state. The firebase SDK is dynamically
 * imported so it never lands in the main bundle (or in any bundle at all)
 * until a user actually enables push on a configured deployment.
 *
 * Required env vars (frontend):
 *   NEXT_PUBLIC_FCM_API_KEY    — Firebase web API key
 *   NEXT_PUBLIC_FCM_PROJECT_ID — Firebase project id
 *   NEXT_PUBLIC_FCM_SENDER_ID  — Cloud Messaging sender id
 *   NEXT_PUBLIC_FCM_APP_ID     — Firebase web app id
 *   NEXT_PUBLIC_FCM_VAPID_KEY  — Web-push certificate (VAPID) public key
 *
 * The service worker (`public/firebase-messaging-sw.js`) cannot read
 * NEXT_PUBLIC_ env, so the same config is forwarded to it via the
 * registration URL query string — env stays the single source of truth.
 */
import {
  registerDeviceToken,
  unregisterDeviceToken,
} from '@/lib/api/notifications';

/**
 * localStorage key holding the FCM token we registered with the backend.
 * Its presence doubles as the "push is enabled on this browser" flag, and it
 * is what logout reads to unregister the device best-effort.
 */
const PUSH_TOKEN_STORAGE_KEY = 'qb.push.token';

const SERVICE_WORKER_PATH = '/firebase-messaging-sw.js';

export type EnablePushResult = 'enabled' | 'denied' | 'unsupported' | 'error';

/**
 * Foreground push payload — mirrors the FCM channel's backend contract.
 * The backend sends data-only messages (title/body live in `data`), so the
 * firebase SDK never auto-displays a duplicate notification on web.
 */
export interface ForegroundPushPayload {
  /** Only present on Firebase-console test sends — backend never sets it. */
  notification?: { title?: string; body?: string };
  data?: {
    title?: string;
    body?: string;
    category?: string;
    cta_url?: string;
  } & Record<string, string>;
}

// NEXT_PUBLIC_ vars are inlined at build time, so each one must be referenced
// as a full `process.env.NEXT_PUBLIC_*` literal — no dynamic lookups.
function firebaseConfig() {
  return {
    apiKey: process.env.NEXT_PUBLIC_FCM_API_KEY,
    projectId: process.env.NEXT_PUBLIC_FCM_PROJECT_ID,
    messagingSenderId: process.env.NEXT_PUBLIC_FCM_SENDER_ID,
    appId: process.env.NEXT_PUBLIC_FCM_APP_ID,
  };
}

function envConfigured(): boolean {
  const cfg = firebaseConfig();
  return Boolean(
    cfg.apiKey &&
      cfg.projectId &&
      cfg.messagingSenderId &&
      cfg.appId &&
      process.env.NEXT_PUBLIC_FCM_VAPID_KEY,
  );
}

function browserSupported(): boolean {
  return (
    typeof window !== 'undefined' &&
    'Notification' in window &&
    'serviceWorker' in navigator
  );
}

/**
 * True only when all five FCM env vars are present AND the browser can do
 * web push. UI gates on this so the feature is invisible while unconfigured.
 */
export function isPushConfigured(): boolean {
  return envConfigured() && browserSupported();
}

/** Token we previously registered, or `null` when push was never enabled. */
export function getStoredPushToken(): string | null {
  if (typeof window === 'undefined') return null;
  try {
    return window.localStorage.getItem(PUSH_TOKEN_STORAGE_KEY);
  } catch {
    // localStorage can throw in hardened/private contexts — treat as unset.
    return null;
  }
}

function storePushToken(token: string | null): void {
  try {
    if (token === null) {
      window.localStorage.removeItem(PUSH_TOKEN_STORAGE_KEY);
    } else {
      window.localStorage.setItem(PUSH_TOKEN_STORAGE_KEY, token);
    }
  } catch {
    // Best effort — worst case logout skips the server-side unregister.
  }
}

/**
 * Lazily import the firebase SDK and return a Messaging instance bound to a
 * singleton app. Dynamic imports keep ~100KB of SDK out of every page for
 * the (current) deployments where push is disabled.
 */
async function getFirebaseMessaging() {
  const [{ initializeApp, getApps, getApp }, { getMessaging }] =
    await Promise.all([import('firebase/app'), import('firebase/messaging')]);
  const app = getApps().length > 0 ? getApp() : initializeApp(firebaseConfig());
  return getMessaging(app);
}

/**
 * Register the messaging service worker, forwarding the public config in the
 * query string so the SW (which can't read NEXT_PUBLIC_ env) initialises
 * from the same values.
 */
async function registerServiceWorker(): Promise<ServiceWorkerRegistration> {
  const config = encodeURIComponent(JSON.stringify(firebaseConfig()));
  const registration = await navigator.serviceWorker.register(
    `${SERVICE_WORKER_PATH}?config=${config}`,
  );
  return registration;
}

/**
 * Full enable flow: permission prompt → SW registration → FCM token →
 * backend device-token registration → local persistence.
 *
 * Returns a discriminated status instead of throwing so the UI can map each
 * outcome to copy without try/catch noise.
 */
export async function enablePush(): Promise<EnablePushResult> {
  if (!isPushConfigured()) return 'unsupported';

  try {
    const permission = await Notification.requestPermission();
    if (permission === 'denied') return 'denied';
    // 'default' means the prompt was dismissed — recoverable, so not 'denied'.
    if (permission !== 'granted') {
      console.warn('[push] permission prompt dismissed (not granted)');
      return 'error';
    }

    const registration = await registerServiceWorker();
    const messaging = await getFirebaseMessaging();
    const { getToken } = await import('firebase/messaging');

    const token = await getToken(messaging, {
      vapidKey: process.env.NEXT_PUBLIC_FCM_VAPID_KEY,
      serviceWorkerRegistration: registration,
    });
    if (!token) {
      console.error('[push] getToken returned empty — verify the VAPID key.');
      return 'error';
    }

    await registerDeviceToken({ token, platform: 'web' });
    storePushToken(token);
    return 'enabled';
  } catch (err) {
    // Surface the real cause (VAPID mismatch, SW failure, blocked request,
    // unsupported API) — the broad catch otherwise hides it from us.
    console.error('[push] enable failed:', err);
    return 'error';
  }
}

/**
 * Best-effort teardown used by the logout flow: unregister the token from
 * the backend (while the bearer is still valid), invalidate it with FCM and
 * clear local state. Never throws — a flaky push stack must not be able to
 * block a sign-out. Returns immediately when push was never enabled, so the
 * common path costs neither a network call nor a firebase chunk download.
 */
export async function disablePush(): Promise<void> {
  const token = getStoredPushToken();
  if (!token) return;

  // Clear local state first so a retry/refresh never resurrects the token.
  storePushToken(null);

  try {
    await unregisterDeviceToken(token);
  } catch {
    // Backend prunes stale tokens on FCM delivery failures, so a missed
    // unregister self-heals server-side.
  }

  try {
    if (envConfigured() && browserSupported()) {
      const messaging = await getFirebaseMessaging();
      const { deleteToken } = await import('firebase/messaging');
      await deleteToken(messaging);
    }
  } catch {
    // ignore — token may already be invalid or the SW may be gone.
  }
}

/**
 * Wire a callback to foreground FCM messages (page visible — the SW only
 * handles background delivery). Used by the enable-push UI to bump the
 * unread badge and invalidate the notifications queries, mirroring what the
 * Echo `notification.created` handler does in `useUserChannel`.
 *
 * Resolves to an unsubscribe function; a no-op unsubscriber when push is
 * unconfigured, unsupported or not yet permitted.
 */
export async function onForegroundMessage(
  cb: (payload: ForegroundPushPayload) => void,
): Promise<() => void> {
  if (!isPushConfigured()) return () => {};
  if (Notification.permission !== 'granted' || !getStoredPushToken()) {
    return () => {};
  }

  try {
    const messaging = await getFirebaseMessaging();
    const { onMessage } = await import('firebase/messaging');
    return onMessage(messaging, (payload) =>
      cb(payload as ForegroundPushPayload),
    );
  } catch {
    return () => {};
  }
}
