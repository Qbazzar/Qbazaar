import { localized } from '@/lib/i18n/locale';
import type { CategoryNode } from '@/lib/api/types';

/** What the category page needs to pick its view and draw its header. */
export interface ResolvedCategory {
  id: string;
  slug: string;
  hasChildren: boolean;
  /** Root-to-category names for the breadcrumb, in the page language. */
  trail: { slug: string; name: string }[];
}

/** Builds it from a root-to-category path of the tree; shared by the server page and the client view. */
export function resolveCategoryPath(path: CategoryNode[]): ResolvedCategory {
  const node = path[path.length - 1];
  return {
    id: node.id,
    slug: node.slug,
    hasChildren: node.children.length > 0,
    trail: path.map((crumb) => ({ slug: crumb.slug, name: localized(crumb.name) })),
  };
}
