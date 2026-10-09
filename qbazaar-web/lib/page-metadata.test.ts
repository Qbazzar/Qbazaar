import { describe, expect, it } from 'vitest';

import { openGraphLocale, pageMetadata } from './page-metadata';

describe('pageMetadata', () => {
  it('sets canonical, share cards and the page language', () => {
    const meta = pageMetadata({ title: 'BMW', description: 'Clean', path: '/ads/1', image: 'https://cdn.qbazaar.qa/a.jpg' });

    expect(meta.alternates).toEqual({ canonical: '/ads/1' });
    expect(meta.openGraph).toMatchObject({
      title: 'BMW',
      url: '/ads/1',
      type: 'website',
      locale: 'ar_QA',
      alternateLocale: ['en_US'],
      images: [{ url: 'https://cdn.qbazaar.qa/a.jpg' }],
    });
    expect(meta.twitter).toMatchObject({ card: 'summary_large_image', images: ['https://cdn.qbazaar.qa/a.jpg'] });
  });

  it('falls back to the branded share card when the page has no image', () => {
    const meta = pageMetadata({ title: 'Cars', path: '/c/cars' });

    expect(meta.openGraph).toMatchObject({ images: [{ url: '/opengraph-image', width: 1200, height: 630 }] });
    expect(meta.twitter).toMatchObject({ images: ['/twitter-image'] });
  });

  it('leaves og:type to <ProductMeta> for products', () => {
    expect(pageMetadata({ title: 'BMW', path: '/ads/1', type: 'product' }).openGraph).not.toHaveProperty('type');
  });

  it('swaps the alternate locale for English pages', () => {
    expect(openGraphLocale('en')).toEqual({ locale: 'en_US', alternateLocale: ['ar_QA'] });
  });
});
