import { describe, expect, it } from 'vitest';

import type { CategoryNode } from '@/lib/api/types';

import { findCategoryPath } from './categories';

function node(slug: string, children: CategoryNode[] = []): CategoryNode {
  return {
    id: slug,
    parent_id: null,
    slug,
    name: { ar: slug, en: slug },
    description: null,
    icon: null,
    order: 0,
    is_active: true,
    custom_fields: null,
    custom_filters: null,
    ads_count: 0,
    today_count: 0,
    created_at: '',
    updated_at: '',
    children,
  };
}

const tree = [node('vehicles', [node('cars'), node('boats', [node('jet-skis')])]), node('pets')];

describe('findCategoryPath', () => {
  it('returns the chain from the root to the category', () => {
    expect(findCategoryPath(tree, 'jet-skis')?.map((n) => n.slug)).toEqual(['vehicles', 'boats', 'jet-skis']);
    expect(findCategoryPath(tree, 'pets')?.map((n) => n.slug)).toEqual(['pets']);
  });

  it('is null for an unknown slug or a missing tree', () => {
    expect(findCategoryPath(tree, 'phones')).toBeNull();
    expect(findCategoryPath(null, 'pets')).toBeNull();
  });
});
