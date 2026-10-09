import { beforeEach, describe, expect, it, vi } from 'vitest';

const fetchApiData = vi.fn();

vi.mock('@/lib/seo', () => ({
  siteUrl: () => 'https://qbazaar.qa',
  fetchApiData: (path: string) => fetchApiData(path),
}));

import sitemap from './sitemap';

const tree = [
  { slug: 'vehicles', children: [{ slug: 'cars', children: [] }] },
  { slug: 'electronics', children: [] },
];

describe('sitemap', () => {
  beforeEach(() => {
    fetchApiData.mockReset();
    fetchApiData.mockImplementation(async (path: string) => {
      if (path === '/api/v1/categories/tree') return tree;
      if (path === '/api/v1/companies') return [{ id: '01co' }];
      if (path === '/api/v1/ads') return [{ id: '01ad', updated_at: '2026-10-01T10:00:00Z' }];
      return null;
    });
  });

  it('lists every category of the tree, children included', async () => {
    const urls = (await sitemap()).map((entry) => entry.url);

    expect(fetchApiData).toHaveBeenCalledWith('/api/v1/categories/tree');
    expect(urls).toEqual(
      expect.arrayContaining([
        'https://qbazaar.qa/c/vehicles',
        'https://qbazaar.qa/c/cars',
        'https://qbazaar.qa/c/electronics',
        'https://qbazaar.qa/ads/01ad',
        'https://qbazaar.qa/companies',
        'https://qbazaar.qa/u/01co',
      ]),
    );
  });

  it('keeps the static routes when the API is down', async () => {
    fetchApiData.mockResolvedValue(null);
    const urls = (await sitemap()).map((entry) => entry.url);

    expect(urls).toContain('https://qbazaar.qa/categories');
    expect(urls.some((url) => url.includes('/c/'))).toBe(false);
  });
});
