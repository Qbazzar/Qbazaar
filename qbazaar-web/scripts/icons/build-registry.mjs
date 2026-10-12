// Builds the icon registry from the reference SVG library.
// Run from qbazaar-web: node scripts/icons/build-registry.mjs
// (outputs are committed; the script is not part of the build)
//
// Inputs:  ../tools/parity/reference-icons/*.svg + index.json (the design's icons, never edited here)
//          scripts/icons/lucide-map.json (fallbacks: lucide names the reference has no glyph for)
//          scripts/icons/fallback-glyphs.json (snapshot of those lucide drawings, refreshed from
//          node_modules/lucide-react while it is installed)
// Outputs: components/design-system/icons/glyphs.ts (one component per glyph)
//          scripts/icons/registry.json (per glyph: export name, reference size, stroke variants, colour, use)
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const WEB = join(HERE, '..', '..');
const REFERENCE_DIR = join(WEB, '..', 'tools', 'parity', 'reference-icons');
const MAP_FILE = join(HERE, 'lucide-map.json');
const FALLBACK_FILE = join(HERE, 'fallback-glyphs.json');
const LUCIDE_ICONS_DIR = join(WEB, 'node_modules', 'lucide-react', 'dist', 'esm', 'icons');
const GLYPHS_FILE = join(WEB, 'components', 'design-system', 'icons', 'glyphs.ts');
const REGISTRY_FILE = join(HERE, 'registry.json');

/** The logo is an <img> asset (SiteLogo) and the footer chevron a CSS border box, not glyphs to render. */
const NOT_GLYPHS = new Set(['logo', 'footer-accordion-chevron']);
/** The reference's most common stroke for 24px UI glyphs; fallbacks are drawn with it. */
const FALLBACK_STROKE_WIDTH = '1.7';

const PAINTS = new Set(['none', 'currentColor']);
const ROOT_ATTRIBUTES = ['viewBox', 'fill', 'stroke', 'strokeWidth', 'strokeLinecap', 'strokeLinejoin', 'fillRule'];
const DROPPED_ATTRIBUTES = new Set(['xmlns', 'class', 'style', 'aria-hidden', 'focusable', 'role']);
/** Root attributes the size prop or the page sets instead (width/height) or that carry no drawing. */
const ROOT_IGNORED = new Set(['xmlns', 'width', 'height', 'style', 'class']);

function exportNameFor(glyphName) {
  return `${glyphName.replace(/(^|-)([a-z0-9])/g, (_match, _dash, character) => character.toUpperCase())}Icon`;
}

/** stroke-width -> strokeWidth; viewBox and single words stay. */
function reactAttributeName(name) {
  return name.replace(/-([a-z])/g, (_match, character) => character.toUpperCase());
}

/** Minimal parser for the library's SVG files: elements and attributes only. */
function parseSvg(source) {
  const root = { tag: '#root', attributes: {}, children: [] };
  const stack = [root];
  const tokens = source.replace(/<\?xml[\s\S]*?\?>|<!--[\s\S]*?-->/g, '').matchAll(/<(\/?)([a-zA-Z][\w:-]*)((?:\s+[\w:-]+\s*=\s*(?:"[^"]*"|'[^']*'))*)\s*(\/?)>/g);
  for (const [, closing, tag, rawAttributes, selfClosing] of tokens) {
    if (closing) {
      if (stack.pop().tag !== tag) throw new Error(`Unbalanced </${tag}>`);
      continue;
    }
    const attributes = {};
    for (const [, name, double, single] of rawAttributes.matchAll(/([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/g)) {
      attributes[name] = double ?? single;
    }
    const element = { tag, attributes, children: [] };
    stack.at(-1).children.push(element);
    if (!selfClosing) stack.push(element);
  }
  if (stack.length !== 1 || root.children.length !== 1 || root.children[0].tag !== 'svg') throw new Error('Expected one <svg> root');
  return root.children[0];
}

function toNode(element) {
  const attributes = {};
  for (const [name, value] of Object.entries(element.attributes)) {
    if (!DROPPED_ATTRIBUTES.has(name)) attributes[reactAttributeName(name)] = value;
  }
  const children = element.children.map(toNode);
  return children.length ? [element.tag, attributes, children] : [element.tag, attributes];
}

/** The <svg> drawing attributes; a fixed outer colour becomes currentColor (recorded as `colour`). */
function rootOf(svg) {
  const known = new Set([...ROOT_ATTRIBUTES, ...ROOT_ATTRIBUTES.map((name) => name.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)), ...ROOT_IGNORED]);
  const unknown = Object.keys(svg.attributes).filter((name) => !known.has(name));
  if (unknown.length) throw new Error(`Unhandled <svg> attributes: ${unknown.join(', ')}`);
  const attributes = {};
  let colour = null;
  for (const name of ROOT_ATTRIBUTES) {
    const raw = svg.attributes[name] ?? svg.attributes[name.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)];
    if (raw === undefined) continue;
    if ((name === 'fill' || name === 'stroke') && !PAINTS.has(raw)) {
      colour ??= raw;
      attributes[name] = 'currentColor';
    } else {
      attributes[name] = raw;
    }
  }
  const styleColour = svg.attributes.style?.match(/(?:^|;)\s*color\s*:\s*([^;]+)/)?.[1]?.trim();
  return { attributes, colour: colour ?? styleColour ?? null };
}

function strokeVariants(icon) {
  const values = [icon.strokeWidth, ...(icon.sourceVariants ?? []).map((variant) => variant.strokeWidth)];
  return [...new Set(values.filter((value) => typeof value === 'number'))].sort((a, b) => a - b);
}

function referenceGlyphs(index) {
  return index.icons
    .filter((icon) => !NOT_GLYPHS.has(icon.name))
    .map((icon) => {
      const svg = parseSvg(readFileSync(join(REFERENCE_DIR, icon.file), 'utf8'));
      const { attributes, colour } = rootOf(svg);
      return {
        name: icon.name,
        source: 'reference',
        attributes,
        nodes: svg.children.map(toNode),
        meta: {
          size: icon.declaredSize ?? null,
          strokeWidth: icon.strokeWidth ?? null,
          strokes: strokeVariants(icon),
          colour: colour ?? (icon.colour && icon.colour !== 'currentColor' ? icon.colour : null),
          use: icon.description,
        },
      };
    });
}

/** lucide-react keeps each drawing as `__iconNode` in dist/esm/icons/<name>.mjs. */
function readLucideNode(lucideName) {
  const file = join(LUCIDE_ICONS_DIR, `${lucideName}.mjs`);
  if (!existsSync(file)) return null;
  const literal = readFileSync(file, 'utf8').match(/const __iconNode = (\[[\s\S]*?\]);\n/)?.[1];
  if (!literal) throw new Error(`No __iconNode in ${file}`);
  const node = Function(`"use strict"; return (${literal});`)();
  return node.map(([tag, { key: _key, ...attributes }]) => [tag, Object.fromEntries(Object.entries(attributes).map(([k, v]) => [k, String(v)]))]);
}

function fallbackGlyphs(map) {
  const snapshot = existsSync(FALLBACK_FILE) ? JSON.parse(readFileSync(FALLBACK_FILE, 'utf8')) : { glyphs: {} };
  const wanted = new Map();
  for (const [lucideExport, fallback] of Object.entries(map.fallbacks)) {
    if (!wanted.has(fallback.lucide)) wanted.set(fallback.lucide, { reason: fallback.reason, aliases: [] });
    wanted.get(fallback.lucide).aliases.push(lucideExport);
  }
  const glyphs = {};
  for (const lucideName of [...wanted.keys()].sort()) {
    const nodes = snapshot.glyphs[lucideName] ?? readLucideNode(lucideName);
    if (!nodes) throw new Error(`Fallback ${lucideName}: not in the snapshot and lucide-react is not installed`);
    glyphs[lucideName] = nodes;
  }
  writeFileSync(
    FALLBACK_FILE,
    `${JSON.stringify({ $license: 'Drawings from lucide-react (ISC, https://lucide.dev/license), kept for icons the reference design does not draw.', glyphs }, null, 2)}\n`,
  );
  return [...wanted].map(([name, { reason, aliases }]) => ({
    name,
    source: 'fallback',
    attributes: {
      viewBox: '0 0 24 24',
      fill: 'none',
      stroke: 'currentColor',
      strokeWidth: FALLBACK_STROKE_WIDTH,
      strokeLinecap: 'round',
      strokeLinejoin: 'round',
    },
    nodes: glyphs[name],
    meta: { size: null, strokeWidth: Number(FALLBACK_STROKE_WIDTH), strokes: [Number(FALLBACK_STROKE_WIDTH)], colour: null, use: `fallback for lucide ${aliases.join(', ')}: ${reason}` },
  }));
}

function literal(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function attributesLiteral(attributes) {
  const entries = Object.entries(attributes).map(([key, value]) => `${/^[a-zA-Z_$][\w$]*$/.test(key) ? key : literal(key)}: ${literal(value)}`);
  return `{ ${entries.join(', ')} }`;
}

function nodeLiteral([tag, attributes, children]) {
  const parts = [literal(tag), attributesLiteral(attributes)];
  if (children) parts.push(`[${children.map(nodeLiteral).join(', ')}]`);
  return `[${parts.join(', ')}]`;
}

function glyphSource(glyph) {
  const exportName = exportNameFor(glyph.name);
  const doc = glyph.source === 'reference' ? `/** ${glyph.meta.use} */` : `/** Fallback (lucide ${glyph.name}): ${glyph.meta.use.split(': ').slice(1).join(': ')} */`;
  return [
    doc,
    `export const ${exportName} = /* @__PURE__ */ createGlyph(${literal(exportName)}, {`,
    `  name: ${literal(glyph.name)},`,
    `  attributes: ${attributesLiteral(glyph.attributes)},`,
    `  nodes: [${glyph.nodes.map(nodeLiteral).join(', ')}],`,
    '});',
  ].join('\n');
}

function main() {
  const index = JSON.parse(readFileSync(join(REFERENCE_DIR, 'index.json'), 'utf8'));
  const map = JSON.parse(readFileSync(MAP_FILE, 'utf8'));
  const glyphs = [...referenceGlyphs(index), ...fallbackGlyphs(map)].sort((a, b) => a.name.localeCompare(b.name));

  const names = new Set();
  for (const glyph of glyphs) {
    if (names.has(glyph.name)) throw new Error(`Glyph name ${glyph.name} is used twice (reference and fallback)`);
    names.add(glyph.name);
  }
  for (const target of [...Object.values(map.glyphs), ...Object.values(map.categoryIcons), ...Object.values(map.files).flatMap((file) => Object.entries(file).filter(([key]) => key !== '$comment').map(([, value]) => value))]) {
    if (!names.has(target)) throw new Error(`lucide-map.json names ${target}, which is not in the registry`);
  }
  const unused = [...readdirSync(REFERENCE_DIR)].filter((file) => file.endsWith('.svg') && !index.icons.some((icon) => icon.file === file));
  if (unused.length) throw new Error(`SVGs missing from index.json: ${unused.join(', ')}`);

  writeFileSync(
    GLYPHS_FILE,
    [
      '// Generated by scripts/icons/build-registry.mjs from tools/parity/reference-icons. Do not edit by hand.',
      '// Fallback glyphs: lucide-react drawings (ISC, https://lucide.dev/license) for objects the reference does not draw.',
      "import { createGlyph } from './glyph';",
      '',
      glyphs.map(glyphSource).join('\n\n'),
      '',
    ].join('\n'),
  );

  const registry = Object.fromEntries(glyphs.map((glyph) => [glyph.name, { export: exportNameFor(glyph.name), source: glyph.source, ...glyph.meta }]));
  writeFileSync(REGISTRY_FILE, `${JSON.stringify({ generatedFrom: 'tools/parity/reference-icons/index.json', glyphs: registry }, null, 2)}\n`);
  console.log(`${glyphs.length} glyphs (${glyphs.filter((g) => g.source === 'reference').length} reference, ${glyphs.filter((g) => g.source === 'fallback').length} fallback)`);
}

main();
