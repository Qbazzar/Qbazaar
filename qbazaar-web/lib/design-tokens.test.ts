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

  it('keeps the Figma card shadow and the AA-darkened brand orange', () => {
    expect(tokensCss).toContain('--color-qb-brand: #cf410f;');
    expect(tokensCss).toContain('--shadow-qb-card: 0 4px 40px 0 rgb(161 161 161 / 0.15);');
  });
});

function colorToken(name: string): string {
  const match = tokensCss.match(new RegExp(`--color-qb-${name}: (#[0-9a-f]{6});`));
  if (!match) throw new Error(`--color-qb-${name} is not a hex colour`);
  return match[1];
}

function relativeLuminance(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => {
    const channel = parseInt(hex.slice(i, i + 2), 16) / 255;
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrastRatio(a: string, b: string): number {
  const [light, dark] = [relativeLuminance(a), relativeLuminance(b)].sort((x, y) => y - x);
  return (light + 0.05) / (dark + 0.05);
}

describe('colour contrast (WCAG AA, 4.5:1)', () => {
  it.each([
    ['on-brand', 'brand'],
    ['on-brand', 'brand-hover'],
    ['on-brand', 'brand-active'],
    ['brand', 'page'],
    ['brand-on-soft', 'brand-soft'],
    ['ink-muted', 'fill-strong'],
    ['ink-subtle', 'fill-strong'],
    ['ink-faint', 'fill-strong'],
    ['ink-disabled', 'page'],
    ['placeholder', 'surface'],
    ['breadcrumb', 'page'],
    ['success', 'success-soft'],
    ['danger', 'danger-soft'],
    ['info', 'info-soft'],
  ])('%s text on %s', (text, background) => {
    expect(contrastRatio(colorToken(text), colorToken(background))).toBeGreaterThanOrEqual(4.5);
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
