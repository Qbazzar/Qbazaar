import type { MetadataRoute } from 'next';

import ar from '@/i18n/ar.json';
import { DEFAULT_LOCALE, dirFor } from '@/lib/i18n/locale';

const BRAND_ORANGE = '#f38057';

// The manifest is one file for every visitor, so it carries the default language
// (the app chooses its language per visitor with a cookie).
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: `${ar.brand.name} — ${ar.brand.tagline}`,
    short_name: ar.brand.name,
    description: ar.brand.description,
    id: '/',
    start_url: '/',
    scope: '/',
    display: 'standalone',
    lang: DEFAULT_LOCALE,
    dir: dirFor(DEFAULT_LOCALE),
    background_color: '#ffffff',
    theme_color: BRAND_ORANGE,
    icons: [
      { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
      { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
      { src: '/icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
  };
}
