import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  PHOTO_MAX_BYTES,
  PHOTO_MAX_EDGE,
  PHOTO_MAX_INPUT_BYTES,
  PhotoProcessingError,
  browserPhotoCodec,
  checkPhotoInput,
  fitWithin,
  preparePhoto,
  qualitySteps,
  type DecodedPhoto,
  type PhotoCodec,
  type Size,
} from './prepare-photo';

const KB = 1024;

/** A codec double: records what was drawn and returns blobs of the given sizes per quality. */
function fakeCodec(decoded: Size, bytesAtQuality: (quality: number) => number) {
  const release = vi.fn();
  const surfaceRelease = vi.fn();
  const drawn: Size[] = [];
  const qualities: number[] = [];
  const codec: PhotoCodec = {
    decode: vi.fn(async (): Promise<DecodedPhoto> => ({ ...decoded, source: {} as CanvasImageSource, release })),
    draw: vi.fn((_photo, size) => {
      drawn.push(size);
      return {
        encode: async (quality: number) => {
          qualities.push(quality);
          return new Blob([new Uint8Array(bytesAtQuality(quality))], { type: 'image/jpeg' });
        },
        release: surfaceRelease,
      };
    }),
  };
  return { codec, drawn, qualities, release, surfaceRelease };
}

describe('fitWithin', () => {
  it('shrinks a 12 MP landscape photo to a 2048 px long side', () => {
    expect(fitWithin({ width: 4032, height: 3024 })).toEqual({ width: 2048, height: 1536 });
  });

  it('shrinks a portrait photo by its height', () => {
    expect(fitWithin({ width: 3024, height: 4032 })).toEqual({ width: 1536, height: 2048 });
  });

  it('never scales a small photo up', () => {
    expect(fitWithin({ width: 800, height: 600 })).toEqual({ width: 800, height: 600 });
    expect(fitWithin({ width: PHOTO_MAX_EDGE, height: 10 })).toEqual({ width: PHOTO_MAX_EDGE, height: 10 });
  });

  it('keeps at least one pixel on a very thin panorama', () => {
    expect(fitWithin({ width: 40_000, height: 10 })).toEqual({ width: 2048, height: 1 });
  });
});

describe('qualitySteps', () => {
  it('starts at 0.8 and steps down to 0.5', () => {
    expect(qualitySteps()).toEqual([0.8, 0.7, 0.6, 0.5]);
  });
});

describe('checkPhotoInput', () => {
  it('accepts JPEG, PNG and WebP up to the input limit', () => {
    expect(checkPhotoInput({ type: 'image/jpeg', size: 5 * KB })).toBeNull();
    expect(checkPhotoInput({ type: 'image/png', size: PHOTO_MAX_INPUT_BYTES })).toBeNull();
    expect(checkPhotoInput({ type: 'image/webp', size: 1 })).toBeNull();
  });

  it('rejects other types and oversized originals', () => {
    expect(checkPhotoInput({ type: 'image/gif', size: 1 })).toBe('type');
    expect(checkPhotoInput({ type: '', size: 1 })).toBe('type');
    expect(checkPhotoInput({ type: 'image/jpeg', size: PHOTO_MAX_INPUT_BYTES + 1 })).toBe('size');
  });
});

describe('preparePhoto', () => {
  it('draws a 12 MP photo at 2048 px and encodes it once at 0.8 when it fits', async () => {
    const { codec, drawn, qualities, release, surfaceRelease } = fakeCodec({ width: 4032, height: 3024 }, () => 600 * KB);

    const photo = await preparePhoto(new Blob(['raw']), codec);

    expect(drawn).toEqual([{ width: 2048, height: 1536 }]);
    expect(qualities).toEqual([0.8]);
    expect(photo).toMatchObject({ width: 2048, height: 1536 });
    expect(photo.blob.type).toBe('image/jpeg');
    expect(photo.blob.size).toBeLessThan(PHOTO_MAX_BYTES);
    expect(release).toHaveBeenCalledOnce();
    expect(surfaceRelease).toHaveBeenCalledOnce();
  });

  it('re-encodes small photos too, so their metadata is dropped', async () => {
    const { codec, drawn, qualities } = fakeCodec({ width: 640, height: 480 }, () => 80 * KB);

    const photo = await preparePhoto(new Blob(['raw']), codec);

    expect(drawn).toEqual([{ width: 640, height: 480 }]);
    expect(qualities).toEqual([0.8]);
    expect(photo.blob.size).toBe(80 * KB);
  });

  it('follows the decoded orientation: a rotated portrait stays portrait', async () => {
    // The decoder hands back the image already rotated by its EXIF tag.
    const { codec, drawn } = fakeCodec({ width: 3000, height: 4000 }, () => 500 * KB);

    const photo = await preparePhoto(new Blob(['raw']), codec);

    expect(drawn).toEqual([{ width: 1536, height: 2048 }]);
    expect(photo).toMatchObject({ width: 1536, height: 2048 });
  });

  it('steps the quality down until the photo is under 1 MB', async () => {
    const sizes: Record<number, number> = { 0.8: 1400 * KB, 0.7: 1100 * KB, 0.6: 900 * KB, 0.5: 700 * KB };
    const { codec, qualities } = fakeCodec({ width: 4000, height: 4000 }, (quality) => sizes[quality]);

    const photo = await preparePhoto(new Blob(['raw']), codec);

    expect(qualities).toEqual([0.8, 0.7, 0.6]);
    expect(photo.blob.size).toBe(900 * KB);
  });

  it('keeps the smallest attempt when even the lowest quality is over the limit', async () => {
    const { codec, qualities } = fakeCodec({ width: 2048, height: 2048 }, (quality) => Math.round(quality * 2200) * KB);

    const photo = await preparePhoto(new Blob(['raw']), codec);

    expect(qualities).toEqual([0.8, 0.7, 0.6, 0.5]);
    expect(photo.blob.size).toBe(1100 * KB);
  });

  it('fails with a PhotoProcessingError instead of passing the original through', async () => {
    const codec: PhotoCodec = {
      decode: vi.fn(async () => {
        throw new Error('not an image');
      }),
      draw: vi.fn(),
    };

    await expect(preparePhoto(new Blob(['broken']), codec)).rejects.toBeInstanceOf(PhotoProcessingError);
    expect(codec.draw).not.toHaveBeenCalled();
  });

  it('releases the decoded image when encoding fails', async () => {
    const release = vi.fn();
    const codec: PhotoCodec = {
      decode: async () => ({ width: 10, height: 10, source: {} as CanvasImageSource, release }),
      draw: () => ({
        encode: async () => {
          throw new Error('encoder crashed');
        },
        release: vi.fn(),
      }),
    };

    await expect(preparePhoto(new Blob(['raw']), codec)).rejects.toBeInstanceOf(PhotoProcessingError);
    expect(release).toHaveBeenCalledOnce();
  });
});

describe('browserPhotoCodec.decode', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('asks the browser to apply the EXIF orientation while decoding', async () => {
    const close = vi.fn();
    const createImageBitmap = vi.fn(async () => ({ width: 3024, height: 4032, close }));
    vi.stubGlobal('createImageBitmap', createImageBitmap);
    const file = new Blob(['raw'], { type: 'image/jpeg' });

    const photo = await browserPhotoCodec.decode(file);

    expect(createImageBitmap).toHaveBeenCalledWith(file, { imageOrientation: 'from-image' });
    expect(photo).toMatchObject({ width: 3024, height: 4032 });
    photo.release();
    expect(close).toHaveBeenCalledOnce();
  });
});
