/**
 * Categories store — Zustand.
 *
 * TanStack Query handles network caching, freshness, and revalidation; this
 * store exists purely to hold the *current* tree + main-categories snapshot
 * so render-tree-far components (breadcrumb, sidebar, page <title>) can
 * derive lookups without re-querying or prop-drilling.
 *
 * NOT persisted — the store rehydrates from React Query on every page load.
 */
import { create } from 'zustand';
import type { Category, CategoryNode } from '@/lib/api/types';

/**
 * Depth-first search through a category forest. Exported so server components
 * can reuse the same lookup without instantiating the store.
 */
export function findCategoryBySlug(
  nodes: CategoryNode[] | null | undefined,
  slug: string,
): CategoryNode | null {
  if (!nodes) return null;
  for (const node of nodes) {
    if (node.slug === slug) return node;
    const hit = findCategoryBySlug(node.children, slug);
    if (hit) return hit;
  }
  return null;
}

/** Root-to-node chain for a slug (the breadcrumb trail), or null when absent. */
export function findCategoryPath(
  nodes: CategoryNode[] | null | undefined,
  slug: string,
): CategoryNode[] | null {
  for (const node of nodes ?? []) {
    if (node.slug === slug) return [node];
    const rest = findCategoryPath(node.children, slug);
    if (rest) return [node, ...rest];
  }
  return null;
}

export interface CategoriesState {
  tree: CategoryNode[] | null;
  mainCategories: Category[] | null;
  /** True once either setter has been called at least once. */
  hydrated: boolean;
  setTree: (tree: CategoryNode[]) => void;
  setMainCategories: (cats: Category[]) => void;
  findBySlug: (slug: string) => CategoryNode | null;
}

export const useCategoriesStore = create<CategoriesState>((set, get) => ({
  tree: null,
  mainCategories: null,
  hydrated: false,
  setTree: (tree) => set({ tree, hydrated: true }),
  setMainCategories: (mainCategories) => set({ mainCategories, hydrated: true }),
  findBySlug: (slug) => findCategoryBySlug(get().tree, slug),
}));
