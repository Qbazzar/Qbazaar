import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchPublicResource } from './public-resource';

const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

function respond(status: number, body: unknown = {}) {
  fetchMock.mockResolvedValue(new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } }));
}

afterEach(() => fetchMock.mockReset());

describe('fetchPublicResource', () => {
  it('unwraps the success envelope and caches the read', async () => {
    respond(200, { success: true, data: { slug: 'terms' } });

    await expect(fetchPublicResource('/api/v1/pages/terms', 600)).resolves.toEqual({ slug: 'terms' });
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringMatching(/\/api\/v1\/pages\/terms$/),
      expect.objectContaining({ next: { revalidate: 600 } }),
    );
  });

  it('resolves to null when the record does not exist', async () => {
    respond(404, { success: false, error: { code: 'CMS_001' } });

    await expect(fetchPublicResource('/api/v1/pages/missing')).resolves.toBeNull();
  });

  it('throws on any other failure instead of reporting a missing record', async () => {
    respond(503);

    await expect(fetchPublicResource('/api/v1/pages/terms')).rejects.toThrow('GET /api/v1/pages/terms failed with status 503');
  });
});
