import type { MetadataRoute } from 'next';

import { siteUrl } from '@/lib/seo';

export default function robots(): MetadataRoute.Robots {
  const base = siteUrl();

  return {
    rules: {
      userAgent: '*',
      allow: '/',
      // Private / auth-only surfaces and the post-ad wizard carry no SEO value
      // and shouldn't be crawled.
      disallow: [
        '/account/',
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
