import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.hoisted(() => vi.fn());
const toast = vi.hoisted(() => ({ error: vi.fn() }));

vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast }));
vi.mock('@/lib/api/categories', () => ({ getCategoryPage: vi.fn() }));
vi.mock('@/lib/api/favorites', () => ({ setFavoriteCategory: vi.fn(), listFavoriteCategories: vi.fn() }));
vi.mock('@/hooks/useAuth', () => ({ useAuth: vi.fn() }));

import { useAuth } from '@/hooks/useAuth';
import { getCategoryPage } from '@/lib/api/categories';
import { ApiClientError } from '@/lib/api/auth';
import { setFavoriteCategory } from '@/lib/api/favorites';
import { setClientLocale } from '@/lib/i18n/locale';
import type { CategoryPage } from '@/lib/api/types';

import { FavoriteCategoryButton } from './FavoriteCategoryButton';

function auth(state: { isAuthenticated: boolean; isHydrated?: boolean }) {
  vi.mocked(useAuth).mockReturnValue({ isHydrated: true, ...state } as never);
}

function categoryPage(isFavorited: boolean): CategoryPage {
  return { is_favorited: isFavorited } as CategoryPage;
}

function renderButton() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  render(<FavoriteCategoryButton slug="cars" />, { wrapper });
  return userEvent.setup();
}

function failure(code: string) {
  return new ApiClientError({ status: 422, code, messageKey: code, message: 'raw' });
}

describe('FavoriteCategoryButton', () => {
  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
    window.history.replaceState(null, '', '/c/cars?seller_type=private');
  });

  it('sends a guest to the login page, back to this URL, without loading the category', async () => {
    auth({ isAuthenticated: false });
    const user = renderButton();

    await user.click(screen.getByRole('button', { name: 'Add to Favorite' }));

    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent('/c/cars?seller_type=private')}`);
    expect(getCategoryPage).not.toHaveBeenCalled();
    expect(setFavoriteCategory).not.toHaveBeenCalled();
  });

  it('starts from is_favorited of the category', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(true));
    renderButton();

    expect(await screen.findByRole('button', { name: 'Favorited, remove category from favorites' })).toBeEnabled();
    expect(screen.getByText('Favorited')).toBeInTheDocument();
  });

  it('flips at once and keeps the server’s answer', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(false));
    let finish: (value: { favorited: boolean }) => void = () => undefined;
    vi.mocked(setFavoriteCategory).mockReturnValue(new Promise((resolve) => (finish = resolve)));
    const user = renderButton();

    await user.click(await screen.findByRole('button', { name: 'Add to Favorite' }));

    expect(setFavoriteCategory).toHaveBeenCalledWith('cars', true);
    expect(await screen.findByText('Favorited')).toBeInTheDocument();
    finish({ favorited: true });
    await waitFor(() => expect(screen.getByRole('button', { name: 'Favorited, remove category from favorites' })).toBeEnabled());
  });

  it('rolls back and says why when the cap is reached (FAV_002)', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(false));
    vi.mocked(setFavoriteCategory).mockRejectedValue(failure('FAV_002'));
    const user = renderButton();

    await user.click(await screen.findByRole('button', { name: 'Add to Favorite' }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('You have reached the limit of favorite categories. Remove one to add another.'));
    expect(await screen.findByRole('button', { name: 'Add to Favorite' })).toBeEnabled();
  });

  it('explains an unknown category (CAT_001) and speaks Arabic in Arabic', async () => {
    setClientLocale('ar');
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(false));
    vi.mocked(setFavoriteCategory).mockRejectedValue(failure('CAT_001'));
    const user = renderButton();

    await user.click(await screen.findByRole('button', { name: 'أضف إلى المفضلة' }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('هذا القسم لم يعد متاحاً.'));
  });

  it('waits for the auth store before it can be pressed', () => {
    auth({ isAuthenticated: false, isHydrated: false });
    renderButton();

    expect(screen.getByRole('button', { name: 'Add to Favorite' })).toBeDisabled();
  });

  it('removes with DELETE when it is already a favourite', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(true));
    vi.mocked(setFavoriteCategory).mockResolvedValue({ favorited: false });
    const user = renderButton();

    await user.click(await screen.findByRole('button', { name: 'Favorited, remove category from favorites' }));

    expect(setFavoriteCategory).toHaveBeenCalledWith('cars', false);
    expect(await screen.findByRole('button', { name: 'Add to Favorite' })).toBeEnabled();
  });

  it('names the pill with the text it shows (WCAG 2.5.3)', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockResolvedValue(categoryPage(true));
    renderButton();

    const pill = await screen.findByRole('button', { name: /^Favorited/ });
    expect(pill).toHaveTextContent('Favorited');
  });

  it('tries the category again when its first load failed, instead of staying dead', async () => {
    auth({ isAuthenticated: true });
    vi.mocked(getCategoryPage).mockRejectedValueOnce(new Error('offline')).mockResolvedValue(categoryPage(true));
    const user = renderButton();

    const pill = await screen.findByRole('button', { name: 'Add to Favorite' });
    await waitFor(() => expect(pill).toBeEnabled());
    await user.click(pill);

    expect(setFavoriteCategory).not.toHaveBeenCalled();
    expect(await screen.findByRole('button', { name: /^Favorited/ })).toBeEnabled();
  });
});
