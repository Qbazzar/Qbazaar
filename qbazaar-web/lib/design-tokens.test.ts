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
    ['spacing', QB_TOKEN_NAMES.spacing],
  ] as const)('declares every %s token tailwind-merge is told about', (namespace, names) => {
    for (const name of names) {
      expect(tokensCss).toContain(`--${namespace}-${name}:`);
    }
  });

  it('keeps the Figma card shadow and the Figma brand orange', () => {
    expect(tokensCss).toContain('--color-qb-brand: #f38057;');
    expect(tokensCss).toContain('--shadow-qb-card: 0 4px 40px 0 rgb(161 161 161 / 0.15);');
  });
});

/** The design's exact colours (owner decision: never darken them for contrast). */
const DESIGN_COLOURS = {
  brand: '#f38057',
  'brand-hover': '#e96c3f',
  'brand-active': '#d3664c',
  'brand-on-soft': '#f38057',
  'ink-muted': '#757575',
  'ink-subtle': '#9e9e9e',
  'ink-faint': '#9e9696',
  'ink-disabled': '#bdbdbd',
  placeholder: '#bfbfbf',
  'icon-muted': '#a19f9f',
  'icon-faint': '#bdbdbd',
  'icon-accordion': '#aaaaaa',
  breadcrumb: '#a4adba',
  'field-border': '#ededed',
  success: '#1bad07',
  danger: '#e64646',
  info: '#2b6fdb',
} as const;

describe('design colours', () => {
  it.each(Object.entries(DESIGN_COLOURS))('%s is the design value %s', (name, hex) => {
    expect(tokensCss).toContain(`--color-qb-${name}: ${hex};`);
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

  it('lets a later padding replace the site gutter', () => {
    expect(cn('px-qb-gutter', 'px-5 qb-desktop:px-qb-gutter')).toBe('px-5 qb-desktop:px-qb-gutter');
  });
});
