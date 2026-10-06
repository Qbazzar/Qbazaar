import { describe, expect, it, vi } from 'vitest';

import { ApiClientError } from '@/lib/api/auth';
import type { Media } from '@/lib/api/types';

import {
  BUSY_WAIT_MS,
  MAX_UPLOAD_ATTEMPTS,
  RATE_LIMIT_WAIT_MS,
  createPhotoQueue,
  uploadRetryDelay,
  type PhotoQueueDeps,
  type UploadOptions,
} from './photo-queue';
import type { PhotoItem } from './photos';

function media(id: number): Media {
  return {
    id: id as unknown as string,
    collection: 'images',
    url: `https://api.test/media/${id}/original`,
    sizes: { thumbnail: `https://cdn.test/${id}-thumb.jpg`, medium: '', large: '', original_webp: '' },
    blurhash: null,
    width: null,
    height: null,
    order: id,
    size_bytes: 1000,
  };
}

function jpeg(name: string, size = 3000): File {
  return new File([new Uint8Array(size)], name, { type: 'image/jpeg' });
}

function apiError(status: number, code: string) {
  return new ApiClientError({ status, code, messageKey: code, message: code });
}

/** A queue wired to an in-memory photo list and fake API calls. */
function setup(overrides: Partial<PhotoQueueDeps> = {}) {
  let photos: PhotoItem[] = [];
  let nextMediaId = 100;
  let keySeed = 0;
  const uploads: Array<{ adId: string; size: number }> = [];
  const deps: PhotoQueueDeps = {
    getPhotos: () => photos,
    setPhotos: (update) => {
      photos = update(photos);
    },
    prepare: vi.fn(async (file: Blob) => ({ blob: new Blob([new Uint8Array(Math.ceil(file.size / 10))]), width: 2048, height: 1536 })),
    upload: vi.fn(async (adId: string, file: Blob, { onProgress }: UploadOptions) => {
      uploads.push({ adId, size: file.size });
      onProgress(50);
      onProgress(100);
      nextMediaId += 1;
      return media(nextMediaId);
    }),
    reorder: vi.fn(async () => undefined),
    removeMedia: vi.fn(async () => undefined),
    createObjectUrl: vi.fn((blob: Blob) => `blob:${blob.size}-${Math.random()}`),
    revokeObjectUrl: vi.fn(),
    createKey: () => `k${(keySeed += 1)}`,
    wait: vi.fn(async () => undefined),
    ...overrides,
  };
  const queue = createPhotoQueue(deps);
  return { queue, deps, uploads, photos: () => photos };
}

describe('uploadRetryDelay', () => {
  it('waits out the rate limit longer on each attempt', () => {
    expect(uploadRetryDelay(apiError(429, 'RATE_LIMIT_EXCEEDED'), 1)).toBe(RATE_LIMIT_WAIT_MS);
    expect(uploadRetryDelay(apiError(429, 'RATE_LIMIT_EXCEEDED'), 2)).toBe(RATE_LIMIT_WAIT_MS * 2);
  });

  it('retries quickly while another upload to the ad is running', () => {
    expect(uploadRetryDelay(apiError(409, 'REQUEST_IN_PROGRESS'), 1)).toBe(BUSY_WAIT_MS);
  });

  it('gives up on other errors and after the last attempt', () => {
    expect(uploadRetryDelay(apiError(422, 'UPLOAD_002'), 1)).toBeNull();
    expect(uploadRetryDelay(new Error('boom'), 1)).toBeNull();
    expect(uploadRetryDelay(apiError(429, 'RATE_LIMIT_EXCEEDED'), MAX_UPLOAD_ATTEMPTS)).toBeNull();
  });
});

describe('createPhotoQueue', () => {
  it('resizes picked photos and keeps them until the ad exists', async () => {
    const { queue, deps, photos } = setup();

    const result = queue.add([jpeg('a.jpg'), jpeg('b.jpg')], 20);
    await queue.settle();

    expect(result).toEqual({ added: 2, rejected: [] });
    expect(deps.prepare).toHaveBeenCalledTimes(2);
    expect(deps.upload).not.toHaveBeenCalled();
    expect(photos().map((photo) => photo.status)).toEqual(['ready', 'ready']);
    // The original's preview URL is swapped for the resized JPEG's.
    expect(deps.revokeObjectUrl).toHaveBeenCalledTimes(2);
  });

  it('uploads one photo per request once attached, in order, with progress', async () => {
    const { queue, uploads, photos } = setup();
    queue.add([jpeg('a.jpg', 3000), jpeg('b.jpg', 5000)], 20);

    queue.attach('ad-1');
    await queue.settle();

    expect(uploads).toEqual([
      { adId: 'ad-1', size: 300 },
      { adId: 'ad-1', size: 500 },
    ]);
    expect(photos().map((photo) => [photo.status, photo.progress, photo.media?.id])).toEqual([
      ['uploaded', 100, 101],
      ['uploaded', 100, 102],
    ]);
  });

  it('rejects unsupported, oversized and surplus files', () => {
    const { queue } = setup();
    const big = new File([new Uint8Array(13 * 1024 * 1024)], 'big.jpg', { type: 'image/jpeg' });

    const result = queue.add([jpeg('1.jpg'), new File(['x'], 'doc.pdf', { type: 'application/pdf' }), big, jpeg('2.jpg'), jpeg('3.jpg')], 2);

    expect(result.added).toBe(2);
    expect(result.rejected).toEqual([
      { name: 'doc.pdf', problem: 'type' },
      { name: 'big.jpg', problem: 'size' },
      { name: '3.jpg', problem: 'limit' },
    ]);
  });

  it('marks a photo the browser cannot read as failed, without uploading it', async () => {
    const { queue, deps, photos } = setup({
      prepare: vi.fn(async () => {
        throw new Error('decode');
      }),
    });

    queue.add([jpeg('broken.jpg')], 20);
    queue.attach('ad-1');
    await queue.settle();

    expect(photos()[0]).toMatchObject({ status: 'failed', failure: 'process' });
    expect(deps.upload).not.toHaveBeenCalled();
  });

  it('retries a rate-limited upload after waiting', async () => {
    const upload = vi
      .fn()
      .mockRejectedValueOnce(apiError(429, 'RATE_LIMIT_EXCEEDED'))
      .mockResolvedValueOnce(media(7));
    const { queue, deps, photos } = setup({ upload });

    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await queue.settle();

    expect(upload).toHaveBeenCalledTimes(2);
    expect(deps.wait).toHaveBeenCalledWith(RATE_LIMIT_WAIT_MS, expect.any(AbortSignal));
    expect(photos()[0]).toMatchObject({ status: 'uploaded' });
  });

  it('keeps a refused upload as failed with its error code, and retries it on request', async () => {
    const upload = vi.fn().mockRejectedValueOnce(apiError(422, 'UPLOAD_004')).mockResolvedValueOnce(media(9));
    const { queue, photos } = setup({ upload });
    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await queue.settle();

    expect(photos()[0]).toMatchObject({ status: 'failed', failure: 'upload', errorCode: 'UPLOAD_004' });

    queue.retry(photos()[0].key);
    await queue.settle();

    expect(photos()[0]).toMatchObject({ status: 'uploaded', errorCode: undefined });
  });

  it('uploads in the on-screen order, so a cover picked before saving needs no reorder', async () => {
    const { queue, deps, photos } = setup();
    queue.add([jpeg('a.jpg', 1000), jpeg('b.jpg', 2000)], 20);
    await queue.settle();
    queue.makeCover(photos()[1].key);

    queue.attach('ad-1');
    await queue.settle();

    expect(vi.mocked(deps.upload).mock.calls.map(([, file]) => file.size)).toEqual([200, 100]);
    expect(deps.reorder).not.toHaveBeenCalled();
  });

  it('saves the order once the batch is done when the cover changed during uploads', async () => {
    let finishFirst: (value: Media) => void = () => undefined;
    const upload = vi
      .fn()
      .mockImplementationOnce(() => new Promise<Media>((resolve) => (finishFirst = resolve)))
      .mockResolvedValueOnce(media(102));
    const { queue, deps, photos } = setup({ upload });
    queue.add([jpeg('a.jpg'), jpeg('b.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalledTimes(1));

    queue.makeCover(photos()[1].key);
    expect(deps.reorder).not.toHaveBeenCalled();
    finishFirst(media(101));
    await queue.settle();

    // The server appended a (101) then b (102); the seller wants b first.
    expect(deps.reorder).toHaveBeenCalledTimes(1);
    expect(deps.reorder).toHaveBeenCalledWith('ad-1', [102, 101]);
  });

  it('reorders existing photos right away when nothing is uploading', async () => {
    const { queue, deps, photos } = setup();
    queue.reset([media(1), media(2), media(3)], 'ad-9');

    queue.move(2, 0);
    await queue.settle();

    expect(photos().map((photo) => photo.media?.id)).toEqual([3, 1, 2]);
    expect(deps.reorder).toHaveBeenCalledWith('ad-9', [3, 1, 2]);
  });

  it('deletes an uploaded photo on the server and puts it back if that fails', async () => {
    const removeMedia = vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(apiError(500, 'SERVER_ERROR'));
    const { queue, photos } = setup({ removeMedia });
    queue.reset([media(1), media(2), media(3)], 'ad-9');

    expect(await queue.remove('media-2')).toBe(true);
    expect(photos().map((photo) => photo.key)).toEqual(['media-1', 'media-3']);

    expect(await queue.remove('media-1')).toBe(false);
    expect(photos().map((photo) => photo.key)).toEqual(['media-1', 'media-3']);
    expect(removeMedia).toHaveBeenNthCalledWith(1, 2);
  });

  it('removes a photo that finished uploading after the seller removed it', async () => {
    let finish: (value: Media) => void = () => undefined;
    const upload = vi.fn(() => new Promise<Media>((resolve) => (finish = resolve)));
    const { queue, deps, photos } = setup({ upload });
    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalled());

    const key = photos()[0].key;
    const removed = queue.remove(key);
    finish(media(55));
    await removed;
    await queue.settle();

    expect(photos()).toEqual([]);
    expect(deps.removeMedia).toHaveBeenCalledWith(55);
  });

  it('cancels the upload of a removed photo while its bytes are still going out', async () => {
    let signal: AbortSignal | undefined;
    const upload = vi.fn((_adId: string, _file: Blob, options: UploadOptions) => {
      signal = options.signal;
      options.onProgress(40);
      return new Promise<Media>((_resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('aborted'))));
    });
    const { queue, deps, photos } = setup({ upload });
    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalled());

    await queue.remove(photos()[0].key);
    await queue.settle();

    expect(signal?.aborted).toBe(true);
    expect(photos()).toEqual([]);
    expect(deps.removeMedia).not.toHaveBeenCalled();
  });

  it('lets a fully sent upload finish when its photo is removed, then deletes it', async () => {
    let signal: AbortSignal | undefined;
    let finish: (value: Media) => void = () => undefined;
    const upload = vi.fn((_adId: string, _file: Blob, options: UploadOptions) => {
      signal = options.signal;
      options.onProgress(100);
      return new Promise<Media>((resolve) => (finish = resolve));
    });
    const { queue, deps, photos } = setup({ upload });
    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalled());

    await queue.remove(photos()[0].key);
    expect(signal?.aborted).toBe(false);
    finish(media(56));
    await queue.settle();

    expect(photos()).toEqual([]);
    expect(deps.removeMedia).toHaveBeenCalledWith(56);
  });

  it('saves a cover picked while the batch was sending its own reorder', async () => {
    let finishFirst: (value: Media) => void = () => undefined;
    let finishReorder: () => void = () => undefined;
    const upload = vi
      .fn()
      .mockImplementationOnce(() => new Promise<Media>((resolve) => (finishFirst = resolve)))
      .mockResolvedValueOnce(media(102))
      .mockResolvedValueOnce(media(103));
    const reorder = vi
      .fn()
      .mockImplementationOnce(() => new Promise<void>((resolve) => (finishReorder = resolve)))
      .mockResolvedValue(undefined);
    const { queue, photos } = setup({ upload, reorder });
    queue.add([jpeg('a.jpg'), jpeg('b.jpg'), jpeg('c.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalledTimes(1));

    queue.makeCover(photos()[1].key);
    finishFirst(media(101));
    await vi.waitFor(() => expect(reorder).toHaveBeenCalledTimes(1));
    queue.makeCover(photos()[2].key);
    finishReorder();
    await queue.settle();

    // a (101), b (102), c (103) went up in turn; b became the cover, then c.
    expect(reorder.mock.calls).toEqual([
      ['ad-1', [102, 101, 103]],
      ['ad-1', [103, 102, 101]],
    ]);
  });

  it('starts over on reset and lets late results of the old form go', async () => {
    let finish: (value: Media) => void = () => undefined;
    const upload = vi.fn(() => new Promise<Media>((resolve) => (finish = resolve)));
    const { queue, deps, photos } = setup({ upload });
    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await vi.waitFor(() => expect(upload).toHaveBeenCalled());

    queue.reset();
    finish(media(77));
    await queue.settle();

    expect(photos()).toEqual([]);
    expect(deps.removeMedia).not.toHaveBeenCalled();
  });

  it('tells listeners when the ad images changed', async () => {
    const { queue } = setup();
    const listener = vi.fn();
    const unsubscribe = queue.onImagesChanged(listener);

    queue.add([jpeg('a.jpg')], 20);
    queue.attach('ad-1');
    await queue.settle();
    await vi.waitFor(() => expect(listener).toHaveBeenCalledWith('ad-1'));

    unsubscribe();
    queue.add([jpeg('b.jpg')], 20);
    await queue.settle();
    expect(listener).toHaveBeenCalledTimes(1);
  });
});
