
export const categoryKeys = {
  all: ['categories'] as const,
  tree: () => [...categoryKeys.all, 'tree'] as const,
  main: () => [...categoryKeys.all, 'main'] as const,
  // `is_favorited` belongs to the viewer, so a guest's cached page is never shown to the account that logs in.
  page: (slug: string, viewer: string) => [...categoryKeys.all, 'page', slug, viewer] as const,
  stats: (slug: string) => [...categoryKeys.all, 'stats', slug] as const,
  fields: (slug: string) => [...categoryKeys.all, 'fields', slug] as const,
};
