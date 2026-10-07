import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { AdSummary, Location } from '@/lib/api/types';

const locations = vi.hoisted(() => ({ data: undefined as Location[] | undefined }));
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: () => ({ data: locations.data }) }));
vi.mock('@/components/ads/FavoriteButton', () => ({ FavoriteButton: () => null }));

import { setClientLocale } from '@/lib/i18n/locale';

import { AdSummaryGridCard, AdSummaryRowCard } from './AdSummaryCards';

const pearl: Location = {
  id: 'pearl',
  parent_id: 'doha',
  slug: 'the-pearl',
  name: { ar: 'اللؤلؤة', en: 'The Pearl' },
  type: 'district',
  lat: null,
  lng: null,
  children: [],
};

const doha: Location = { ...pearl, id: 'doha', parent_id: null, slug: 'doha', name: { ar: 'الدوحة', en: 'Doha' }, type: 'city', children: [pearl] };

const ad: AdSummary = {
  id: 'ad-1',
  title: 'BMW 320d Touring',
  summary: 'Beautifully maintained touring with M-Sport package…',
  price: 287000,
  price_type: 'fixed',
  currency: 'QAR',
  status: 'active',
  views_count: 0,
  favorites_count: 0,
  primary_image: null,
  location_slug: 'the-pearl',
  category_slug: 'cars',
  published_at: null,
  created_at: '2026-10-01T10:00:00+00:00',
};

beforeEach(() => {
  locations.data = [doha];
  setClientLocale('ar');
});

describe('AdSummary cards', () => {
  it('name the place in the page language from the locations tree', () => {
    render(<AdSummaryGridCard ad={ad} imageSizes="100vw" />);

    expect(screen.getByText('اللؤلؤة')).toBeInTheDocument();
  });

  it('fall back to the slug as words until the tree is loaded', () => {
    locations.data = undefined;
    setClientLocale('en');
    render(<AdSummaryGridCard ad={ad} imageSizes="100vw" />);

    expect(screen.getByText('The Pearl')).toBeInTheDocument();
  });

  it('show the description line on the seller row', () => {
    render(<AdSummaryRowCard ad={ad} imageSizes="100vw" />);

    expect(screen.getByText(ad.summary!)).toBeInTheDocument();
  });

  it('leave the description out when the API sends none', () => {
    render(<AdSummaryRowCard ad={{ ...ad, summary: '' }} imageSizes="100vw" />);

    expect(screen.queryByText(ad.summary!)).toBeNull();
    expect(screen.getByRole('heading', { name: ad.title })).toBeInTheDocument();
  });
});
