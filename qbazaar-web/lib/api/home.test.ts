import { describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/client', () => ({ api: { get: vi.fn() } }));

import { api } from '@/lib/api/client';
import { getHomeFeed } from '@/lib/api/home';

describe('getHomeFeed', () => {
  it('asks for the page language and unwraps the envelope', async () => {
    const feed = { categories: [], recommended: [], featured_sellers: [], best_selling: [] };
    vi.mocked(api.get).mockResolvedValue({ data: { success: true, data: feed } } as never);

    await expect(getHomeFeed('ar')).resolves.toBe(feed);
    expect(api.get).toHaveBeenCalledWith('/api/v1/home', { params: { lang: 'ar' } });
  });
});
