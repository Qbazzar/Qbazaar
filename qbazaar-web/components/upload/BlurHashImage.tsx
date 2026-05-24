'use client';

/**
 * Image with a BlurHash placeholder.
 *
 * Renders a CSS-gradient backdrop derived from the BlurHash (so SSR has
 * something instantly), then on the client decodes the BlurHash into a tiny
 * PNG data URL and paints it on top. The real `<img>` fades in once it has
 * loaded — at that point we drop the placeholder layer entirely.
 *
 * Intentionally avoids `next/image`: the dropzone shows fresh uploads whose
 * URLs are on the user's local filesystem (`blob:`), which `next/image`
 * refuses to optimise. Plain `<img>` is also lighter for the home feed.
 */
import { useEffect, useState } from 'react';
import { blurhashToCss, blurhashToDataUrl } from '@/lib/images/blurhashToCss';
import { cn } from '@/lib/utils';

interface Props {
  src: string;
  alt: string;
  blurhash?: string | null;
  className?: string;
  imgClassName?: string;
  /** CSS aspect-ratio for the wrapper (e.g. "4 / 3", "1 / 1"). */
  aspect?: string;
  sizes?: string;
  loading?: 'lazy' | 'eager';
  /** When false (default), respects RTL by not flipping the image. */
  draggable?: boolean;
}

export function BlurHashImage({
  src,
  alt,
  blurhash,
  className,
  imgClassName,
  aspect = '4 / 3',
  sizes,
  loading = 'lazy',
  draggable = false,
}: Props) {
  const [loaded, setLoaded] = useState(false);
  const [decoded, setDecoded] = useState<string | null>(null);
  const gradient = blurhashToCss(blurhash);

  useEffect(() => {
    let cancelled = false;
    setLoaded(false);
    setDecoded(null);
    if (!blurhash) return;
    // Defer the decode so it doesn't block the first paint of a long list.
    const handle = window.requestIdleCallback
      ? window.requestIdleCallback(() => {
          const dataUrl = blurhashToDataUrl(blurhash);
          if (!cancelled) setDecoded(dataUrl);
        })
      : window.setTimeout(() => {
          const dataUrl = blurhashToDataUrl(blurhash);
          if (!cancelled) setDecoded(dataUrl);
        }, 0);
    return () => {
      cancelled = true;
      if (window.cancelIdleCallback && typeof handle === 'number') {
        window.cancelIdleCallback(handle);
      } else if (typeof handle === 'number') {
        window.clearTimeout(handle);
      }
    };
  }, [blurhash, src]);

  return (
    <div
      className={cn(
        'relative overflow-hidden bg-cream-200',
        className,
      )}
      style={{ aspectRatio: aspect, background: gradient }}
    >
      {decoded ? (
        <img
          src={decoded}
          alt=""
          aria-hidden="true"
          className={cn(
            'absolute inset-0 size-full object-cover transition-opacity duration-500',
            loaded ? 'opacity-0' : 'opacity-100',
          )}
        />
      ) : null}
      <img
        src={src}
        alt={alt}
        loading={loading}
        sizes={sizes}
        draggable={draggable}
        onLoad={() => setLoaded(true)}
        className={cn(
          'absolute inset-0 size-full object-cover transition-opacity duration-500',
          loaded ? 'opacity-100' : 'opacity-0',
          imgClassName,
        )}
      />
    </div>
  );
}
