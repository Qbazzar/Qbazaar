import 'server-only';

import type { HelpCategoryWithArticles } from './help';
import { fetchPublicResource } from './public-resource';

/** A help topic with its articles, read on the server; `null` when it does not exist. */
export function fetchHelpCategory(slug: string): Promise<HelpCategoryWithArticles | null> {
  return fetchPublicResource<HelpCategoryWithArticles>(`/api/v1/help/categories/${encodeURIComponent(slug)}`);
}
