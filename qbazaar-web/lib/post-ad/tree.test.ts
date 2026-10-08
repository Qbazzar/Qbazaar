import { describe, expect, it } from 'vitest';

import type { CategoryNode } from '@/lib/api/types';

import { fieldsOf, findPath, isLeaf } from './tree';

const node = (id: string, children: CategoryNode[] = [], customFields: CategoryNode['custom_fields'] = null) =>
  ({ id, children, custom_fields: customFields, name: { ar: id, en: id } }) as unknown as CategoryNode;

const TREE = [
  node('vehicles', [node('cars', [], [{ key: 'year', label: { ar: 'السنة', en: 'Year' }, type: 'number', required: true, options: null }]), node('boats')]),
  node('jobs', [node('full-time')]),
];

describe('category tree helpers', () => {
  it('finds the path from the root down to a category', () => {
    expect(findPath(TREE, 'cars').map((n) => n.id)).toEqual(['vehicles', 'cars']);
    expect(findPath(TREE, 'jobs').map((n) => n.id)).toEqual(['jobs']);
    expect(findPath(TREE, 'unknown')).toEqual([]);
    expect(findPath(TREE, null)).toEqual([]);
  });

  it('knows leaves and returns the custom fields of a leaf only', () => {
    expect(isLeaf(TREE[0])).toBe(false);
    expect(fieldsOf(TREE, 'cars').map((f) => f.key)).toEqual(['year']);
    expect(fieldsOf(TREE, 'boats')).toEqual([]);
    expect(fieldsOf(TREE, 'vehicles')).toEqual([]);
  });
});
