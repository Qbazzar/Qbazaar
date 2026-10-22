'use client';

/**
 * Image loader of `next/image` (`images.loaderFile`) for the media CDN.
 *
 * The CDN (Cloudflare in front of R2) serves the conversions Laravel makes of
 * each upload and has no resizing of its own, so the loader does not build a
 * resize URL: it picks the conversion that is wide enough for the width the
 * browser asked for. Both are the same picture at a different size
 * (`Fit::Contain`), and every file is immutable at the edge.
 *
 * - `thumbnail` is a 200 px square crop, so it is never swapped in or out.
 * - `medium` is at most 640 px wide, `large` at most 1024 px.
 * - Anything else (avatars, local assets, other hosts) is returned unchanged.
 */
const CONVERSION_URL = /^(https?:\/\/[^?#]*\/conversions\/[^/?#]+-)(thumbnail|medium|large)(\.[a-z0-9]+)(\?[^#]*)?$/i;

/** Widths up to this are served from `medium`; the 750 px device size still reads well from 640 px. */
const MEDIUM_MAX_WIDTH = 750;

export interface ImageLoaderProps {
  src: string;
  width: number;
  quality?: number;
}

export function cdnImageLoader({ src, width }: ImageLoaderProps): string {
  const match = CONVERSION_URL.exec(src);
  if (!match || match[2].toLowerCase() === 'thumbnail') return src;

  const [, base, , extension, query = ''] = match;
  return `${base}${width <= MEDIUM_MAX_WIDTH ? 'medium' : 'large'}${extension}${query}`;
}

export default cdnImageLoader;
