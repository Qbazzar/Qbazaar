import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

const pathname = vi.hoisted(() => ({ current: '/' }));
const unread = vi.hoisted(() => ({ messages: 0, notifications: 0 }));

vi.mock('next/navigation', () => ({
  usePathname: () => pathname.current,
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock('@/lib/echo/useUserChannel', () => ({ useUserChannel: vi.fn() }));
vi.mock('@/lib/queries/messaging', () => ({
  useUnreadCountQuery: () => ({ data: { total: unread.messages } }),
}));
vi.mock('@/lib/queries/notifications', () => ({
  useUnreadNotificationsCountQuery: () => ({ data: { total: unread.notifications } }),
}));

import { SiteHeader, SiteHeaderGate } from './SiteHeader';

function signIn() {
  useAuthStore.setState({
    user: { id: 'u1', full_name: 'Farah Alzinati', email: 'farah@example.com' } as User,
    accessToken: 'token',
    isHydrated: true,
  });
}

beforeEach(() => {
  setClientLocale('en');
  pathname.current = '/';
  unread.messages = 0;
  unread.notifications = 0;
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
});

describe('SiteHeader', () => {
  it('offers guests the post-ad action, login and sign up', () => {
    render(<SiteHeader />);

    expect(screen.getByRole('link', { name: 'QBazaar' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Add Ads' })).toHaveAttribute('href', '/post-ad');
    expect(screen.getByRole('link', { name: 'Login' })).toHaveAttribute('href', '/login');
    expect(screen.getByRole('link', { name: 'Sign Up' })).toHaveAttribute('href', '/register');
    expect(screen.queryByRole('link', { name: 'Account' })).toBeNull();
  });

  it('shows the account avatar and announces unread counts when signed in', () => {
    signIn();
    unread.messages = 3;
    render(<SiteHeader />);

    expect(screen.getByRole('link', { name: 'Account' })).toHaveAttribute('href', '/account');
    expect(screen.getByRole('img', { name: 'Farah Alzinati' })).toHaveTextContent('FA');
    expect(screen.getByRole('link', { name: 'Messages (3 unread)' })).toHaveAttribute('href', '/account/messages');
    expect(screen.queryByRole('link', { name: 'Login' })).toBeNull();
  });

  it('shows guests no unread markers', () => {
    unread.notifications = 5;
    render(<SiteHeader />);

    expect(screen.getAllByRole('link', { name: 'Notifications' })).not.toHaveLength(0);
    expect(screen.queryByRole('link', { name: /unread/ })).toBeNull();
  });
});

describe('SiteHeaderGate', () => {
  it.each([
    ['/', true],
    ['/ads/1', true],
    ['/login', false],
    ['/post-ad/details', false],
  ])('on %s shows the header: %s', (path, shown) => {
    pathname.current = path;
    render(<SiteHeaderGate />);

    expect(screen.queryByRole('banner') !== null).toBe(shown);
  });
});
