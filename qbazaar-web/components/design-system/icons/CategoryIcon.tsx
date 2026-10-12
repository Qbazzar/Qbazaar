/**
 * A category's icon as the design draws it. The API names category icons after lucide
 * ("Smartphone"); the seeded names resolve to the reference category tiles, any other name
 * (an admin's pick) still renders through DynamicIcon.
 */
import { DynamicIcon } from '@/components/ui/dynamic-icon';

import type { GlyphProps, IconGlyph } from './glyph';
import {
  CarsTileIcon,
  ClothesTileIcon,
  ElectronicsTileIcon,
  HomeGardenTileIcon,
  JobsTileIcon,
  LeisureTileIcon,
  PetsTileIcon,
  RealEstateTileIcon,
  ServicesTileIcon,
} from './glyphs';

/** Mirrors `categoryIcons` in scripts/icons/lucide-map.json (a test keeps the two equal). */
export const CATEGORY_GLYPHS: Readonly<Record<string, IconGlyph>> = {
  Bike: LeisureTileIcon,
  Briefcase: JobsTileIcon,
  Car: CarsTileIcon,
  Home: RealEstateTileIcon,
  PawPrint: PetsTileIcon,
  Shirt: ClothesTileIcon,
  Smartphone: ElectronicsTileIcon,
  Sofa: HomeGardenTileIcon,
  Wrench: ServicesTileIcon,
};

/** "paw-print" and "PawPrint" name the same icon, as in DynamicIcon. */
function pascalCase(name: string): string {
  return name.replace(/(^|-)([a-z0-9])/g, (_match, _dash, character: string) => character.toUpperCase());
}

interface CategoryIconProps extends Omit<GlyphProps, 'name' | 'ref'> {
  name: string | null | undefined;
}

export function CategoryIcon({ name, ...props }: CategoryIconProps) {
  const Tile = name ? CATEGORY_GLYPHS[pascalCase(name)] : undefined;
  return Tile ? <Tile {...props} /> : <DynamicIcon name={name} {...props} />;
}
