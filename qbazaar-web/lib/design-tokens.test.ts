import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

import { QB_TOKEN_NAMES, cn } from '@/lib/utils';

const tokensCss = readFileSync(join(__dirname, '..', 'styles', 'design-tokens.css'), 'utf8');

describe('design tokens', () => {
  it.each([
    ['text', QB_TOKEN_NAMES.text],
    ['radius', QB_TOKEN_NAMES.radius],
    ['shadow', QB_TOKEN_NAMES.shadow],
  ] as const)('declares every %s token tailwind-merge is told about', (namespace, names) => {
    for (const name of names) {
      expect(tokensCss).toContain(`--${namespace}-${name}:`);
    }
  });

  it('keeps the brand values from the Figma file', () => {
    expect(tokensCss).toContain('--color-qb-brand: #f38057;');
    expect(tokensCss).toContain('--shadow-qb-card: 0 4px 40px 0 rgb(161 161 161 / 0.15);');
  });
});

describe('cn with design tokens', () => {
  it('keeps a qb font size next to a qb text colour', () => {
    expect(cn('text-qb-body', 'text-qb-ink')).toBe('text-qb-body text-qb-ink');
  });

  it('still lets a later size or radius replace an earlier one', () => {
    expect(cn('text-qb-body', 'text-qb-h2')).toBe('text-qb-h2');
    expect(cn('rounded-qb-md', 'rounded-qb-xl')).toBe('rounded-qb-xl');
    expect(cn('shadow-qb-card', 'shadow-qb-hover')).toBe('shadow-qb-hover');
  });
});
