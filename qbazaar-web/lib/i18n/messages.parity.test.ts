import { describe, expect, it } from 'vitest';

import ar from '@/i18n/ar.json';
import en from '@/i18n/en.json';

const ARABIC_ONLY_PLURALS = new Set(['zero', 'two', 'few', 'many']);

function flatten(node: unknown, prefix = ''): string[] {
  if (typeof node !== 'object' || node === null) return [prefix];
  return Object.entries(node).flatMap(([key, value]) => flatten(value, prefix ? `${prefix}.${key}` : key));
}

describe('message catalogs', () => {
  const enKeys = new Set(flatten(en));
  const arKeys = new Set(flatten(ar));

  it('has every English key in Arabic', () => {
    expect([...enKeys].filter((key) => !arKeys.has(key))).toEqual([]);
  });

  it('only adds Arabic plural categories on top of the English keys', () => {
    const extra = [...arKeys].filter((key) => !enKeys.has(key));
    const unexpected = extra.filter((key) => !ARABIC_ONLY_PLURALS.has(key.split('.').pop() ?? ''));
    expect(unexpected).toEqual([]);
  });
});
