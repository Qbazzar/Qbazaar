import { describe, expect, it } from 'vitest';

import { cdnImageLoader } from './cdn-loader';

const url = (name: string) => `https://cdn.qbazaar.qa/684/conversions/01M4ED8V27CHJ1PJXTQX95PET6-${name}.jpg`;

describe('cdnImageLoader', () => {
  it('serves medium for widths up to 750 px, whichever conversion the source names', () => {
    expect(cdnImageLoader({ src: url('medium'), width: 384 })).toBe(url('medium'));
    expect(cdnImageLoader({ src: url('large'), width: 750 })).toBe(url('medium'));
  });

  it('serves large above 750 px', () => {
    expect(cdnImageLoader({ src: url('medium'), width: 828 })).toBe(url('large'));
    expect(cdnImageLoader({ src: url('large'), width: 1920 })).toBe(url('large'));
  });

  it('leaves the square thumbnail alone', () => {
    expect(cdnImageLoader({ src: url('thumbnail'), width: 1080 })).toBe(url('thumbnail'));
  });

  it('keeps the query string of a conversion', () => {
    expect(cdnImageLoader({ src: `${url('large')}?v=2`, width: 256 })).toBe(`${url('medium')}?v=2`);
  });

  it('returns other sources as they are', () => {
    expect(cdnImageLoader({ src: '/brand/qb-logo.svg', width: 96 })).toBe('/brand/qb-logo.svg');
    expect(cdnImageLoader({ src: 'https://cdn.qbazaar.qa/avatars/1.jpg', width: 96 })).toBe('https://cdn.qbazaar.qa/avatars/1.jpg');
    expect(cdnImageLoader({ src: 'https://lh3.googleusercontent.com/a/medium.jpg', width: 96 })).toBe('https://lh3.googleusercontent.com/a/medium.jpg');
  });
});
