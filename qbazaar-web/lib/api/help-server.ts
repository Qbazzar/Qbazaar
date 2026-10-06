import 'server-only';

import { cache } from 'react';

import type { HelpCategoryWithArticles } from './help';
import { fetchPublicResource } from './public-resource';

/**
 * A help topic with its articles, read on the server; `null` when it does not
 * exist. Memoized per request, so the metadata and the page share one read.
 */
export const fetchHelpCategory = cache(
  (slug: string): Promise<HelpCategoryWithArticles | null> =>
    fetchPublicResource<HelpCategoryWithArticles>(`/api/v1/help/categories/${encodeURIComponent(slug)}`),
);
