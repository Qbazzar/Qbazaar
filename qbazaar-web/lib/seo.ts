import type { PaginatedEnvelope } from '@/lib/api/types';

/**
 * SEO helpers shared by robots / sitemap / manifest / per-page metadata.
 *
 * `NEXT_PUBLIC_APP_URL` is the public site origin (canonical + OG/sitemap URLs);
 * `NEXT_PUBLIC_API_URL` is the API origin the server-side SEO fetches hit.
 */

const DEFAULT_SITE_URL = 'https://qbazaar.qa';
const DEFAULT_API_URL = 'http://localhost:8000';

// `next build` prerenders the sitemap and fails a page that takes over 60 s, so
// a stalled API must cost only the API-backed entries, never the build.
const API_TIMEOUT_MS = 5000;

export function siteUrl(): string {
  return (process.env.NEXT_PUBLIC_APP_URL ?? DEFAULT_SITE_URL).replace(/\/+$/, '');
}

export function absoluteUrl(path = ''): string {
  const suffix = path.startsWith('/') ? path : `/${path}`;
  return `${siteUrl()}${suffix}`;
}

export function apiOrigin(): string {
  return (process.env.NEXT_PUBLIC_API_URL ?? DEFAULT_API_URL).replace(/\/+$/, '');
}

/** How long a server fetch may be reused: seconds, or `'no-store'` for a request that must not be cached. */
export type ApiCachePolicy = number | 'no-store';

interface ApiFetchOptions {
  /** Sent as `Accept-Language`, for lists the API translates by header rather than by `?lang=`. */
  language?: string;
}

/**
 * Server-only fetch against the public API. Returns `null` on any failure,
 * including no answer within API_TIMEOUT_MS, so a missing sitemap entry / OG
 * tag / first render degrades gracefully instead of 500-ing or stalling the
 * route.
 */
export async function fetchApiJson<T>(path: string, cachePolicy: ApiCachePolicy, options: ApiFetchOptions = {}): Promise<T | null> {
  try {
    const res = await fetch(`${apiOrigin()}${path}`, {
      headers: { Accept: 'application/json', ...(options.language ? { 'Accept-Language': options.language } : {}) },
      ...(cachePolicy === 'no-store' ? { cache: 'no-store' as const } : { next: { revalidate: cachePolicy } }),
      signal: AbortSignal.timeout(API_TIMEOUT_MS),
    });

    if (!res.ok) return null;

    return (await res.json()) as T;
  } catch {
    return null;
  }
}

/** `fetchApiJson` unwrapped from the `{ success, data }` envelope. */
export async function fetchApiData<T>(
  path: string,
  revalidateSeconds = 3600,
  options: ApiFetchOptions = {},
): Promise<T | null> {
  const json = await fetchApiJson<{ success?: boolean; data?: T }>(path, revalidateSeconds, options);

  return json?.data ?? null;
}

/** A paginated list: its rows with the `meta` the pager needs. */
export async function fetchApiPage<T>(
  path: string,
  revalidateSeconds = 3600,
): Promise<PaginatedEnvelope<T> | null> {
  const json = await fetchApiJson<PaginatedEnvelope<T>>(path, revalidateSeconds);

  return json && Array.isArray(json.data) && json.meta ? json : null;
}

/**
 * Build a Schema.org BreadcrumbList from ordered { name, path } crumbs.
 * Paths are resolved to absolute URLs.
 */
export function breadcrumbJsonLd(
  crumbs: Array<{ name: string; path: string }>,
): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: crumbs.map((crumb, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: crumb.name,
      item: absoluteUrl(crumb.path),
    })),
  };
}
