/**
 * SEO helpers shared by robots / sitemap / manifest / per-page metadata.
 *
 * `NEXT_PUBLIC_APP_URL` is the public site origin (canonical + OG/sitemap URLs);
 * `NEXT_PUBLIC_API_URL` is the API origin the server-side SEO fetches hit.
 */

const DEFAULT_SITE_URL = 'https://qbazzar.miete.site';
const DEFAULT_API_URL = 'http://localhost:8000';

export function siteUrl(): string {
  return (process.env.NEXT_PUBLIC_APP_URL ?? DEFAULT_SITE_URL).replace(/\/+$/, '');
}

export function absoluteUrl(path = ''): string {
  const suffix = path.startsWith('/') ? path : `/${path}`;
  return `${siteUrl()}${suffix}`;
}

function apiOrigin(): string {
  return (process.env.NEXT_PUBLIC_API_URL ?? DEFAULT_API_URL).replace(/\/+$/, '');
}

/**
 * Server-only fetch against the public API for SEO endpoints. Unwraps the
 * `{ success, data }` envelope and returns `null` on any failure so a missing
 * sitemap entry / OG tag degrades gracefully instead of 500-ing the route.
 */
export async function fetchApiData<T>(
  path: string,
  revalidateSeconds = 3600,
): Promise<T | null> {
  try {
    const res = await fetch(`${apiOrigin()}${path}`, {
      headers: { Accept: 'application/json' },
      next: { revalidate: revalidateSeconds },
    });

    if (!res.ok) return null;

    const json = (await res.json()) as { success?: boolean; data?: T };

    return json?.data ?? null;
  } catch {
    return null;
  }
}
