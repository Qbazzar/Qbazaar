import 'server-only';

import { cache } from 'react';

import type { HelpCategoryWithArticles } from './help';
import { fetchPublicResource } from './public-resource';
import type { HelpCategory } from './types';

/** Every help topic with its article count, read on the server and memoized per request. */
export const fetchHelpCategories = cache(
  async (): Promise<HelpCategory[]> =>
    (await fetchPublicResource<HelpCategory[]>('/api/v1/help/categories')) ?? [],
);

/**
 * A help topic with its articles, read on the server; `null` when it does not
 * exist. Memoized per request, so the metadata and the page share one read.
 */
export const fetchHelpCategory = cache(
  (slug: string): Promise<HelpCategoryWithArticles | null> =>
    fetchPublicResource<HelpCategoryWithArticles>(`/api/v1/help/categories/${encodeURIComponent(slug)}`),
);
