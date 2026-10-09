import type { Ad } from '@/lib/api/types';
import { adOfferPrice, publicImageUrl } from '@/lib/ads/format';
import { absoluteUrl } from '@/lib/seo';

const SCHEMA = 'https://schema.org';
const LOGO_PATH = '/icons/icon-512.png';

type JsonLdNode = Record<string, unknown>;

export function organizationJsonLd(name: string): JsonLdNode {
  return {
    '@context': SCHEMA,
    '@type': 'Organization',
    name,
    url: absoluteUrl('/'),
    logo: absoluteUrl(LOGO_PATH),
  };
}

/** WebSite node whose SearchAction points at the real `/search?q=` page. */
export function websiteJsonLd(name: string): JsonLdNode {
  return {
    '@context': SCHEMA,
    '@type': 'WebSite',
    name,
    url: absoluteUrl('/'),
    potentialAction: {
      '@type': 'SearchAction',
      target: { '@type': 'EntryPoint', urlTemplate: `${absoluteUrl('/search')}?q={search_term_string}` },
      'query-input': 'required name=search_term_string',
    },
  };
}

function availabilityOf(status: Ad['status']): string {
  return `${SCHEMA}/${status === 'sold' ? 'SoldOut' : 'InStock'}`;
}

/** Only a real price makes an Offer: free ads are 0, "contact for price" has none. */
function offerOf(ad: Ad): JsonLdNode | null {
  const price = adOfferPrice(ad);
  if (price == null) return null;

  return {
    '@type': 'Offer',
    price,
    priceCurrency: ad.currency,
    availability: availabilityOf(ad.status),
    url: absoluteUrl(`/ads/${ad.id}`),
    ...(ad.user ? { seller: sellerOf(ad.user) } : {}),
  };
}

function sellerOf(user: NonNullable<Ad['user']>): JsonLdNode {
  return {
    '@type': user.account_type === 'business' ? 'Organization' : 'Person',
    name: user.business_name || user.full_name,
    url: absoluteUrl(`/u/${user.id}`),
  };
}

/** Product + Offer for an ad page. */
export function productJsonLd(ad: Ad): JsonLdNode {
  const images = (ad.images ?? []).map(publicImageUrl).filter(Boolean);
  const offer = offerOf(ad);

  return {
    '@context': SCHEMA,
    '@type': 'Product',
    name: ad.title,
    description: ad.description,
    url: absoluteUrl(`/ads/${ad.id}`),
    ...(images.length > 0 ? { image: images } : {}),
    ...(offer ? { offers: offer } : {}),
  };
}
