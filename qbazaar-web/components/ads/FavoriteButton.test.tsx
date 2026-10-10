import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

const push = vi.fn();
vi.mock('next/navigation', () => ({ usePathname: () => '/', useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), info: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/api/favorites', () => ({ toggleFavorite: vi.fn() }));

import { toggleFavorite } from '@/lib/api/favorites';
import { useAuthStore } from '@/store/auth';
import { useFavoritesStore } from '@/store/favorites';
import type { User } from '@/lib/api/types';

import { FavoriteButton } from './FavoriteButton';

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>;
}

describe('FavoriteButton', () => {
  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
    useFavoritesStore.getState().clear();
    useAuthStore.setState({ user: { id: 'u' } as User, accessToken: 't', isHydrated: true });
  });

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

  it('unsaves on the wishlist at once and puts the heart back when the request fails', async () => {
    let fail!: (reason: Error) => void;
    vi.mocked(toggleFavorite).mockReturnValue(new Promise((_, reject) => (fail = reject)));
    useFavoritesStore.getState().mergeIds(['ad-1']);
    render(<FavoriteButton adId="ad-1" initialFavorited />, { wrapper });

    await userEvent.click(screen.getByRole('button', { name: 'Unsave' }));

    expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute('aria-pressed', 'false');
    fail(new Error('network'));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Unsave' })).toHaveAttribute('aria-pressed', 'true'));
  });

  it('sends a guest to the login page with the page to return to', async () => {
    useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
    window.history.pushState({}, '', '/ads/ad-1?x=1');
    render(<FavoriteButton adId="ad-1" />, { wrapper });

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent('/ads/ad-1?x=1')}`);
    expect(toggleFavorite).not.toHaveBeenCalled();
  });
});
