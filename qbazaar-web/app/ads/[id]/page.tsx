import type { Metadata } from 'next';

import type { Ad } from '@/lib/api/types';
import { absoluteUrl, fetchApiData } from '@/lib/seo';
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

/**
 * Schema.org Product JSON-LD so the listing is eligible for rich results.
 * Rendered server-side from the ad payload; absent when the ad can't be loaded.
 */
function AdProductJsonLd({ ad }: { ad: Ad }) {
  const images = (ad.images ?? []).map((media) => media.url).filter(Boolean);

  const jsonLd: Record<string, unknown> = {
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

  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
    />
  );
}

/**
 * Ad detail — `/ads/{id}`.
 *
 * The interactive detail (gallery, custom fields, seller card) is rendered by
 * the client island; the server entrypoint adds crawlable Product JSON-LD when
 * the ad can be fetched.
 */
export default async function AdDetailPage({ params }: PageProps) {
  const { id } = await params;
  const ad = await fetchApiData<Ad>(`/api/v1/ads/${id}`, 300);

  return (
    <>
      {ad ? <AdProductJsonLd ad={ad} /> : null}
      <AdDetailClient id={id} />
    </>
  );
}
