import { beforeEach, describe, expect, it, vi } from 'vitest';

// Mock the shared axios instance so we control the envelope shape.
vi.mock('@/lib/api/client', () => ({ api: { post: vi.fn() } }));

import { api } from '@/lib/api/client';
import { reorderAdImages, uploadAdImages } from '@/lib/api/ad-images';

const mockPost = vi.mocked(api.post);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('uploadAdImages', () => {
  it('unwraps the nested data.images array (regression: was returning the object)', async () => {
    mockPost.mockResolvedValue({
      data: { success: true, data: { images: [{ id: 'm1' }, { id: 'm2' }] } },
    } as never);

    const result = await uploadAdImages('ad-1', [new Blob(['x'])]);

    // Must be a spreadable array — the dropzone does `[...images, ...uploaded]`.
    expect(Array.isArray(result)).toBe(true);
    expect(result).toHaveLength(2);
  });

  it('returns an empty array (not undefined) when the payload is malformed', async () => {
    mockPost.mockResolvedValue({
      data: { success: true, data: {} },
    } as never);

    const result = await uploadAdImages('ad-1', [new Blob(['x'])]);

    expect(result).toEqual([]);
  });

  it('short-circuits to an empty array without calling the API for no files', async () => {
    const result = await uploadAdImages('ad-1', []);

    expect(result).toEqual([]);
    expect(mockPost).not.toHaveBeenCalled();
  });
});

describe('reorderAdImages', () => {
  it('sends the ids under the `order` key (regression: was `media_ids`)', async () => {
    mockPost.mockResolvedValue({ data: {} } as never);

    await reorderAdImages('ad-1', ['3', '1', '2']);

    expect(mockPost).toHaveBeenCalledWith(
      '/api/v1/ads/ad-1/images/reorder',
      { order: ['3', '1', '2'] },
    );
  });
});
