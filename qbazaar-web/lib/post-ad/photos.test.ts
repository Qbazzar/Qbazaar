import { describe, expect, it } from 'vitest';

import type { Media } from '@/lib/api/types';

import { hasPendingPhotos, makeCover, movePhoto, photoDisplayUrl, photoFromMedia, sameOrder, uploadedMediaIds, type PhotoItem } from './photos';

const media = (id: number, sizes: Partial<Media['sizes']> = {}): Media => ({
  id: String(id),
  collection: 'images',
  url: `https://api.test/media/${id}/original`,
  sizes: { thumbnail: `https://cdn.test/${id}-t.jpg`, medium: `https://cdn.test/${id}-m.jpg`, large: `https://cdn.test/${id}-l.jpg`, original_webp: '', ...sizes },
  blurhash: null,
  width: null,
  height: null,
  order: id,
  size_bytes: 1,
});

const local = (key: string, status: PhotoItem['status']): PhotoItem => ({ key, status, previewUrl: `blob:${key}`, ownsPreviewUrl: true, progress: 0 });

describe('photos', () => {
  it('builds tiles from server media with the thumbnail as preview', () => {
    expect(photoFromMedia(media(4))).toMatchObject({ key: 'media-4', status: 'uploaded', previewUrl: 'https://cdn.test/4-t.jpg', ownsPreviewUrl: false });
  });

  it('shows the local JPEG in the preview until the server has its sizes', () => {
    expect(photoDisplayUrl(local('a', 'uploading'))).toBe('blob:a');
    expect(photoDisplayUrl(photoFromMedia(media(4)))).toBe('https://cdn.test/4-l.jpg');
    expect(photoDisplayUrl(photoFromMedia(media(5, { large: '' })))).toBe('https://api.test/media/5/original');
  });

  it('moves photos and makes any photo the cover', () => {
    const list = [local('a', 'ready'), local('b', 'ready'), local('c', 'ready')];
    expect(movePhoto(list, 0, 2).map((p) => p.key)).toEqual(['b', 'c', 'a']);
    expect(movePhoto(list, 0, 5)).toBe(list);
    expect(makeCover(list, 'c').map((p) => p.key)).toEqual(['c', 'a', 'b']);
    expect(makeCover(list, 'missing')).toBe(list);
  });

  it('lists uploaded media ids in screen order and spots pending work', () => {
    const list = [photoFromMedia(media(2)), local('x', 'uploading'), photoFromMedia(media(1))];
    expect(uploadedMediaIds(list)).toEqual(['2', '1']);
    expect(hasPendingPhotos(list)).toBe(true);
    expect(hasPendingPhotos([photoFromMedia(media(1)), local('f', 'failed')])).toBe(false);
  });

  it('compares orders', () => {
    expect(sameOrder([1, 2], [1, 2])).toBe(true);
    expect(sameOrder([1, 2], [2, 1])).toBe(false);
    expect(sameOrder([1], [1, 2])).toBe(false);
  });
});
