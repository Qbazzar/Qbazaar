/**
 * Client-side image compression for ad uploads.
 *
 * The dropzone calls this before each upload so the user gets a snappy
 * progress bar and the backend gets sane payload sizes. We:
 *
 *   1. Read the file into an HTMLImageElement.
 *   2. Downscale to a max edge of 1920px while preserving aspect ratio.
 *   3. Re-encode as JPEG at quality 0.85 (great visual fidelity, ~5-10x
 *      smaller than typical phone photos).
 *
 * Files that already look "web-ready" (≤ 800 KB) are returned untouched —
 * compressing them again only adds CPU work and never helps.
 */

const MAX_EDGE = 1920;
const QUALITY = 0.85;
const SKIP_BYTES = 800 * 1024;

export interface CompressedImage {
  blob: Blob;
  width: number;
  height: number;
  mimeType: string;
  originalSize: number;
  compressedSize: number;
}

/**
 * Reads + downscales + re-encodes a user-selected image. Falls back to the
 * raw file on any failure (decoded image, OOM, OffscreenCanvas refused) so a
 * single weird photo never blocks the user's whole upload.
 */
export async function compressImage(file: File): Promise<CompressedImage> {
  // Fast path: already small enough.
  if (file.size <= SKIP_BYTES) {
    const { width, height } = await tryReadDimensions(file);
    return {
      blob: file,
      width,
      height,
      mimeType: file.type || 'image/jpeg',
      originalSize: file.size,
      compressedSize: file.size,
    };
  }

  try {
    const bitmap = await loadBitmap(file);
    const { width: targetW, height: targetH } = fitTo(
      bitmap.width,
      bitmap.height,
      MAX_EDGE,
    );

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas 2D context unavailable');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bitmap, 0, 0, targetW, targetH);

    const blob = await new Promise<Blob | null>((resolve) =>
      canvas.toBlob(resolve, 'image/jpeg', QUALITY),
    );
    if (!blob) throw new Error('Canvas toBlob returned null');

    // Release the bitmap as soon as we're done with it.
    if ('close' in bitmap && typeof bitmap.close === 'function') {
      bitmap.close();
    }

    return {
      blob,
      width: targetW,
      height: targetH,
      mimeType: 'image/jpeg',
      originalSize: file.size,
      compressedSize: blob.size,
    };
  } catch {
    // Whatever failed (decoding, canvas, encoder), shipping the raw file is
    // still a perfectly valid outcome — the backend re-encodes anyway.
    const { width, height } = await tryReadDimensions(file).catch(() => ({
      width: 0,
      height: 0,
    }));
    return {
      blob: file,
      width,
      height,
      mimeType: file.type || 'application/octet-stream',
      originalSize: file.size,
      compressedSize: file.size,
    };
  }
}

async function loadBitmap(file: File): Promise<ImageBitmap | HTMLImageElement> {
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file);
  }
  // Old Safari fallback.
  return loadHtmlImage(file);
}

function loadHtmlImage(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = (err) => {
      URL.revokeObjectURL(url);
      reject(err);
    };
    img.src = url;
  });
}

async function tryReadDimensions(
  file: File,
): Promise<{ width: number; height: number }> {
  try {
    const bitmap = await loadBitmap(file);
    const dims = { width: bitmap.width, height: bitmap.height };
    if ('close' in bitmap && typeof bitmap.close === 'function') bitmap.close();
    return dims;
  } catch {
    return { width: 0, height: 0 };
  }
}

function fitTo(
  width: number,
  height: number,
  maxEdge: number,
): { width: number; height: number } {
  if (width <= maxEdge && height <= maxEdge) return { width, height };
  if (width >= height) {
    const ratio = maxEdge / width;
    return { width: maxEdge, height: Math.round(height * ratio) };
  }
  const ratio = maxEdge / height;
  return { width: Math.round(width * ratio), height: maxEdge };
}
