/**
 * The design's icon set: every SVG of the reference HTML (tools/parity/reference-icons) as a
 * component named after the reference icon (`add-ads-pin` -> `AddAdsPinIcon`), plus lucide
 * fallbacks for the few objects the reference never draws. scripts/icons/registry.json lists
 * each glyph's reference size, stroke variants, colour and where the design uses it;
 * scripts/icons/codemod.mjs moves lucide imports onto this set.
 */
export { createGlyph, isRegistryGlyph } from './glyph';
export type { GlyphData, GlyphNode, GlyphProps, IconGlyph, RegistryGlyph } from './glyph';
export * from './glyphs';
