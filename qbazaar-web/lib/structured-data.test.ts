import { describe, expect, it } from 'vitest';

import type { Ad } from '@/lib/api/types';
import { breadcrumbJsonLd } from '@/lib/seo';
import { organizationJsonLd, productJsonLd, websiteJsonLd } from './structured-data';

const ad = {
  id: '01ad',
  title: 'BMW 320i',
  description: 'Clean car',
  price: 52000,
  price_type: 'fixed',
  currency: 'QAR',
  status: 'active',
  images: [{ url: 'https://cdn.qbazaar.qa/a.jpg' }, { url: 'https://cdn.qbazaar.qa/b.jpg' }],
  user: { id: 'u1', full_name: 'Sara', business_name: null, account_type: 'private' },
} as unknown as Ad;

describe('structured data', () => {
  it('describes the organization with an absolute logo', () => {
    expect(organizationJsonLd('QBazaar')).toMatchObject({
      '@type': 'Organization',
      name: 'QBazaar',
      url: 'https://qbazaar.qa/',
      logo: 'https://qbazaar.qa/icons/icon-512.png',
    });
  });

  it('points the website search action at /search', () => {
    expect(websiteJsonLd('QBazaar')).toMatchObject({
      '@type': 'WebSite',
      potentialAction: {
        '@type': 'SearchAction',
        target: { urlTemplate: 'https://qbazaar.qa/search?q={search_term_string}' },
        'query-input': 'required name=search_term_string',
      },
    });
  });

  it('builds a Product with an Offer, its images and its seller', () => {
    expect(productJsonLd(ad)).toMatchObject({
      '@type': 'Product',
      name: 'BMW 320i',
      image: ['https://cdn.qbazaar.qa/a.jpg', 'https://cdn.qbazaar.qa/b.jpg'],
      offers: {
        '@type': 'Offer',
        price: 52000,
        priceCurrency: 'QAR',
        availability: 'https://schema.org/InStock',
        seller: { '@type': 'Person', name: 'Sara', url: 'https://qbazaar.qa/u/u1' },
      },
    });
  });

  it('marks sold ads as sold out and free ads as price 0', () => {
    const sold = productJsonLd({ ...ad, status: 'sold' } as Ad) as { offers: { availability: string } };
    const free = productJsonLd({ ...ad, price: null, price_type: 'free' } as Ad) as { offers: { price: number } };

    expect(sold.offers.availability).toBe('https://schema.org/SoldOut');
    expect(free.offers.price).toBe(0);
  });

  it('leaves the Offer out when the price is on request', () => {
    expect(productJsonLd({ ...ad, price: null, price_type: 'contact' } as Ad)).not.toHaveProperty('offers');
  });

  it('numbers breadcrumb items from 1 with absolute URLs', () => {
    const list = breadcrumbJsonLd([{ name: 'Home', path: '/' }, { name: 'Cars', path: '/c/cars' }]);

    expect(list).toMatchObject({
      '@type': 'BreadcrumbList',
      itemListElement: [
        { position: 1, item: 'https://qbazaar.qa/' },
        { position: 2, name: 'Cars', item: 'https://qbazaar.qa/c/cars' },
      ],
    });
  });
});
