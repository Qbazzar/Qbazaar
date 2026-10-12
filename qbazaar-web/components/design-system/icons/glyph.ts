/**
 * Renders the reference SVG icons (tools/parity/reference-icons) as React components.
 *
 * Each glyph keeps the reference drawing: its viewBox, paths, stroke width, linecaps and
 * inner paints. Only the outer colour is `currentColor`, so the text colour of the usage
 * paints it, as with lucide. No hooks, so glyphs render in server components too.
 */
import { createElement, forwardRef, type ForwardRefExoticComponent, type ReactNode, type RefAttributes, type SVGProps } from 'react';

type GlyphAttributes = Readonly<Record<string, string>>;

/** [tag, attributes, children] with React attribute names (strokeWidth, fillRule). */
export type GlyphNode = readonly [tag: string, attributes: GlyphAttributes, children?: readonly GlyphNode[]];

export interface GlyphData {
  /** Reference icon name, as in reference-icons/index.json (or the lucide name of a fallback). */
  readonly name: string;
  /** viewBox, fill, stroke, strokeWidth, strokeLinecap, strokeLinejoin of the reference <svg>. */
  readonly attributes: GlyphAttributes;
  readonly nodes: readonly GlyphNode[];
}

export interface GlyphProps extends Omit<SVGProps<SVGSVGElement>, 'ref'> {
  /** Width and height (px or any CSS length). 24 by default, like lucide, so a swapped import keeps its box. */
  size?: number | string;
}

/**
 * An icon component, and what an `icon` prop accepts. A forwardRef component like a lucide
 * icon, so registry glyphs and lucide icons fit each other's props both ways while the
 * streams move their files one at a time; `glyph` is set on registry glyphs only.
 */
export type IconGlyph = ForwardRefExoticComponent<GlyphProps & RefAttributes<SVGSVGElement>> & { readonly glyph?: GlyphData };

export type RegistryGlyph = IconGlyph & { readonly glyph: GlyphData };

const DEFAULT_SIZE = 24;

/** Like lucide: an icon without an accessible name is hidden from assistive tech. */
function hasAccessibleName(props: object): boolean {
  return Object.keys(props).some((key) => key.startsWith('aria-') || key === 'role' || key === 'title');
}

function renderNodes(nodes: readonly GlyphNode[]): ReactNode[] {
  return nodes.map(([tag, attributes, children], index) =>
    createElement(tag, { key: index, ...attributes }, children ? renderNodes(children) : undefined),
  );
}

export function createGlyph(displayName: string, glyph: GlyphData): IconGlyph {
  const Glyph = forwardRef<SVGSVGElement, GlyphProps>(function Glyph({ size = DEFAULT_SIZE, children, ...props }, ref) {
    return createElement(
      'svg',
      {
        ref,
        xmlns: 'http://www.w3.org/2000/svg',
        width: size,
        height: size,
        ...glyph.attributes,
        ...(hasAccessibleName(props) ? null : { 'aria-hidden': true }),
        ...props,
      },
      ...renderNodes(glyph.nodes),
      children,
    );
  });
  Glyph.displayName = displayName;
  return Object.assign(Glyph, { glyph });
}

export function isRegistryGlyph(icon: IconGlyph): icon is RegistryGlyph {
  return icon.glyph !== undefined;
}
