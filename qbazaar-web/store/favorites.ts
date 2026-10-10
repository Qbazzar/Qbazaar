/**
 * Favorites store — Zustand.
 *
 * Holds the set of ad ids the current user has favorited so every `AdCard`
 * heart icon stays in sync without making each one re-query the server.
 *
 * Hydration is lazy: `useFavoritesQuery` dumps the first page of ids into the
 * set on its first successful response, and individual toggles patch the set
 * optimistically before the server confirms. We clear the set on logout via
 * a non-reactive helper called from the auth-store sign-out flow.
 */
import { create } from 'zustand';

export interface FavoritesState {
  ids: Set<string>;
  /** Ads whose saved state the store has been told (by the server or a toggle), either way. */
  known: Set<string>;
  /** Replace the set wholesale (e.g. when the favorites list reloads). */
  setIds: (ids: Iterable<string>) => void;
  /** Merge ids into the existing set (used as pages of favorites load). */
  mergeIds: (ids: Iterable<string>) => void;
  /** Optimistic toggle — flips the local state without hitting the server. */
  toggleLocal: (id: string) => void;
  /** Force a specific state (used on mutation `onError` to roll back). */
  setOne: (id: string, favorited: boolean) => void;
  clear: () => void;
}

function withKnown(known: Set<string>, ids: Iterable<string>): Set<string> {
  const next = new Set(known);
  for (const id of ids) next.add(id);
  return next;
}

export const useFavoritesStore = create<FavoritesState>((set) => ({
  ids: new Set<string>(),
  known: new Set<string>(),
  setIds: (ids) => {
    const next = new Set(ids);
    set((state) => ({ ids: next, known: withKnown(state.known, next) }));
  },
  mergeIds: (ids) =>
    set((state) => {
      const list = [...ids];
      return { ids: withKnown(state.ids, list), known: withKnown(state.known, list) };
    }),
  toggleLocal: (id) =>
    set((state) => {
      const next = new Set(state.ids);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return { ids: next, known: withKnown(state.known, [id]) };
    }),
  setOne: (id, favorited) =>
    set((state) => {
      const next = new Set(state.ids);
      if (favorited) next.add(id);
      else next.delete(id);
      return { ids: next, known: withKnown(state.known, [id]) };
    }),
  clear: () => set({ ids: new Set<string>(), known: new Set<string>() }),
}));

/**
 * Non-reactive accessor used by the auth-store sign-out flow so it doesn't
 * subscribe to React's render cycle.
 */
export function clearFavoritesNonReactive(): void {
  useFavoritesStore.getState().clear();
}

/**
 * Selector helper — kept here so callers don't have to remember the shape.
 */
export function selectIsFavorited(id: string) {
  return (state: FavoritesState): boolean => state.ids.has(id);
}
