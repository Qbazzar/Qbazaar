import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { AdSummary, Location } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useLocationsStore } from '@/store/locations';

vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({ push: vi.fn() }),
}));

import { HomeAdSection } from './HomeAdSection';

const ad: AdSummary = {
  id: 'ad-1',
  title: 'ساعة أبل ألترا 2',
  price: 2350,
  price_type: 'fixed',
  currency: 'QAR',
  status: 'active',
  views_count: 0,
  favorites_count: 0,
  primary_image: null,
  location_slug: 'the-pearl',
  category_slug: 'electronics',
  published_at: '2026-10-06T07:00:00Z',
  created_at: '2026-10-06T07:00:00Z',
};

function withQueryClient(children: ReactNode) {
  return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>;
}

function section(props: Partial<Parameters<typeof HomeAdSection>[0]>) {
  return withQueryClient(<HomeAdSection id="latest" title="Latest ads" subtitle="Fresh deals" ads={[]} isLoading={false} {...props} />);
}

describe('HomeAdSection', () => {
  beforeEach(() => {
    setClientLocale('en');
    useLocationsStore.setState({ qatar: null });
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-06T08:00:00Z'));
  });
  afterEach(() => vi.useRealTimers());

  it('hides itself when there is nothing to show', () => {
    const { container } = render(section({}));

    expect(container).toBeEmptyDOMElement();
  });

  it('marks the loading state as busy', () => {
    const { container } = render(section({ ads: undefined, isLoading: true }));

    expect(container.querySelector('[aria-busy="true"]')).not.toBeNull();
  });

  it('renders each ad as a card in a labelled rail', () => {
    useLocationsStore.setState({ qatar: [{ slug: 'the-pearl', name: { en: 'The Pearl', ar: 'اللؤلؤة' }, children: [] } as unknown as Location] });
    render(section({ ads: [ad] }));

    const rail = screen.getByRole('list', { name: 'Latest ads' });
    const title = screen.getByRole('link', { name: 'ساعة أبل ألترا 2' });
    expect(rail).toContainElement(title);
    expect(title).toHaveAttribute('href', '/ads/ad-1');
    expect(title).toHaveAttribute('dir', 'auto');
    expect(screen.getByText('QAR 2,350')).toBeInTheDocument();
    expect(screen.getByText('The Pearl • 1 hour ago')).toBeInTheDocument();
  });
});
