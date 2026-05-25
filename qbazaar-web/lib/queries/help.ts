/**
 * TanStack Query hooks for the help center domain (Sprint 12).
 *
 * Caching strategy:
 * - Categories list: 1h — the catalogue rarely changes.
 * - Category / article detail: 1h — instant back-navigation on the same hop.
 * - Search: 5m, and only fires when `q.trim().length >= 2` so the user
 *   doesn't trigger a query on every keystroke. Debouncing happens upstream
 *   in `HelpSearchBar`; we also guard here to avoid surprise refetches.
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import {
  getHelpArticle,
  getHelpCategory,
  listHelpCategories,
  searchHelp,
  type HelpCategoryWithArticles,
} from '@/lib/api/help';
import type { ApiClientError } from '@/lib/api/auth';
import type {
  HelpArticle,
  HelpArticleListItem,
  HelpCategory,
} from '@/lib/api/types';

const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;

export const helpKeys = {
  all: ['help'] as const,
  categories: () => [...helpKeys.all, 'categories'] as const,
  category: (slug: string) => [...helpKeys.all, 'category', slug] as const,
  article: (slug: string) => [...helpKeys.all, 'article', slug] as const,
  search: (q: string) => [...helpKeys.all, 'search', q] as const,
};

export function useHelpCategoriesQuery(): UseQueryResult<
  HelpCategory[],
  ApiClientError
> {
  return useQuery<HelpCategory[], ApiClientError>({
    queryKey: helpKeys.categories(),
    queryFn: () => listHelpCategories(),
    staleTime: HOUR,
  });
}

export function useHelpCategoryQuery(
  slug: string,
): UseQueryResult<HelpCategoryWithArticles, ApiClientError> {
  return useQuery<HelpCategoryWithArticles, ApiClientError>({
    queryKey: helpKeys.category(slug),
    queryFn: () => getHelpCategory(slug),
    staleTime: HOUR,
    enabled: Boolean(slug),
  });
}

export function useHelpArticleQuery(
  slug: string,
): UseQueryResult<HelpArticle, ApiClientError> {
  return useQuery<HelpArticle, ApiClientError>({
    queryKey: helpKeys.article(slug),
    queryFn: () => getHelpArticle(slug),
    staleTime: HOUR,
    enabled: Boolean(slug),
  });
}

export function useHelpSearchQuery(
  q: string,
): UseQueryResult<HelpArticleListItem[], ApiClientError> {
  const trimmed = q.trim();
  return useQuery<HelpArticleListItem[], ApiClientError>({
    queryKey: helpKeys.search(trimmed),
    queryFn: () => searchHelp(trimmed),
    staleTime: 5 * MINUTE,
    enabled: trimmed.length >= 2,
    placeholderData: (prev) => prev,
  });
}
