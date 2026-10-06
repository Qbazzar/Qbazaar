import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/api/users', () => ({ createReview: vi.fn() }));

import { toast } from 'sonner';
import { ApiClientError } from '@/lib/api/auth';
import { createReview } from '@/lib/api/users';
import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { ReviewSellerButton } from './ReviewSellerButton';

function renderButton() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<ReviewSellerButton adId="ad-1" sellerId="seller" />, { wrapper });
}

async function submitFourStars() {
  await userEvent.click(screen.getByRole('button', { name: 'Rate the seller' }));
  await userEvent.click(await screen.findByRole('button', { name: '4 of 5 stars' }));
  await userEvent.click(screen.getByRole('button', { name: 'Submit review' }));
}

beforeEach(() => {
  setClientLocale('en');
  useAuthStore.setState({ user: { id: 'buyer' } as User, accessToken: 'AT', isHydrated: true });
  vi.clearAllMocks();
});

describe('ReviewSellerButton', () => {
  it('is hidden from guests and from the seller', () => {
    useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
    const { unmount } = renderButton();
    expect(screen.queryByRole('button', { name: 'Rate the seller' })).toBeNull();
    unmount();

    useAuthStore.setState({ user: { id: 'seller' } as User, accessToken: 'AT', isHydrated: true });
    renderButton();
    expect(screen.queryByRole('button', { name: 'Rate the seller' })).toBeNull();
  });

  it('sends the rating', async () => {
    vi.mocked(createReview).mockResolvedValue({ id: 'r1', rating: 4, comment: null, created_at: '2026-10-01T10:00:00Z' });
    renderButton();

    await submitFourStars();

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Thanks, your review was sent'));
    expect(createReview).toHaveBeenCalledWith('ad-1', { rating: 4, comment: null });
  });

  it.each([
    ['REVIEW_001', 'You can rate the seller only after a completed deal (an accepted offer) on this ad'],
    ['REVIEW_002', 'You have already rated this ad'],
    ['REVIEW_003', "You can't rate your own ad"],
  ])('explains the %s refusal', async (code, message) => {
    vi.mocked(createReview).mockRejectedValue(new ApiClientError({ status: 403, code, messageKey: 'x', message: 'Refused' }));
    renderButton();

    await submitFourStars();

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(message));
  });
});
