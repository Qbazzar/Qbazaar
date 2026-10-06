import type { SuccessEnvelope } from './types';

const API_ORIGIN = (process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4010').replace(/\/+$/, '');
const API_TIMEOUT_MS = 10_000;

/**
 * Read of a public API record for a server component that answers 404 itself.
 * Resolves to `null` only when the API says the record does not exist; any
 * other failure, including no answer within API_TIMEOUT_MS, throws, so the
 * error boundary offers a retry instead of a false "not found". Responses
 * are cached for `revalidateSeconds`; the timeout signal opts the call out of
 * Next's per-request dedupe, so callers wrap it in React `cache()`.
 */
export async function fetchPublicResource<T>(path: string, revalidateSeconds = 3600): Promise<T | null> {
  const res = await fetch(`${API_ORIGIN}${path}`, {
    headers: { Accept: 'application/json' },
    next: { revalidate: revalidateSeconds },
    signal: AbortSignal.timeout(API_TIMEOUT_MS),
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`GET ${path} failed with status ${res.status}`);

  const envelope = (await res.json()) as SuccessEnvelope<T>;
  return envelope.data ?? null;
}
