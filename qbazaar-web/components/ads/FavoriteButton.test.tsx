import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

vi.mock('next/navigation', () => ({ usePathname: () => '/', useRouter: () => ({ push: vi.fn() }) }));

import { FavoriteButton } from './FavoriteButton';

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>;
}

describe('FavoriteButton', () => {
  beforeEach(() => setClientLocale('en'));

  it('names a card heart after its ad and lets aria-pressed carry the state', () => {
    const { rerender } = render(<FavoriteButton adId="ad-1" adTitle="Toyota Corolla" />, { wrapper });
    expect(screen.getByRole('button', { name: 'Save Toyota Corolla' })).toHaveAttribute('aria-pressed', 'false');

    rerender(<FavoriteButton adId="ad-1" adTitle="Toyota Corolla" initialFavorited />);
    expect(screen.getByRole('button', { name: 'Save Toyota Corolla' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('keeps the short label where the ad is already named around it', () => {
    render(<FavoriteButton adId="ad-1" initialFavorited />, { wrapper });

    expect(screen.getByRole('button', { name: 'Unsave' })).toHaveAttribute('aria-pressed', 'true');
  });
});
