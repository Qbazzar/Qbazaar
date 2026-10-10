/**
 * TanStack Query hooks for the public categories endpoints.
 *
 * Reference data changes rarely, so we use a long `staleTime` (1h) for the
 * tree/main/fields queries and a short one (5m) for live stats. Each
 * hook also syncs the result into the shared Zustand store so non-query
 * consumers (server-friendly helpers, breadcrumb lookup) can stay in sync.
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useEffect } from 'react';
import {
  getCategoryFields,
  getCategoryPage,
  getCategoryStats,
  getCategoryTree,
  getMainCategories,
} from '@/lib/api/categories';
import { useCategoriesStore } from '@/store/categories';
import type {
  Category,
  CategoryField,
  CategoryNode,
  CategoryPage,
  CategoryStats,
} from '@/lib/api/types';

const HOUR = 60 * 60 * 1000;
const FIVE_MIN = 5 * 60 * 1000;
// The API caches each category page for two minutes.
const TWO_MIN = 2 * 60 * 1000;

export const categoryKeys = {
  all: ['categories'] as const,
  tree: () => [...categoryKeys.all, 'tree'] as const,
  main: () => [...categoryKeys.all, 'main'] as const,
  page: (slug: string) => [...categoryKeys.all, 'page', slug] as const,
  stats: (slug: string) => [...categoryKeys.all, 'stats', slug] as const,
  fields: (slug: string) => [...categoryKeys.all, 'fields', slug] as const,
};

/**
 * Fetch the full category tree (categories + nested children) and mirror
 * it into the Zustand store so breadcrumbs and lookups can read it.
 * `enabled: false` waits until the tree is about to be needed.
 */
export function useCategoryTreeQuery({ enabled = true }: { enabled?: boolean } = {}): UseQueryResult<CategoryNode[]> {
  const setTree = useCategoriesStore((s) => s.setTree);
  const query = useQuery({
    queryKey: categoryKeys.tree(),
    queryFn: getCategoryTree,
    staleTime: HOUR,
    enabled,
  });

  useEffect(() => {
    if (query.data) setTree(query.data);
  }, [query.data, setTree]);

  return query;
}

export function useMainCategoriesQuery(): UseQueryResult<Category[]> {
  const setMain = useCategoriesStore((s) => s.setMainCategories);
  const query = useQuery({
    queryKey: categoryKeys.main(),
    queryFn: getMainCategories,
    staleTime: HOUR,
  });

  useEffect(() => {
    if (query.data) setMain(query.data);
  }, [query.data, setMain]);

  return query;
}

/** Category page sections (newest ads per child); disabled until a slug is known. */
export function useCategoryPageQuery(
  slug: string | null | undefined,
): UseQueryResult<CategoryPage> {
  return useQuery({
    queryKey: categoryKeys.page(slug ?? ''),
    queryFn: () => getCategoryPage(slug as string),
    enabled: Boolean(slug),
    staleTime: TWO_MIN,
  });
}

export function useCategoryStatsQuery(
  slug: string | null | undefined,
): UseQueryResult<CategoryStats> {
  return useQuery({
    queryKey: categoryKeys.stats(slug ?? ''),
    queryFn: () => getCategoryStats(slug as string),
    enabled: Boolean(slug),
    staleTime: FIVE_MIN,
  });
}

export function useCategoryFieldsQuery(
  slug: string | null | undefined,
): UseQueryResult<CategoryField[]> {
  return useQuery({
    queryKey: categoryKeys.fields(slug ?? ''),
    queryFn: () => getCategoryFields(slug as string),
    enabled: Boolean(slug),
    staleTime: HOUR,
  });
}
