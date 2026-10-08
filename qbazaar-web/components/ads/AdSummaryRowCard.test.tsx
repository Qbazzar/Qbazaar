import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { AdSummary, Location } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useLocationsStore } from '@/store/locations';

vi.mock('@/components/ads/FavoriteButton', () => ({ FavoriteButton: () => null }));

import { AdSummaryRowCard } from './AdSummaryRowCard';

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
  spec_chips: [
    { key: 'year', label: 'Year', value: '2019' },
    { key: 'fuel', label: 'Fuel', value: 'Diesel' },
  ],
};

beforeEach(() => {
  useLocationsStore.setState({ qatar: [doha], hydrated: true });
  setClientLocale('ar');
});

describe('AdSummaryRowCard', () => {
  it('names the place in the page language from the locations tree', () => {
    render(<AdSummaryRowCard ad={ad} />);

    expect(screen.getByText(/اللؤلؤة/)).toBeInTheDocument();
  });

  it('falls back to the slug as words until the tree is loaded', () => {
    useLocationsStore.setState({ qatar: null, hydrated: false });
    setClientLocale('en');
    render(<AdSummaryRowCard ad={ad} />);

    expect(screen.getByText(/the pearl/)).toBeInTheDocument();
  });

  it('prices the ad like the listing cards, with Latin digits', () => {
    render(<AdSummaryRowCard ad={ad} />);

    expect(screen.getAllByText('ر.ق 287,000').length).toBeGreaterThan(0);
  });

  it('shows the description line and the spec chips', () => {
    render(<AdSummaryRowCard ad={ad} />);

    expect(screen.getByText(ad.summary!)).toBeInTheDocument();
    expect(screen.getByText('2019')).toBeInTheDocument();
    expect(screen.getByText('Diesel')).toBeInTheDocument();
  });

  it('leaves the description out when the API sends none', () => {
    render(<AdSummaryRowCard ad={{ ...ad, summary: '' }} />);

    expect(screen.queryByText(ad.summary!)).toBeNull();
    expect(screen.getByRole('heading', { name: ad.title })).toBeInTheDocument();
  });
});
