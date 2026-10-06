import type { Metadata } from 'next';

import type { Ad } from '@/lib/api/types';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { absoluteUrl, breadcrumbJsonLd, fetchApiData } from '@/lib/seo';
import { JsonLd } from '@/components/seo/JsonLd';
import { AdDetailClient } from './AdDetailClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

/** Trim a body to a single-line meta description of at most ~160 chars. */
function metaDescription(body: string | null | undefined): string | undefined {
  if (!body) return undefined;
  const flat = body.replace(/\s+/g, ' ').trim();
  return flat.length > 160 ? `${flat.slice(0, 157)}…` : flat;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { id } = await params;
  // Cached + deduped with the page's own fetch below (same URL + options).
  const ad = await fetchApiData<Ad>(`/api/v1/ads/${id}`, 300);

  if (!ad) {
    return { title: 'الإعلان' };
  }

  const url = absoluteUrl(`/ads/${id}`);
  const description = metaDescription(ad.description);
  const image = ad.images?.[0]?.url;

  return {
    title: ad.title,
    description,
    alternates: { canonical: url },
    openGraph: {
      title: ad.title,
      description,
      url,
      type: 'website',
      images: image ? [{ url: image }] : undefined,
    },
    twitter: {
      card: 'summary_large_image',
      title: ad.title,
      description,
      images: image ? [image] : undefined,
    },
  };
}

/** Schema.org Product graph for the listing. */
function adProductJsonLd(ad: Ad): Record<string, unknown> {
  const images = (ad.images ?? []).map((media) => media.url).filter(Boolean);

  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: ad.title,
    description: ad.description,
    ...(images.length > 0 ? { image: images } : {}),
    ...(ad.price != null
      ? {
          offers: {
            '@type': 'Offer',
            price: ad.price,
            priceCurrency: ad.currency,
            availability:
              ad.status === 'sold'
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
            url: absoluteUrl(`/ads/${ad.id}`),
          },
        }
      : {}),
  };
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
  const { id } = await params;
  // The page renders alongside the root layout, so the JSON-LD names need the request locale here too.
  const [ad] = await Promise.all([fetchApiData<Ad>(`/api/v1/ads/${id}`, 300), resolveServerLocale()]);

  return (
    <>
      {ad ? (
        <JsonLd data={[adProductJsonLd(ad), adBreadcrumbJsonLd(ad)]} />
      ) : null}
      <AdDetailClient id={id} initialAd={ad ?? undefined} />
    </>
  );
}
