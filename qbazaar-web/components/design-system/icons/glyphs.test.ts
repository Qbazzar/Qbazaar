/**
 * The registry must draw exactly what the reference library holds: same viewBox, elements,
 * path data, stroke widths, linecaps and inner paints. Only the outer colour may become
 * currentColor. Fallbacks must draw exactly what lucide draws.
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { createElement, type ComponentType } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import * as lucide from 'lucide-react';
import { describe, expect, it } from 'vitest';

import type { IconGlyph } from './glyph';
import * as glyphs from './glyphs';

// Vitest runs from qbazaar-web; the reference library sits beside it in tools/parity.
const referenceDir = join(process.cwd(), '..', 'tools', 'parity', 'reference-icons', '/');
const scriptsDir = join(process.cwd(), 'scripts', 'icons', '/');
const index = JSON.parse(readFileSync(`${referenceDir}index.json`, 'utf8')) as { icons: { name: string; file: string }[] };
const lucideMap = JSON.parse(readFileSync(`${scriptsDir}lucide-map.json`, 'utf8')) as { fallbacks: Record<string, { lucide: string }> };
const registry = JSON.parse(readFileSync(`${scriptsDir}registry.json`, 'utf8')) as { glyphs: Record<string, { export: string; source: string }> };

const NOT_GLYPHS = new Set(['logo', 'footer-accordion-chevron']);
const PAINTS = new Set(['none', 'currentColor']);
const ROOT_DRAWING = ['viewBox', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule'];

function exportNameFor(name: string) {
  return `${name.replace(/(^|-)([a-z0-9])/g, (_m, _d, c: string) => c.toUpperCase())}Icon`;
}

function parse(markup: string): Element {
  return new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement;
}

/** Every element under the root, in document order, as tag + sorted attributes. */
function drawing(svg: Element) {
  return [...svg.querySelectorAll('*')].map((element) => ({
    tag: element.tagName.toLowerCase(),
    attributes: [...element.attributes].map((attribute) => `${attribute.name}=${attribute.value}`).sort(),
  }));
}

function rendered(component: ComponentType) {
  return parse(renderToStaticMarkup(createElement(component)));
}

const allGlyphs = glyphs as unknown as Record<string, IconGlyph>;
const referenceIcons = index.icons.filter((icon) => !NOT_GLYPHS.has(icon.name));

describe('reference glyphs', () => {
  it.each(referenceIcons.map((icon) => [icon.name, icon.file]))('%s matches the reference SVG', (name, file) => {
    const component = allGlyphs[exportNameFor(name)];
    expect(component, `no export for ${name}`).toBeDefined();
    expect(component.glyph?.name).toBe(name);

    const reference = parse(readFileSync(`${referenceDir}${file}`, 'utf8'));
    const ours = rendered(component);

    for (const attribute of ROOT_DRAWING) expect(ours.getAttribute(attribute), attribute).toBe(reference.getAttribute(attribute));
    for (const paint of ['fill', 'stroke']) {
      const value = reference.getAttribute(paint);
      expect(ours.getAttribute(paint), paint).toBe(value === null || PAINTS.has(value) ? value : 'currentColor');
    }
    expect(drawing(ours)).toEqual(drawing(reference));
  });

  it('exports nothing the library does not hold, besides the listed fallbacks', () => {
    const expected = new Set([
      ...referenceIcons.map((icon) => exportNameFor(icon.name)),
      ...Object.values(lucideMap.fallbacks).map((fallback) => exportNameFor(fallback.lucide)),
    ]);
    expect(new Set(Object.keys(allGlyphs))).toEqual(expected);
  });

  it('lists every glyph in registry.json under its export name', () => {
    for (const component of Object.values(allGlyphs)) {
      expect(registry.glyphs[component.glyph?.name ?? '']?.export).toBe(component.displayName);
    }
    expect(Object.keys(registry.glyphs)).toHaveLength(Object.keys(allGlyphs).length);
  });
});

describe('fallback glyphs', () => {
  const lucideExports = lucide as unknown as Record<string, ComponentType>;

  it.each(Object.entries(lucideMap.fallbacks))('%s draws the lucide shape at the reference stroke', (lucideName, { lucide: glyphName }) => {
    const component = allGlyphs[exportNameFor(glyphName)];
    expect(registry.glyphs[glyphName].source).toBe('fallback');

    const ours = rendered(component);
    expect(ours.getAttribute('stroke-width')).toBe('1.7');
    expect(drawing(ours)).toEqual(drawing(rendered(lucideExports[lucideName])));
  });
});
