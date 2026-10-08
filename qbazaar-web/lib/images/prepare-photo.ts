/**
 * Ad photos are prepared in the browser before upload (FE-16.11): decoded
 * with their EXIF orientation applied, scaled so the long side is at most
 * 2048 px and re-encoded as JPEG. Re-encoding through a canvas writes a fresh
 * file, so camera data and GPS coordinates never leave the device.
 */

export const PHOTO_MAX_EDGE = 2048;
export const PHOTO_QUALITY = 0.8;
/** The quality steps down until a photo fits; a 12 MP photo lands far below. */
export const PHOTO_MAX_BYTES = 1024 * 1024;
export const PHOTO_OUTPUT_TYPE = 'image/jpeg';

/** What the picker accepts; anything else is rejected before decoding. */
export const PHOTO_ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;
/** Largest original we try to decode (the limit the design states). */
export const PHOTO_MAX_INPUT_BYTES = 12 * 1024 * 1024;

const QUALITY_FLOOR = 0.5;
const QUALITY_STEP = 0.1;
// JPEG has no alpha channel: transparent PNG areas would otherwise turn black.
const JPEG_BACKGROUND = '#ffffff';

export interface Size {
  width: number;
  height: number;
}

export interface PreparedPhoto extends Size {
  blob: Blob;
}

/** A decoded, correctly oriented image. */
export interface DecodedPhoto extends Size {
  source: CanvasImageSource;
  release: () => void;
}

/** A drawn canvas that can be encoded at several qualities. */
export interface PhotoSurface {
  encode: (quality: number) => Promise<Blob>;
  release: () => void;
}

export interface PhotoCodec {
  decode: (file: Blob) => Promise<DecodedPhoto>;
  draw: (photo: DecodedPhoto, size: Size) => PhotoSurface;
}

export class PhotoProcessingError extends Error {
  constructor(message: string, options?: { cause?: unknown }) {
    super(message, options);
    this.name = 'PhotoProcessingError';
  }
}

export type PhotoInputProblem = 'type' | 'size';

/** Why a picked file can't be used, or null when it can. */
export function checkPhotoInput(file: Pick<File, 'type' | 'size'>): PhotoInputProblem | null {
  if (!(PHOTO_ACCEPTED_TYPES as readonly string[]).includes(file.type)) return 'type';
  if (file.size > PHOTO_MAX_INPUT_BYTES) return 'size';
  return null;
}

/** Scales `size` down so its long side is at most `maxEdge`; never scales up. */
export function fitWithin(size: Size, maxEdge: number = PHOTO_MAX_EDGE): Size {
  const longest = Math.max(size.width, size.height);
  if (longest <= maxEdge) return { width: size.width, height: size.height };
  const scale = maxEdge / longest;
  return {
    width: Math.max(1, Math.round(size.width * scale)),
    height: Math.max(1, Math.round(size.height * scale)),
  };
}

/** JPEG qualities to try in order: 0.8, 0.7, 0.6, 0.5. */
export function qualitySteps(): number[] {
  const steps: number[] = [];
  for (let quality = PHOTO_QUALITY; quality >= QUALITY_FLOOR - 1e-9; quality -= QUALITY_STEP) {
    steps.push(Math.round(quality * 100) / 100);
  }
  return steps;
}

export async function preparePhoto(file: Blob, codec: PhotoCodec = browserPhotoCodec): Promise<PreparedPhoto> {
  let photo: DecodedPhoto;
  try {
    photo = await codec.decode(file);
  } catch (cause) {
    throw new PhotoProcessingError('The photo could not be decoded', { cause });
  }

  let surface: PhotoSurface | null = null;
  try {
    const size = fitWithin(photo);
    surface = codec.draw(photo, size);
    let blob: Blob | null = null;
    for (const quality of qualitySteps()) {
      blob = await surface.encode(quality);
      if (blob.size <= PHOTO_MAX_BYTES) break;
    }
    if (!blob) throw new Error('No JPEG quality left to try');
    return { blob, ...size };
  } catch (cause) {
    throw new PhotoProcessingError('The photo could not be encoded', { cause });
  } finally {
    surface?.release();
    photo.release();
  }
}

async function decodeWithBitmap(file: Blob): Promise<DecodedPhoto> {
  const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
  return { source: bitmap, width: bitmap.width, height: bitmap.height, release: () => bitmap.close() };
}

// Browsers without the `imageOrientation` option (older Safari) still apply
// EXIF orientation when an <img> is drawn onto a canvas.
async function decodeWithImageElement(file: Blob): Promise<DecodedPhoto> {
  const url = URL.createObjectURL(file);
  const image = new Image();
  image.decoding = 'async';
  image.src = url;
  try {
    await image.decode();
  } catch (error) {
    URL.revokeObjectURL(url);
    throw error;
  }
  return {
    source: image,
    width: image.naturalWidth,
    height: image.naturalHeight,
    release: () => URL.revokeObjectURL(url),
  };
}

export const browserPhotoCodec: PhotoCodec = {
  async decode(file) {
    if (typeof createImageBitmap === 'function') {
      try {
        return await decodeWithBitmap(file);
      } catch {
        // Fall through to the <img> decoder below.
      }
    }
    return decodeWithImageElement(file);
  },

  draw(photo, { width, height }) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('2D canvas is not available');
    context.fillStyle = JPEG_BACKGROUND;
    context.fillRect(0, 0, width, height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(photo.source, 0, 0, width, height);

    return {
      encode: (quality) =>
        new Promise<Blob>((resolve, reject) => {
          canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error('The canvas produced no image'))),
            PHOTO_OUTPUT_TYPE,
            quality,
          );
        }),
      // Zero-sized canvases hand their memory back at once (iOS keeps it otherwise).
      release: () => {
        canvas.width = 0;
        canvas.height = 0;
      },
    };
  },
};
