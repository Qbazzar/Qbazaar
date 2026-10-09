import type { Metadata } from 'next';

import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { OG_ALT, OG_SIZE } from '@/lib/og/constants';

const OG_LOCALE: Record<Locale, string> = { ar: 'ar_QA', en: 'en_US' };

/**
 * The site picks its language by cookie, not by URL, so there is one URL per
 * page and no hreflang: the page's own language is `og:locale` and the other
 * one is offered as `og:locale:alternate`.
 */
export function openGraphLocale(locale: Locale = getLocale()): { locale: string; alternateLocale: string[] } {
  const other: Locale = locale === 'ar' ? 'en' : 'ar';
  return { locale: OG_LOCALE[locale], alternateLocale: [OG_LOCALE[other]] };
}

export interface PageMetadataInput {
  title: string;
  description?: string;
  /** Path of the page on the site; the canonical URL is built from it. */
  path: string;
  /** Absolute image URL. Without one the page shows the branded default share card. */
  image?: string | null;
  type?: 'website' | 'article' | 'profile' | 'product';
  robots?: Metadata['robots'];
}

/**
 * Title, description, canonical, Open Graph and Twitter card of a public page.
 * `title` is the bare page title: the root layout adds the "| QBazaar" suffix
 * to the document title, while the share cards carry the bare one.
 */
export function pageMetadata({ title, description, path, image, type = 'website', robots }: PageMetadataInput): Metadata {
  const text = description ?? t('brand.description');
  // A page's own `openGraph` replaces the layout's, so the file-based default card must be named again.
  const openGraphImages = image ? [{ url: image }] : [{ url: '/opengraph-image', ...OG_SIZE, alt: OG_ALT }];
  const twitterImages = image ? [image] : ['/twitter-image'];

  return {
    title,
    description: text,
    alternates: { canonical: path },
    robots,
    openGraph: {
      title,
      description: text,
      url: path,
      siteName: t('brand.name', 'QBazaar'),
      // Next's typed og:type list has no "product": <ProductMeta> writes that tag itself.
      ...(type === 'product' ? {} : { type }),
      ...openGraphLocale(),
      images: openGraphImages,
    },
    twitter: { card: 'summary_large_image', title, description: text, images: twitterImages },
  };
}
