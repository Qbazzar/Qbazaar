import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { CATEGORY_GLYPHS, CategoryIcon } from './CategoryIcon';
import { ElectronicsTileIcon } from './glyphs';

const lucideMap = JSON.parse(readFileSync(join(process.cwd(), 'scripts', 'icons', 'lucide-map.json'), 'utf8')) as {
  categoryIcons: Record<string, string>;
};

describe('CategoryIcon', () => {
  it('draws the reference tile for a seeded category icon name (Electronics is the bolt)', () => {
    const { container } = render(<CategoryIcon name="Smartphone" className="size-6" />);
    const svg = container.querySelector('svg')!;

    expect(svg.querySelector('path')).toHaveAttribute('d', 'M13 2 4 14h7l-1 8 9-12h-7l1-8z');
    expect(svg).toHaveAttribute('stroke-width', '1.6');
    expect(svg).toHaveClass('size-6');
  });

  it('accepts kebab-case names like DynamicIcon', () => {
    const { container } = render(<CategoryIcon name="paw-print" />);
    const svg = container.querySelector('svg');

    expect(svg).not.toHaveClass('lucide');
    expect(svg).toHaveAttribute('stroke-width', '1.6');
  });

  it('falls back to the lucide set for names the design has no tile for', () => {
    const { container } = render(<CategoryIcon name="Factory" />);
    expect(container.querySelector('svg')).toHaveClass('lucide-factory');
  });

  it('matches categoryIcons in lucide-map.json', () => {
    const fromMap = Object.fromEntries(Object.entries(CATEGORY_GLYPHS).map(([name, glyph]) => [name, glyph.glyph?.name]));
    expect(fromMap).toEqual(lucideMap.categoryIcons);
    expect(CATEGORY_GLYPHS.Smartphone).toBe(ElectronicsTileIcon);
  });
});
