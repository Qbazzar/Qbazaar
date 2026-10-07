import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() } }));
vi.mock('@/lib/api/messaging', () => ({ startConversation: vi.fn() }));
vi.mock('@/lib/api/offers', () => ({ makeOffer: vi.fn() }));

import { ApiClientError } from '@/lib/api/auth';
import type { DealAd } from '@/lib/api/commerce-types';
import { startConversation } from '@/lib/api/messaging';
import { makeOffer } from '@/lib/api/offers';
import type { Conversation, Offer, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { MakeOfferForm } from './MakeOfferForm';
import { renderWithClient } from './test-utils';

const ad = {
  id: 'ad-2',
  user_id: 'seller-1',
  title: 'Sony WH-1000XM5',
  status: 'active',
  price: 955,
  price_type: 'negotiable',
  currency: 'QAR',
} as DealAd;

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'buyer-1', phone_verified: true } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(startConversation).mockResolvedValue({ id: 'conv-3' } as Conversation);
});

describe('MakeOfferForm', () => {
  it('fills the amount from a suggestion and sends it as an exact decimal', async () => {
    vi.mocked(makeOffer).mockResolvedValue({ id: 'offer-9', conversation_id: 'conv-3' } as Offer);
    renderWithClient(<MakeOfferForm ad={ad} />);

    const suggestion = screen.getByRole('button', { name: 'QAR 905, 5% below the asking price' });
    await userEvent.click(suggestion);
    expect(screen.getByLabelText(/Your offer/)).toHaveValue('905');
    expect(suggestion).toHaveAttribute('aria-pressed', 'true');

    await userEvent.click(screen.getByRole('button', { name: 'Send offer' }));

    await waitFor(() => expect(makeOffer).toHaveBeenCalledWith('conv-3', { amount: '905.00', note: null }));
    expect(startConversation).toHaveBeenCalledWith('ad-2');
    expect(push).toHaveBeenCalledWith('/account/messages?c=conv-3');
  });

  it('requires an amount within the offer bounds', async () => {
    renderWithClient(<MakeOfferForm ad={ad} />);

    await userEvent.click(screen.getByRole('button', { name: 'Send offer' }));
    expect(await screen.findByText('Enter an amount.')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText(/Your offer/), '0.5');
    await userEvent.click(screen.getByRole('button', { name: 'Send offer' }));
    expect(await screen.findByText('The amount must be at least QAR 1.00.')).toBeInTheDocument();
    expect(startConversation).not.toHaveBeenCalled();
  });

  it('reports an open offer the buyer already has', async () => {
    vi.mocked(makeOffer).mockRejectedValue(new ApiClientError({ status: 422, code: 'OFFER_005', messageKey: 'x', message: 'Exists' }));
    renderWithClient(<MakeOfferForm ad={ad} />);

    await userEvent.type(screen.getByLabelText(/Your offer/), '900');
    await userEvent.click(screen.getByRole('button', { name: 'Send offer' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('You already have an open offer on this ad.');
  });
});
