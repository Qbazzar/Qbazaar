import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { Location } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';

const locations = vi.hoisted(() => ({ data: undefined as Location[] | undefined, isLoading: false, isError: false }));
vi.mock('@/lib/queries/locations', () => ({ useQatarLocationsQuery: () => locations }));

import { HomeCityTags, collagePlaces, slotsFit } from './HomeCityTags';

function place(slug: string, en: string, ar: string, children: Location[] = []): Location {
  return { id: slug, parent_id: null, slug, name: { en, ar }, type: 'city', lat: null, lng: null, children } as Location;
}

const doha = place('doha', 'Doha', 'الدوحة', [place('west-bay', 'West Bay', 'الخليج الغربي'), place('al-sadd', 'Al Sadd', 'السد')]);
const wakra = place('al-wakra', 'Al Wakra', 'الوكرة', [place('mesaieed', 'Mesaieed', 'مسيعيد')]);

describe('collagePlaces', () => {
  it('lists every city before the districts', () => {
    expect(collagePlaces([doha, wakra], 'en').map((p) => p.label)).toEqual(['Doha', 'Al Wakra', 'West Bay', 'Al Sadd', 'Mesaieed']);
  });

  it('uses the page language and stops at the sixteen collage slots', () => {
    const cities = Array.from({ length: 20 }, (_, i) => place(`city-${i}`, `City ${i}`, `مدينة ${i}`));

    expect(collagePlaces([doha], 'ar')[0]).toEqual({ slug: 'doha', label: 'الدوحة' });
    expect(collagePlaces(cities, 'en')).toHaveLength(16);
  });
});

describe('HomeCityTags', () => {
  beforeEach(() => {
    setClientLocale('en');
    Object.assign(locations, { data: [doha, wakra], isLoading: false, isError: false });
  });

  it('links each place to the search filtered by it', () => {
    render(<HomeCityTags />);

    const link = screen.getByRole('link', { name: 'Browse ads in West Bay' });
    expect(link).toHaveAttribute('href', '/search?location_slug=west-bay');
    expect(link).toHaveTextContent('West Bay');
    expect(screen.getAllByRole('listitem')).toHaveLength(5);
  });

  it('renders nothing when the locations fail to load', () => {
    Object.assign(locations, { data: undefined, isError: true });
    const { container } = render(<HomeCityTags />);

    expect(container).toBeEmptyDOMElement();
  });
});

describe('slotsFit', () => {
  const pill = (width: number) => ({ width, height: 45, insetX: 21, insetY: 11 });

  it('keeps the scattered slots while every label stays clear', () => {
    expect(slotsFit(Array.from({ length: 16 }, () => pill(100)), 1360)).toBe(true);
  });

  it('lets pills overlap in their padding, as Doha and Al Wakra do in the reference', () => {
    expect(slotsFit([pill(94), pill(100), pill(130)], 1360)).toBe(true);
  });

  it("gives up the slots once a pill would cover a neighbour's label", () => {
    expect(slotsFit(Array.from({ length: 16 }, () => pill(180)), 1360)).toBe(false);
  });

  it('gives up the slots when a pill would leave the collage', () => {
    expect(slotsFit([...Array.from({ length: 15 }, () => pill(100)), pill(200)], 1360)).toBe(false);
  });
});
