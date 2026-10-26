'use client';

import { useState } from 'react';

type Source = string | null | undefined;

/**
 * The first usable source, moving on to the next when the image fails to
 * load: a conversion the API listed can still be missing at the CDN (queued
 * or failed), and a broken photo should fall back to a file that exists.
 */
export function useImageFallback(sources: Source[]): { src: string | undefined; onError: () => void } {
  const candidates = [...new Set(sources.filter((source): source is string => Boolean(source)))];
  const key = candidates.join('|');
  const [failed, setFailed] = useState({ key, count: 0 });
  // A new photo starts again from its first source.
  const count = failed.key === key ? failed.count : 0;

  return {
    src: candidates[count],
    onError: () => setFailed({ key, count: Math.min(count + 1, candidates.length) }),
  };
}
