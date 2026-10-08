'use client';

import { createContext, useContext, type ReactNode } from 'react';

import type { Location } from '@/lib/api/types';
import { findLocationBySlug, useLocationsStore } from '@/store/locations';

const RenderedPlacesContext = createContext<Location[] | undefined>(undefined);

/**
 * The Qatar locations tree a server page fetched. Below it, the first render
 * (on the server and again at hydration) already names the places, before
 * the browser's copy of the tree reaches the locations store.
 */
export function QatarPlacesProvider({ places, children }: { places: Location[] | undefined; children: ReactNode }) {
  return <RenderedPlacesContext.Provider value={places}>{children}</RenderedPlacesContext.Provider>;
}

/** The tree the page was rendered with, if any, to seed `useQatarLocationsQuery`. */
export function useRenderedQatarPlaces(): Location[] | undefined {
  return useContext(RenderedPlacesContext);
}

/** The place a slug names: from the locations store, or before it loads, from the tree the page was rendered with. */
export function useQatarPlace(slug: string): Location | null {
  const loaded = useLocationsStore((state) => state.findBySlug(slug));
  const rendered = useContext(RenderedPlacesContext);
  return loaded ?? findLocationBySlug(rendered, slug);
}
