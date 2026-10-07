/**
 * Typed client for the public categories endpoints.
 *
 * Every call goes through the shared `api` axios instance (so it inherits
 * auth + 401 refresh behaviour even though these endpoints are public — a
 * future sprint may add a "favourites" overlay that needs the bearer).
 *
 * The Laravel API wraps every payload in `{ success, data }`; the helpers
 * here unwrap `data` so callers can stay envelope-agnostic.
 */
import { api } from './client';
import type {
  Category,
  CategoryField,
  CategoryNode,
  CategoryPage,
  CategoryStats,
  SuccessEnvelope,
} from './types';

const BASE = '/api/v1/categories';

export async function getCategoryTree(): Promise<CategoryNode[]> {
  const { data } = await api.get<SuccessEnvelope<CategoryNode[]>>(
    `${BASE}/tree`,
  );
  return data.data;
}

export async function getMainCategories(): Promise<Category[]> {
  const { data } = await api.get<SuccessEnvelope<Category[]>>(`${BASE}/main`);
  return data.data;
}

/** Category page: the category plus one section of newest ads per active child. */
export async function getCategoryPage(slug: string): Promise<CategoryPage> {
  const { data } = await api.get<SuccessEnvelope<CategoryPage>>(
    `${BASE}/${encodeURIComponent(slug)}`,
  );
  return data.data;
}

export async function getCategoryStats(slug: string): Promise<CategoryStats> {
  const { data } = await api.get<SuccessEnvelope<CategoryStats>>(
    `${BASE}/${encodeURIComponent(slug)}/stats`,
  );
  return data.data;
}

export async function getCategoryFields(
  slug: string,
): Promise<CategoryField[]> {
  const { data } = await api.get<SuccessEnvelope<CategoryField[]>>(
    `${BASE}/${encodeURIComponent(slug)}/fields`,
  );
  return data.data;
}
