/**
 * TanStack Query hooks for the CMS pages domain (Sprint 12).
 *
 * Caching strategy:
 * - List: staleTime 24h — pages change rarely and the footer is the only
 *   surface that hits this endpoint.
 * - Detail: staleTime 1h — long enough that navigation feels instant, short
 *   enough that fresh edits land on returning visitors within the hour.
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { getPage, listPages } from '@/lib/api/pages';
import type { ApiClientError } from '@/lib/api/auth';
import type { Page, PageListItem } from '@/lib/api/types';

const HOUR = 60 * 60 * 1000;

export const pagesKeys = {
  all: ['pages'] as const,
  list: () => [...pagesKeys.all, 'list'] as const,
  detail: (slug: string) => [...pagesKeys.all, 'detail', slug] as const,
};

export function usePagesQuery(): UseQueryResult<PageListItem[], ApiClientError> {
  return useQuery<PageListItem[], ApiClientError>({
    queryKey: pagesKeys.list(),
    queryFn: () => listPages(),
    staleTime: 24 * HOUR,
  });
}

export function usePageQuery(
  slug: string,
): UseQueryResult<Page, ApiClientError> {
  return useQuery<Page, ApiClientError>({
    queryKey: pagesKeys.detail(slug),
    queryFn: () => getPage(slug),
    staleTime: HOUR,
    enabled: Boolean(slug),
  });
}
