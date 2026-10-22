import 'server-only';

import { QueryClient, dehydrate, type DehydratedState, type QueryKey } from '@tanstack/react-query';

import type { ResolvedCategory } from '@/components/catalog/resolved-category';
import { SEARCH_PER_PAGE } from '@/components/catalog/page-size';
import { SORT_MODES, hasListingParams, parseListingQuery } from '@/components/catalog/listing-query';
import type { AdSummary, CategoryPage, Location, PaginatedResponse, SearchQueryParams, SearchResponse, SortMode } from '@/lib/api/types';
import type { Locale } from '@/lib/i18n/locale';
import { adKeys } from '@/lib/queries/ads-keys';
import { categoryKeys } from '@/lib/queries/categories-keys';
import { searchKeys } from '@/lib/queries/search-keys';
import { fetchApiData, fetchApiJson } from '@/lib/seo';

/**
 * The first page of results, fetched on the server and handed to the client
 * queries as dehydrated state, so the first HTML already holds the cards
 * instead of a skeleton that waits for the browser to ask the API.
 *
 * Only the plain URLs are seeded (a page, a sort, a keyword or a category):
 * the query key built here must equal the one the client builds, and any
 * other filter is left to the client as before. The dehydrated entry carries
 * the time of this fetch, so the client's `staleTime` keeps it from asking
 * the API again right away.
 */

export type SeedParams = Record<string, string | string[] | undefined>;

/**
 * The seeds block the page's first byte, so each gets a short budget of its
 * own instead of the 5 s of the SEO fetches; past it the client loads the
 * results as before.
 */
const SEED_TIMEOUT_MS = 1500;

/** The API caches a category page for two minutes. */
const CATEGORY_PAGE_REVALIDATE_SECONDS = 120;
/** A plain category listing is the same for every visitor; a minute keeps a new ad from waiting long. */
const LISTING_REVALIDATE_SECONDS = 60;

/** What the page's URL state reads and what the seed covers; other filters of the same URL are left to the client. */
const SEARCH_SEEDED_KEYS = ['q', 'category_slug', 'location_slug', 'page', 'sort', 'view'];
const SEARCH_FILTER_KEYS = ['price_min', 'price_max', 'lat', 'lng', 'radius_km', 'condition', 'ad_type', 'shipping', 'seller_type', 'buy_now', 'cf'];
const LISTING_SEEDED_KEYS = ['page', 'sort', 'view'];
const LISTING_FILTER_KEYS = ['q', 'category', 'location', 'price_min', 'price_max', 'condition', 'ad_type', 'shipping', 'seller_type', 'buy_now', 'cf'];
const KEYWORD_MAX_LENGTH = 200;
const PAGE_NUMBER = /^[1-9]\d{0,2}$/;

interface ListingSeedParams extends Record<string, unknown> {
  category_id?: string;
  sort?: SortMode;
  page: number;
}

/** The dehydrated state of the given entries; a failed fetch (null) leaves its entry out. */
function dehydrated(entries: Array<{ key: QueryKey; data: unknown }>): DehydratedState | undefined {
  const present = entries.filter((entry) => entry.data != null);
  if (present.length === 0) return undefined;

  // No timers: this client lives for one render.
  const client = new QueryClient({ defaultOptions: { queries: { gcTime: Infinity } } });
  for (const { key, data } of present) client.setQueryData(key, data);
  return dehydrate(client);
}

/**
 * The seeded keys of the URL as single strings, or null when a filter this seed
 * does not cover is set (or a seeded key is repeated). Unknown keys such as
 * `utm_source` change nothing on the page and are ignored.
 */
function seededValues(bag: SeedParams, seeded: readonly string[], filters: readonly string[]): Map<string, string> | null {
  if (filters.some((key) => bag[key] !== undefined)) return null;

  const values = new Map<string, string>();
  for (const key of seeded) {
    const value = bag[key];
    if (Array.isArray(value)) return null;
    if (value !== undefined) values.set(key, value);
  }
  return values;
}

/** The request the search page makes for this URL, or null when the URL has a filter that is not seeded. */
export function searchSeedParams(bag: SeedParams): SearchQueryParams | null {
  const values = seededValues(bag, SEARCH_SEEDED_KEYS, SEARCH_FILTER_KEYS);
  if (!values) return null;

  const sort = SORT_MODES.find((mode) => mode === (values.get('sort') ?? 'latest'));
  const page = values.get('page') ?? '1';
  const keyword = values.get('q') ?? '';
  if (!sort || !PAGE_NUMBER.test(page) || keyword.length > KEYWORD_MAX_LENGTH) return null;

  const params: SearchQueryParams = { sort };
  if (keyword) params.q = keyword;
  for (const key of ['category_slug', 'location_slug'] as const) {
    const value = values.get(key);
    if (value) params[key] = value;
  }
  return { ...params, page: Number(page), per_page: SEARCH_PER_PAGE };
}

function queryString(params: Record<string, unknown>): string {
  const query = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined) query.set(key, String(value));
  }
  return query.toString();
}

/**
 * Results of `/search` for the URL. Search words are unbounded, so the request
 * stays out of the data cache: it replaces the one the browser would make.
 */
export async function searchSeed(bag: SeedParams, locale: Locale): Promise<DehydratedState | undefined> {
  const params = searchSeedParams(bag);
  if (!params) return undefined;

  const data = await fetchApiJson<SearchResponse>(`/api/v1/search?${queryString({ ...params, lang: locale })}`, 'no-store', { language: locale, timeoutMs: SEED_TIMEOUT_MS });
  return dehydrated([{ key: searchKeys.result(params), data }]);
}

/** The `GET /ads` request of a listing page for this URL, or null when the URL has a filter that is not seeded. */
export function listingSeedParams(bag: SeedParams, categoryId?: string): ListingSeedParams | null {
  const values = seededValues(bag, LISTING_SEEDED_KEYS, LISTING_FILTER_KEYS);
  if (!values) return null;

  // The client reads the page and the sort through the same parser.
  const query = parseListingQuery(new URLSearchParams([...values]));
  return { category_id: categoryId, ...(query.sort === 'latest' ? {} : { sort: query.sort }), page: query.page };
}

/** The listing page of a category (or of every ad, without one) for a plain, paged or sorted URL. */
export async function listingSeed(bag: SeedParams, categoryId: string | undefined, locale: Locale): Promise<DehydratedState | undefined> {
  const params = listingSeedParams(bag, categoryId);
  if (!params) return undefined;

  const data = await fetchApiJson<PaginatedResponse<AdSummary>>(`/api/v1/ads?${queryString({ ...params, lang: locale })}`, LISTING_REVALIDATE_SECONDS, { language: locale, timeoutMs: SEED_TIMEOUT_MS });
  return dehydrated([{ key: adKeys.list(params), data }]);
}

/** The sections of a parent category's overview: the guest copy, a signed-in viewer's own key loads by itself. */
export async function categoryHubSeed(slug: string, locale: Locale): Promise<DehydratedState | undefined> {
  const data = await fetchApiData<CategoryPage>(`/api/v1/categories/${encodeURIComponent(slug)}?lang=${locale}`, CATEGORY_PAGE_REVALIDATE_SECONDS, { timeoutMs: SEED_TIMEOUT_MS });
  return dehydrated([{ key: categoryKeys.page(slug, 'guest'), data }]);
}

/** What `/c/[slug]` shows first: the overview of a parent category, the listing of a leaf or of a paged / sorted URL. */
export function categorySeed(bag: SeedParams, category: ResolvedCategory, locale: Locale): Promise<DehydratedState | undefined> {
  const entries = Object.entries(bag).filter((entry): entry is [string, string] => typeof entry[1] === 'string');
  // A repeated key is read as its first value by the page; leave such a URL to the client.
  if (entries.length !== Object.values(bag).filter((value) => value !== undefined).length) return Promise.resolve(undefined);

  const overview = category.hasChildren && !hasListingParams(new URLSearchParams(entries));
  return overview ? categoryHubSeed(category.slug, locale) : listingSeed(bag, category.id, locale);
}

/** The Qatar locations tree the cards name their places from (cached for an hour, shared with the home page). */
export function fetchPlaces(): Promise<Location[] | null> {
  return fetchApiData<Location[]>('/api/v1/locations/qatar');
}
