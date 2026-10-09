import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

    // The name starts with the initials the avatar shows.
    const account = screen.getByRole('link', { name: 'FA, Account' });
    expect(account).toHaveAttribute('href', '/account');
    expect(account).toHaveTextContent('FA');
    expect(screen.getByRole('link', { name: 'Messages (3 unread)' })).toHaveAttribute('href', '/account/messages');
    expect(screen.queryByRole('link', { name: 'Login' })).toBeNull();
  });

  it('opens the account menu under the avatar while the mouse rests on it', async () => {
    const user = userEvent.setup();
    signIn();
    render(<SiteHeader />);
    const avatar = screen.getByRole('link', { name: 'FA, Account' });

    await user.hover(avatar);
    const menu = screen.getByRole('navigation', { name: 'Account menu' });
    expect(avatar).toHaveAttribute('aria-expanded', 'true');
    expect(within(menu).getByText('farah@example.com')).toBeInTheDocument();
    expect(within(menu).getByRole('link', { name: 'Save Search' })).toHaveAttribute('href', '/account/saved-searches');
    expect(within(menu).getByRole('link', { name: 'My Ads' })).toHaveAttribute('href', '/account/ads');
    expect(within(menu).getByRole('link', { name: 'Sales Overview' })).toHaveAttribute('href', '/account/orders');
    expect(within(menu).getByRole('link', { name: 'Account Settings' })).toHaveAttribute('href', '/account');
    expect(within(menu).getByRole('button', { name: 'Log Out' })).toBeInTheDocument();
  });

  it('opens the account menu to the keyboard and closes it on Escape', async () => {
    const user = userEvent.setup();
    signIn();
    render(<SiteHeader />);
    const avatar = screen.getByRole('link', { name: 'FA, Account' });

    // Tabbing (not a bare focus()) fires the focusin events React listens to.
    for (let step = 0; step < 20 && document.activeElement !== avatar; step++) await user.tab();
    expect(avatar).toHaveFocus();
    expect(screen.getByRole('navigation', { name: 'Account menu' })).toBeInTheDocument();
    await user.tab();
    expect(screen.getByRole('link', { name: /Farah Alzinati/ })).toHaveFocus();
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('navigation', { name: 'Account menu' })).toBeNull();
    expect(avatar).toHaveFocus();
  });

  it('draws the Add Ads icon at the reference size', () => {
    render(<SiteHeader />);

    expect(screen.getByRole('link', { name: 'Add Ads' })).toHaveClass('[&_svg]:size-5');
    expect(screen.getByRole('link', { name: 'Add Ads' })).not.toHaveClass('[&_svg]:size-4');
  });

  it('opens the language panel from the globe', async () => {
    const user = userEvent.setup();
    render(<SiteHeader />);

    const [globe] = screen.getAllByRole('button', { name: 'Language' });
    await user.click(globe);

    const panel = screen.getByRole('group', { name: 'Choose your language' });
    expect(within(panel).getByRole('button', { name: 'English' })).toHaveAttribute('aria-current', 'true');
    expect(within(panel).getByRole('button', { name: 'العربية' })).toHaveAttribute('lang', 'ar');
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
    ['/post-ad', true],
    ['/login', false],
    ['/verify-email', false],
  ])('on %s shows the header: %s', (path, shown) => {
    pathname.current = path;
    render(<SiteHeaderGate />);

    expect(screen.queryByRole('banner') !== null).toBe(shown);
  });

  it('starts with a link that skips to the content', () => {
    render(<SiteHeaderGate />);

    expect(screen.getAllByRole('link')[0]).toHaveAccessibleName('Skip to content');
    expect(screen.getAllByRole('link')[0]).toHaveAttribute('href', '#main-content');
  });
});
