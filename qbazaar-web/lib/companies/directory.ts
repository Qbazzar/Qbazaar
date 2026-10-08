/**
 * URL state of the companies directory (`/companies?q=&page=`) and the API
 * request it maps to (`GET /api/v1/companies`).
 */

/** The contract caps `q` at 100 characters. */
export const COMPANY_QUERY_MAX_LENGTH = 100;

export interface CompaniesParams {
  query: string;
  page: number;
}

type SearchParamValue = string | string[] | undefined;

function first(value: SearchParamValue): string {
  return (Array.isArray(value) ? value[0] : value) ?? '';
}

/** Reads `q` and `page` from the page's search params; anything malformed falls back to page 1 and no query. */
export function readCompaniesParams(searchParams: Record<string, SearchParamValue>): CompaniesParams {
  const query = first(searchParams.q).trim().slice(0, COMPANY_QUERY_MAX_LENGTH);
  const page = Number(first(searchParams.page));
  return { query, page: Number.isInteger(page) && page > 1 ? page : 1 };
}

function searchString({ query, page }: CompaniesParams): string {
  const params = new URLSearchParams();
  if (query) params.set('q', query);
  if (page > 1) params.set('page', String(page));
  const search = params.toString();
  return search ? `?${search}` : '';
}

/** Link to a page of the directory; page 1 has no `page` param, so it has one URL. */
export function companiesHref(params: CompaniesParams): string {
  return `/companies${searchString(params)}`;
}

export function companiesApiPath(params: CompaniesParams): string {
  return `/api/v1/companies${searchString(params)}`;
}
