/**
 * Lazy Echo / Reverb client.
 *
 * Reverb implements the Pusher protocol, so we drive it with `laravel-echo`
 * pinned to `broadcaster: 'reverb'` and a custom authorizer that runs all
 * `/broadcasting/auth` requests through our shared axios instance — this is
 * what lets the cookie/bearer/locale interceptors run against the auth
 * endpoint without duplicating their logic.
 *
 * Build paths must not fail when env vars are missing or the socket is
 * down: every call funnels through `getEcho()` which returns `null` in
 * those situations so callers can no-op gracefully.
 *
 * Required env vars (frontend):
 *   NEXT_PUBLIC_REVERB_APP_KEY  — public app key issued by Reverb
 *   NEXT_PUBLIC_REVERB_HOST     — ws host (e.g. localhost)
 *   NEXT_PUBLIC_REVERB_PORT     — ws port (default 8080)
 *   NEXT_PUBLIC_REVERB_SCHEME   — 'http' or 'https' (drives forceTLS)
 */
import { api } from '@/lib/api/client';

// Echo is bundled with a non-trivial Pusher footprint; lazy-load to keep the
// initial chunk lean and to avoid any "window is not defined" surprises in
// server components.
type EchoInstance = {
  private: (channel: string) => {
    listen: (event: string, cb: (payload: unknown) => void) => unknown;
    stopListening: (event: string) => unknown;
  };
  leave: (channel: string) => void;
  leaveChannel: (channel: string) => void;
  disconnect: () => void;
};

let echoInstance: EchoInstance | null = null;
let pusherAttached = false;

function envConfigured(): boolean {
  return Boolean(
    process.env.NEXT_PUBLIC_REVERB_APP_KEY &&
      process.env.NEXT_PUBLIC_REVERB_HOST,
  );
}

/**
 * Custom authorizer — runs through axios so the bearer header, 401 refresh
 * dance and locale all carry over from REST calls.
 *
 * The callback signature follows Pusher's typings; we cast our axios
 * response into the channel-auth shape so consumers of Echo are happy.
 */
type ChannelAuthCallback = (
  error: Error | null,
  data: { auth: string; channel_data?: string; shared_secret?: string } | null,
) => void;

function createAuthorizer(channelName: string) {
  return {
    authorize: (socketId: string, callback: ChannelAuthCallback) => {
      api
        .post('/api/v1/broadcasting/auth', {
          socket_id: socketId,
          channel_name: channelName,
        })
        .then((response) => {
          callback(null, response.data as { auth: string });
        })
        .catch((err: unknown) => {
          const error =
            err instanceof Error ? err : new Error('Broadcasting auth failed');
          callback(error, null);
        });
    },
  };
}

/**
 * Returns the shared Echo instance, lazily instantiating it on first call.
 * Returns `null` on the server, when env vars are missing, or when the
 * dynamic import fails (keeps the rest of the app working in offline mode).
 */
export async function getEcho(): Promise<EchoInstance | null> {
  if (typeof window === 'undefined') return null;
  if (!envConfigured()) return null;
  if (echoInstance) return echoInstance;

  try {
    const [{ default: Echo }, pusherModule] = await Promise.all([
      import('laravel-echo'),
      import('pusher-js'),
    ]);

    // Echo's reverb broadcaster needs a global Pusher reference so it can
    // pick the transport up at runtime. We attach it once.
    if (!pusherAttached && typeof window !== 'undefined') {
      (window as unknown as { Pusher: unknown }).Pusher = pusherModule.default;
      pusherAttached = true;
    }

    const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';
    const port = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080);

    // The `authorizer` typings on `laravel-echo` declare a generator that
    // returns the legacy Pusher shape. Our custom implementation matches the
    // current Pusher types, so we cast the bag to `Parameters<typeof Echo>[0]`
    // to satisfy the constructor.
    const instance = new Echo({
      broadcaster: 'reverb',
      key: process.env.NEXT_PUBLIC_REVERB_APP_KEY as string,
      wsHost: process.env.NEXT_PUBLIC_REVERB_HOST as string,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      // Channel authorizer hooks back into our axios pipeline so private
      // channels inherit the same auth/refresh story as REST.
      authorizer: (channel: { name: string }) => createAuthorizer(channel.name),
    } as unknown as ConstructorParameters<typeof Echo>[0]);

    echoInstance = instance as unknown as EchoInstance;
    return echoInstance;
  } catch {
    // Bundle loaded but socket setup failed — fall back to polling.
    return null;
  }
}

/**
 * Drop the singleton + tear down the underlying socket. Called from the
 * logout flow so a fresh sign-in doesn't reuse the previous user's
 * authenticated channels.
 */
export function disconnectEcho(): void {
  if (!echoInstance) return;
  try {
    echoInstance.disconnect();
  } catch {
    // ignore — best effort
  }
  echoInstance = null;
}
