'use client';

import type { SearchQueryParams } from '@/lib/api/types';

import { SaveSearchButton } from '@/components/search/SaveSearchButton';

import { FavoriteCategoryButton } from './FavoriteCategoryButton';

interface CategoryActionsProps {
  slug: string;
  saveParams: SearchQueryParams;
  variant?: 'header' | 'toolbar';
}

/** "Add to Favorite" and "Save Search", side by side as the design lays them out (category.html). */
export function CategoryActions({ slug, saveParams, variant = 'header' }: CategoryActionsProps) {
  return (
    <>
      <FavoriteCategoryButton slug={slug} variant={variant} />
      <SaveSearchButton params={saveParams} variant={variant} />
    </>
  );
}
