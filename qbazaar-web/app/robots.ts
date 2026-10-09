import type { MetadataRoute } from 'next';

import { siteUrl } from '@/lib/seo';

export default function robots(): MetadataRoute.Robots {
  const base = siteUrl();

  return {
    rules: {
      userAgent: '*',
      allow: '/',
      // Private / auth-only surfaces, the post-ad wizard and the JSON API carry
      // no SEO value and shouldn't be crawled.
      disallow: [
        '/account/',
        '/checkout/',
        '/api/',
        '/impersonate',
        '/post-ad',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password',
        '/verify-otp',
        '/verify-email',
      ],
    },
    sitemap: `${base}/sitemap.xml`,
    host: base,
  };
}
