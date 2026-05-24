/**
 * BlurHash helpers.
 *
 * The backend ships a `blurhash` string with every image. We:
 *
 *   - `blurhashToDataUrl()` — decode it into a tiny PNG data URL on the
 *     client so we can paint a placeholder behind <img/> before the real
 *     pixels arrive.
 *   - `blurhashToCss()` — convert it to a CSS linear-gradient fallback for
 *     environments where decoding fails (SSR, very old browsers, missing
 *     OffscreenCanvas, etc).
 *
 * Both helpers are pure — no React, no DOM apart from a transient canvas —
 * so they're safe to call from server components for the gradient fallback.
 */
import { decode } from 'blurhash';

const SIZE = 32;

/**
 * Decode a BlurHash into a data URL. Returns `null` on any failure so the
 * caller can fall back to the gradient (or skip the placeholder entirely).
 *
 * Browser-only — the function bails out gracefully on the server.
 */
export function blurhashToDataUrl(hash: string | null | undefined): string | null {
  if (!hash || typeof document === 'undefined') return null;
  try {
    const pixels = decode(hash, SIZE, SIZE);
    const canvas = document.createElement('canvas');
    canvas.width = SIZE;
    canvas.height = SIZE;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    const imageData = ctx.createImageData(SIZE, SIZE);
    imageData.data.set(pixels);
    ctx.putImageData(imageData, 0, 0);
    return canvas.toDataURL('image/png');
  } catch {
    return null;
  }
}

/**
 * Quick-and-dirty gradient placeholder derived from the BlurHash chars.
 *
 * BlurHash hides the dominant DC colour in the first 4 characters of the
 * string — that's enough to extract a base tone. We then mix two slightly
 * tinted variants to produce a soft diagonal gradient that hints at the
 * image's mood without actually decoding it. Pure CSS so it works during
 * SSR.
 */
export function blurhashToCss(hash: string | null | undefined): string {
  if (!hash || hash.length < 6) {
    return 'linear-gradient(135deg, #F1EBE2 0%, #E5DDD0 100%)';
  }
  const seed = hash.charCodeAt(2) + hash.charCodeAt(3) + hash.charCodeAt(4);
  const hue = seed % 360;
  return `linear-gradient(135deg, hsl(${hue}, 24%, 86%) 0%, hsl(${(hue + 30) % 360}, 22%, 78%) 100%)`;
}
