'use client';

import { useMemo } from 'react';

import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { localized } from '@/lib/i18n/locale';
import type { LocalizedString } from '@/lib/api/types';

import { labelFromSlug } from './format';

interface NamedNode {
  slug: string;
  name: LocalizedString;
  children?: NamedNode[] | null;
}

export interface SlugLabels {
  category: (slug: string | null | undefined) => string;
  location: (slug: string | null | undefined) => string;
}

function namesBySlug(nodes: readonly NamedNode[] | undefined): Map<string, string> {
  const names = new Map<string, string>();
  const visit = (list: readonly NamedNode[]) => {
    for (const node of list) {
      names.set(node.slug, localized(node.name));
      if (node.children) visit(node.children);
    }
  };
  if (nodes) visit(nodes);
  return names;
}

/**
 * Category and location names in the active language for the slugs the
 * account lists carry (AdSummary and saved searches have no names). Reads the
 * cached category and location trees; until they load, or for an unknown
 * slug, the slug is shown as words.
 */
export function useSlugLabels(): SlugLabels {
  const { data: categories } = useCategoryTreeQuery();
  const { data: locations } = useQatarLocationsQuery();

  return useMemo(() => {
    const categoryNames = namesBySlug(categories);
    const locationNames = namesBySlug(locations);
    const lookup = (names: Map<string, string>) => (slug: string | null | undefined) =>
      slug ? names.get(slug) || labelFromSlug(slug) : '';
    return { category: lookup(categoryNames), location: lookup(locationNames) };
  }, [categories, locations]);
}
