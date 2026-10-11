/**
 * TanStack Query hooks for the favourite categories.
 *
 * Whether the open category is favourited rides on its page (`is_favorited`,
 * the cached `GET /categories/{slug}`), so the "Add to Favorite" pill needs no
 * request of its own. The list is for the account area.
 */
import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { listFavoriteCategories, setFavoriteCategory } from '@/lib/api/favorites';
import type { ApiClientError } from '@/lib/api/auth';
import type { CategoryPage, FavoriteCategory, FavoriteCategoryState } from '@/lib/api/types';

import { categoryKeys } from './categories';

const MINUTE = 60 * 1000;

export const favoriteCategoryKeys = {
  all: ['favorite-categories'] as const,
  list: () => [...favoriteCategoryKeys.all, 'list'] as const,
};

/** Signed-in only: a guest's request would 401. */
export function useFavoriteCategoriesQuery(enabled: boolean): UseQueryResult<FavoriteCategory[], ApiClientError> {
  return useQuery({
    queryKey: favoriteCategoryKeys.list(),
    queryFn: listFavoriteCategories,
    enabled,
    staleTime: MINUTE,
  });
}

interface SetVariables {
  slug: string;
  favorited: boolean;
}

interface SetContext {
  previous?: CategoryPage;
}

/** Flips `is_favorited` on the cached category page at once and puts it back if the request fails. */
export function useSetFavoriteCategoryMutation(): UseMutationResult<FavoriteCategoryState, ApiClientError, SetVariables, SetContext> {
  const qc = useQueryClient();
  const writeFlag = (slug: string, favorited: boolean) =>
    qc.setQueryData<CategoryPage>(categoryKeys.page(slug), (page) => (page ? { ...page, is_favorited: favorited } : page));

  return useMutation({
    mutationFn: ({ slug, favorited }) => setFavoriteCategory(slug, favorited),
    onMutate: async ({ slug, favorited }) => {
      await qc.cancelQueries({ queryKey: categoryKeys.page(slug) });
      const previous = qc.getQueryData<CategoryPage>(categoryKeys.page(slug));
      writeFlag(slug, favorited);
      return { previous };
    },
    onError: (_error, { slug }, context) => {
      if (context?.previous) qc.setQueryData(categoryKeys.page(slug), context.previous);
    },
    onSuccess: (state, { slug }) => {
      writeFlag(slug, state.favorited);
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: favoriteCategoryKeys.list() });
    },
  });
}
