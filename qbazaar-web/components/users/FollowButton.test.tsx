import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), info: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/api/users', () => ({ followUser: vi.fn(), unfollowUser: vi.fn() }));

import { toast } from 'sonner';
import { ApiClientError } from '@/lib/api/auth';
import { followUser, unfollowUser } from '@/lib/api/users';
import type { PublicUserProfile, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { userKeys } from '@/lib/queries/users';
import { useAuthStore } from '@/store/auth';

import { FollowButton } from './FollowButton';

let queryClient: QueryClient;

function wrapper({ children }: { children: ReactNode }) {
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

function signIn(id = 'viewer') {
  useAuthStore.setState({ user: { id } as User, accessToken: 'AT', isHydrated: true });
}

beforeEach(() => {
  setClientLocale('en');
  queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
  vi.clearAllMocks();
});

describe('FollowButton', () => {
  it('sends guests to login and back', async () => {
    render(<FollowButton userId="seller" name="BonTon" isFollowing={false} stateKnown={false} />, { wrapper });

    await userEvent.click(screen.getByRole('button', { name: 'Follow BonTon' }));

    const here = `${window.location.pathname}${window.location.search}`;
    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent(here)}`);
    expect(followUser).not.toHaveBeenCalled();
  });

  it('is not shown on your own profile', () => {
    signIn('seller');
    render(<FollowButton userId="seller" name="BonTon" isFollowing={false} stateKnown />, { wrapper });

    expect(screen.queryByRole('button')).toBeNull();
  });

  it('waits until the viewer’s follow state is known', () => {
    signIn();
    render(<FollowButton userId="seller" name="BonTon" isFollowing={false} stateKnown={false} />, { wrapper });

    expect(screen.getByRole('button', { name: 'Follow BonTon' })).toBeDisabled();
  });

  it('follows and writes the new state into the cached profile', async () => {
    signIn();
    const key = userKeys.profile('seller', 'viewer');
    queryClient.setQueryData(key, { id: 'seller', is_following: false, followers_count: 4 } as PublicUserProfile);
    vi.mocked(followUser).mockResolvedValue({ following: true, user_id: 'seller', followers_count: 5 });

    render(
      <FollowButton userId="seller" name="BonTon" isFollowing={false} stateKnown label="Follow Seller" />,
      { wrapper },
    );
    await userEvent.click(screen.getByRole('button', { name: 'Follow Seller BonTon' }));

    await waitFor(() =>
      expect(queryClient.getQueryData<PublicUserProfile>(key)).toMatchObject({ is_following: true, followers_count: 5 }),
    );
    expect(followUser).toHaveBeenCalledWith('seller');
  });

  it('unfollows when already following', async () => {
    signIn();
    vi.mocked(unfollowUser).mockResolvedValue({ following: false, user_id: 'seller', followers_count: 3 });

    render(<FollowButton userId="seller" name="BonTon" isFollowing stateKnown />, { wrapper });
    await userEvent.click(screen.getByRole('button', { name: 'Following BonTon' }));

    await waitFor(() => expect(unfollowUser).toHaveBeenCalledWith('seller'));
  });

  it('explains a refused follow', async () => {
    signIn();
    vi.mocked(followUser).mockRejectedValue(
      new ApiClientError({ status: 403, code: 'FOLLOW_002', messageKey: 'errors.follow_blocked', message: 'Blocked' }),
    );

    render(<FollowButton userId="seller" name="BonTon" isFollowing={false} stateKnown />, { wrapper });
    await userEvent.click(screen.getByRole('button', { name: 'Follow BonTon' }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith("You can't follow this user"));
  });
});
