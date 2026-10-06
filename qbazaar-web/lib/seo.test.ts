// @vitest-environment node
import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchApiData, fetchApiPage } from './seo';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('fetchApiData', () => {
  it('unwraps the data envelope', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(Response.json({ success: true, data: [{ slug: 'vehicles' }] })),
    );

    await expect(fetchApiData('/api/v1/categories/tree')).resolves.toEqual([{ slug: 'vehicles' }]);
  });

  it('returns null for an error response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(Response.json({ success: false }, { status: 404 })));

    await expect(fetchApiData('/api/v1/ads')).resolves.toBeNull();
  });

  it('gives up on an API that never answers', async () => {
    const timeout = new AbortController();
    vi.spyOn(AbortSignal, 'timeout').mockReturnValue(timeout.signal);
    vi.stubGlobal(
      'fetch',
      vi.fn(
        (_url: string, init?: RequestInit) =>
          new Promise<Response>((_resolve, reject) => {
            init?.signal?.addEventListener('abort', () => reject(init.signal?.reason));
          }),
      ),
    );

    const pending = fetchApiData('/api/v1/ads');
    timeout.abort(new DOMException('The operation timed out.', 'TimeoutError'));

    await expect(pending).resolves.toBeNull();
  });
});

describe('fetchApiPage', () => {
  it('keeps the rows together with their meta', async () => {
    const meta = { current_page: 2, per_page: 20, total: 41, last_page: 3 };
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(Response.json({ success: true, data: [{ id: 'a' }], meta })));

    await expect(fetchApiPage('/api/v1/companies?page=2')).resolves.toEqual({ success: true, data: [{ id: 'a' }], meta });
  });

  it('returns null for a body that is not a page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(Response.json({ success: true, data: { id: 'a' } })));

    await expect(fetchApiPage('/api/v1/companies')).resolves.toBeNull();
  });
});
