import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/api/follows', () => ({ listFollows: vi.fn() }));
vi.mock('@/lib/api/users', () => ({ getPublicProfile: vi.fn(), followUser: vi.fn(), unfollowUser: vi.fn() }));

import { toast } from 'sonner';
import { listFollows, type FollowListUser } from '@/lib/api/follows';
import { followUser, getPublicProfile, unfollowUser } from '@/lib/api/users';
import type { PublicUserProfile, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { ConnectionsView } from './ConnectionsView';

const mariah: FollowListUser = {
  id: 'u1',
  full_name: 'Mariah Karim',
  avatar_url: null,
  account_type: 'private',
  followers_count: 12,
  is_following: true,
  followed_at: '2026-10-01T10:00:00Z',
};

function page(rows: FollowListUser[]) {
  return { success: true as const, data: rows, meta: { per_page: 20, has_more: false, next_cursor: null } };
}

function renderView(kind: 'following' | 'followers') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<ConnectionsView kind={kind} />, { wrapper });
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'me' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(getPublicProfile).mockResolvedValue({ id: 'me', followers_count: 4, following_count: 6 } as PublicUserProfile);
});

describe('ConnectionsView', () => {
  it('shows both tabs with their counts and marks the open one', async () => {
    vi.mocked(listFollows).mockResolvedValue(page([mariah]));
    renderView('following');

    const following = await screen.findByRole('link', { name: /Following\s*6/ });
    expect(following).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: /Followers\s*4/ })).toHaveAttribute('href', '/account/followers');
    expect(await screen.findByText('Mariah Karim', { selector: 'bdi' })).toBeInTheDocument();
    expect(listFollows).toHaveBeenCalledWith('following', null);
  });

  it('unfollows from "Following" and drops the card', async () => {
    vi.mocked(listFollows).mockResolvedValue(page([mariah]));
    vi.mocked(unfollowUser).mockResolvedValue({ following: false, user_id: 'u1', followers_count: 11 });
    renderView('following');

    await userEvent.click(await screen.findByRole('button', { name: /Following Mariah Karim/ }));

    await waitFor(() => expect(unfollowUser).toHaveBeenCalledWith('u1'));
    expect(toast.success).toHaveBeenCalledWith('Unfollowed Mariah Karim');
    await waitFor(() => expect(screen.queryByText('Mariah Karim', { selector: 'bdi' })).toBeNull());
  });

  it('follows back from "Followers"', async () => {
    vi.mocked(listFollows).mockResolvedValue(page([{ ...mariah, is_following: false }]));
    vi.mocked(followUser).mockResolvedValue({ following: true, user_id: 'u1', followers_count: 13 });
    renderView('followers');

    await userEvent.click(await screen.findByRole('button', { name: /Follow back Mariah Karim/ }));

    await waitFor(() => expect(followUser).toHaveBeenCalledWith('u1'));
    expect(toast.success).toHaveBeenCalledWith('You now follow Mariah Karim');
    expect(await screen.findByRole('button', { name: /Following Mariah Karim/ })).toBeInTheDocument();
  });

  it('opens the messages page from the message button', async () => {
    vi.mocked(listFollows).mockResolvedValue(page([mariah]));
    renderView('following');

    expect(await screen.findByRole('link', { name: 'Message Mariah Karim' })).toHaveAttribute('href', '/account/messages');
  });

  it('has its own empty state per tab', async () => {
    vi.mocked(listFollows).mockResolvedValue(page([]));
    renderView('followers');

    expect(await screen.findByRole('heading', { name: "You don't have any followers yet" })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Browse marketplace' })).toHaveAttribute('href', '/categories');
  });
});
