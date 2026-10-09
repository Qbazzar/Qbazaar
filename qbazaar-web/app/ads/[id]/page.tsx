import type { Metadata } from 'next';

import type { Ad } from '@/lib/api/types';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { formatAdPrice, publicImageUrl } from '@/lib/ads/format';
import { pageMetadata } from '@/lib/page-metadata';
import { breadcrumbJsonLd, fetchApiData } from '@/lib/seo';
import { productJsonLd } from '@/lib/structured-data';
import { JsonLd } from '@/components/seo/JsonLd';
import { ProductMeta } from '@/components/seo/ProductMeta';
import { AdDetailClient } from './AdDetailClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

const META_DESCRIPTION_MAX = 160;

/** "QAR 52,000 · Doha — first lines of the text", cut to a single line of a meta description. */
function metaDescription(ad: Ad, locale: Locale): string | undefined {
  const city = localized(ad.location?.name, locale);
  const body = ad.description?.replace(/\s+/g, ' ').trim() ?? '';
  const lead = [formatAdPrice(ad, locale), city].filter(Boolean).join(' · ');
  const text = body ? `${lead} — ${body}` : lead;
  return text.length > META_DESCRIPTION_MAX ? `${text.slice(0, META_DESCRIPTION_MAX - 1)}…` : text;
}

/**
 * The public copy of the ad, cached for five minutes per language: the option
 * labels of its category come in the language the request asks for. The
 * metadata and the page call it with the same URL, so they share the entry.
 */
function fetchAd(id: string, locale: Locale): Promise<Ad | null> {
  return fetchApiData<Ad>(`/api/v1/ads/${encodeURIComponent(id)}?lang=${locale}`, 300);
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const [{ id }, locale] = await Promise.all([params, resolveServerLocale()]);
  const ad = await fetchAd(id, locale);

  if (!ad) {
    return { title: t('ads.errors.ad_not_found') };
  }

  return pageMetadata({
    title: ad.title,
    description: metaDescription(ad, locale),
    path: `/ads/${id}`,
    image: ad.images?.[0] ? publicImageUrl(ad.images[0]) : undefined,
    type: 'product',
  });
}

/** Home › Categories › {category} › {ad} breadcrumb. */
function adBreadcrumbJsonLd(ad: Ad): Record<string, unknown> {
  const crumbs = [
    { name: t('brand.name', 'QBazaar'), path: '/' },
    { name: t('categories.all', 'الأقسام'), path: '/categories' },
  ];

  if (ad.category?.slug) {
    crumbs.push({
      name: localized(ad.category.name) || ad.category.slug,
      path: `/c/${ad.category.slug}`,
    });
  }

  crumbs.push({ name: ad.title, path: `/ads/${ad.id}` });

  return breadcrumbJsonLd(crumbs);
}

/**
 * Ad detail — `/ads/{id}`.
 *
 * The server fetches the public copy of the ad once: for the metadata, the
 * crawlable Product + Breadcrumb JSON-LD and the first render of the client
 * island, which then refetches with the viewer's session (the owner may be
 * looking at an ad the public can't see).
 */
export default async function AdDetailPage({ params }: PageProps) {
  // The page renders alongside the root layout, so the JSON-LD names need the request locale here too.
  const [{ id }, locale] = await Promise.all([params, resolveServerLocale()]);
  const ad = await fetchAd(id, locale);

  return (
    <>
      {ad ? (
        <>
          <ProductMeta ad={ad} />
          <JsonLd data={[productJsonLd(ad), adBreadcrumbJsonLd(ad)]} />
        </>
      ) : null}
      <AdDetailClient id={id} initialAd={ad ?? undefined} />
    </>
  );
}
