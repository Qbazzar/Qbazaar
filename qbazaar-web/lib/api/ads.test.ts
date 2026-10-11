import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/client', () => ({ api: { get: vi.fn() } }));

import { api } from '@/lib/api/client';
import { listAds } from '@/lib/api/ads';

describe('listAds', () => {
  beforeEach(() => vi.mocked(api.get).mockResolvedValue({ data: { data: [], meta: {} } } as never));

  it('serialises the catalog filters the way the API reads them', async () => {
    await listAds({
      category_id: 'c1',
      q: 'sedan',
      ad_type: 'wanted',
      seller_type: 'business',
      shipping: 'delivery',
      condition: 'used',
      buy_now: true,
      custom_fields: { year: { min: 2015, max: 2020 }, make: 'bmw' },
    });

    const [url, config] = vi.mocked(api.get).mock.lastCall!;
    expect(url).toBe('/api/v1/ads');
    expect(config?.params).toEqual({
      category_id: 'c1',
      q: 'sedan',
      ad_type: 'wanted',
      seller_type: 'business',
      shipping: 'delivery',
      condition: 'used',
      buy_now: true,
      'custom_fields[year][min]': 2015,
      'custom_fields[year][max]': 2020,
      'custom_fields[make]': 'bmw',
    });
  });

  it('leaves out what is not set', async () => {
    await listAds({ q: undefined, seller_type: undefined, custom_fields: {}, page: 2 });

    expect(vi.mocked(api.get).mock.lastCall![1]?.params).toEqual({ page: 2 });
  });
});
