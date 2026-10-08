import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/api/users', () => ({ blockUser: vi.fn() }));

import { toast } from 'sonner';
import { ApiClientError } from '@/lib/api/auth';
import { blockUser } from '@/lib/api/users';
import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { BlockUserButton } from './BlockUserButton';

const onBlocked = vi.fn();

function renderButton() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<BlockUserButton userId="seller" userName="Mark Toro" onBlocked={onBlocked} />, { wrapper });
}

async function confirmBlock() {
  await userEvent.click(screen.getByRole('button', { name: 'Block' }));
  await userEvent.click(await screen.findByRole('button', { name: 'Block User' }));
}

beforeEach(() => {
  setClientLocale('en');
  useAuthStore.setState({ user: { id: 'buyer' } as User, accessToken: 'AT', isHydrated: true });
  vi.clearAllMocks();
});

describe('BlockUserButton', () => {
  it('sends guests to login and back', async () => {
    useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Block' }));

    const here = `${window.location.pathname}${window.location.search}`;
    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent(here)}`);
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('asks first, with "Block User" next to "Cancel"', async () => {
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Block' }));

    expect(await screen.findByRole('dialog', { name: 'Block Mark Toro?' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(blockUser).not.toHaveBeenCalled();
  });

  it('blocks, says so and tells the page', async () => {
    vi.mocked(blockUser).mockResolvedValue(undefined);
    renderButton();

    await confirmBlock();

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('User blocked'));
    expect(blockUser).toHaveBeenCalledWith('seller');
    expect(onBlocked).toHaveBeenCalledOnce();
  });

  it.each([
    ['USER_002', 403, "This account can't be blocked"],
    ['USER_003', 422, "You can't block yourself"],
  ])('explains the %s refusal', async (code, status, message) => {
    vi.mocked(blockUser).mockRejectedValue(new ApiClientError({ status, code, messageKey: 'x', message: 'Refused' }));
    renderButton();

    await confirmBlock();

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(message));
    expect(onBlocked).not.toHaveBeenCalled();
  });
});
