import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

const replace = vi.hoisted(() => vi.fn());
vi.mock('next/navigation', () => ({
  usePathname: () => '/categories',
  useRouter: () => ({ replace, push: vi.fn() }),
}));
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));
vi.mock('@/lib/api/auth', () => ({ logout: vi.fn() }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));

import { logout } from '@/lib/api/auth';

import { MobileMenu } from './MobileMenu';

describe('MobileMenu', () => {
  // The drawer is code-split; load it once up front so the first test does not wait on the transform,
  // which can pass the default hook timeout while the whole suite runs in parallel.
  beforeAll(() => import('./MobileMenuDrawer'), 60_000);

  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
  });

  it('opens a named drawer with the site navigation', async () => {
    const user = userEvent.setup();
    render(<MobileMenu signedIn={false} />);

    await user.click(screen.getByRole('button', { name: 'Open menu' }));
    const drawer = await screen.findByRole('dialog', { name: 'Menu' });

    expect(drawer).toHaveClass('start-0', 'max-w-[340px]');
    expect(screen.getByRole('navigation', { name: 'Menu' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Categories' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute('aria-current');
  });

  it('gives guests the browsing rows with sign-up and login', async () => {
    const user = userEvent.setup();
    render(<MobileMenu signedIn={false} />);

    await user.click(screen.getByRole('button', { name: 'Open menu' }));
    const nav = await screen.findByRole('navigation', { name: 'Menu' });

    expect(within(nav).getAllByRole('listitem')).toHaveLength(5);
    expect(screen.getByRole('link', { name: 'Sign Up' })).toHaveAttribute('href', '/register');
    expect(screen.getByRole('link', { name: 'Login' })).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('link', { name: 'Add Ads' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'My Ads' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Log Out' })).toBeNull();
  });

  it('gives members their pages and the post-ad action', async () => {
    const user = userEvent.setup();
    render(<MobileMenu signedIn />);

    await user.click(screen.getByRole('button', { name: 'Open menu' }));

    expect(await screen.findByRole('link', { name: 'My Ads' })).toHaveAttribute('href', '/account/ads');
    expect(screen.getByRole('link', { name: 'My orders' })).toHaveAttribute('href', '/account/orders');
    expect(screen.getByRole('link', { name: 'Wallet' })).toHaveAttribute('href', '/account/wallet');
    expect(screen.getByRole('link', { name: 'Add Ads' })).toHaveAttribute('href', '/post-ad');
    expect(screen.queryByRole('link', { name: 'Sign Up' })).toBeNull();
  });

  it('signs a signed-in user out and lands on the login page', async () => {
    const user = userEvent.setup();
    render(<MobileMenu signedIn />);

    await user.click(screen.getByRole('button', { name: 'Open menu' }));
    await user.click(await screen.findByRole('button', { name: 'Log Out' }));

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/login'));
    expect(logout).toHaveBeenCalledOnce();
    expect(screen.queryByRole('link', { name: 'Login' })).toBeNull();
  });
});
