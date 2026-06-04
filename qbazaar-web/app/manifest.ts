import type { MetadataRoute } from 'next';

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: 'QBazaar — Qatar classifieds marketplace',
    short_name: 'QBazaar',
    description: 'Buy, sell and discover near you in Qatar.',
    start_url: '/',
    display: 'standalone',
    background_color: '#ffffff',
    theme_color: '#F37335',
    // TODO: ship dedicated maskable 192/512 icons; the brand mark is a
    // reasonable stand-in until then.
    icons: [
      {
        src: '/brand/logo.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'any',
      },
    ],
  };
}
