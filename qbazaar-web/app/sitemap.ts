import type { MetadataRoute } from 'next';

import { fetchApiData, siteUrl } from '@/lib/seo';

/** Public, crawlable static routes. */
const STATIC_PATHS = ['', '/ads', '/categories', '/search', '/help', '/support'];

type CategoryNode = { slug?: string; children?: CategoryNode[] };
type AdRow = { id: string; updated_at?: string; published_at?: string };

function flattenCategorySlugs(nodes: CategoryNode[] | null): string[] {
  if (!Array.isArray(nodes)) return [];

  const slugs: string[] = [];
  for (const node of nodes) {
    if (node?.slug) slugs.push(node.slug);
    if (Array.isArray(node?.children)) {
      slugs.push(...flattenCategorySlugs(node.children));
    }
  }

  return slugs;
}

/**
 * Dynamic sitemap: static routes + every category + the most recent ads.
 *
 * The public `/ads` feed is capped at one page, so only recent ads are listed
 * here — a complete ad sitemap needs a dedicated lightweight backend endpoint
 * (`/sitemap/ads`) which isn't built yet. Each API call degrades to "skip"
 * rather than failing the whole sitemap.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = siteUrl();
  const now = new Date();

  const entries: MetadataRoute.Sitemap = STATIC_PATHS.map((path) => ({
    url: `${base}${path}`,
    lastModified: now,
    changeFrequency: 'daily',
    priority: path === '' ? 1 : 0.7,
  }));

  const categories = await fetchApiData<CategoryNode[]>('/api/v1/categories');
  for (const slug of flattenCategorySlugs(categories)) {
    entries.push({
      url: `${base}/c/${slug}`,
      lastModified: now,
      changeFrequency: 'daily',
      priority: 0.6,
    });
  }

  const ads = await fetchApiData<AdRow[]>('/api/v1/ads', 900);
  if (Array.isArray(ads)) {
    for (const ad of ads) {
      if (!ad?.id) continue;
      const stamp = ad.updated_at ?? ad.published_at;
      entries.push({
        url: `${base}/ads/${ad.id}`,
        lastModified: stamp ? new Date(stamp) : now,
        changeFrequency: 'weekly',
        priority: 0.5,
      });
    }
  }

  return entries;
}
