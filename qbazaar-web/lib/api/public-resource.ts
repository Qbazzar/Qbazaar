import 'server-only';

import type { SuccessEnvelope } from './types';

const API_ORIGIN = (process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:4010').replace(/\/+$/, '');

/**
 * Server-side read of a public API record for a page that answers 404 itself.
 * Resolves to `null` only when the API says the record does not exist; any
 * other failure throws, so the error boundary offers a retry instead of a
 * false "not found". Identical calls in one render (metadata and page) are
 * deduplicated by Next.js, and responses are cached for `revalidateSeconds`.
 */
export async function fetchPublicResource<T>(path: string, revalidateSeconds = 3600): Promise<T | null> {
  const res = await fetch(`${API_ORIGIN}${path}`, {
    headers: { Accept: 'application/json' },
    next: { revalidate: revalidateSeconds },
  });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`GET ${path} failed with status ${res.status}`);

  const envelope = (await res.json()) as SuccessEnvelope<T>;
  return envelope.data ?? null;
}
