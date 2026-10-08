import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), info: vi.fn() } }));
vi.mock('@/lib/api/purchase-requests', () => ({ createPurchaseRequest: vi.fn() }));

import { ApiClientError } from '@/lib/api/auth';
import type { DealAd } from '@/lib/api/commerce-types';
import { createPurchaseRequest } from '@/lib/api/purchase-requests';
import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildPurchaseRequest } from '@/lib/orders/test-fixtures';
import { useAuthStore } from '@/store/auth';

import { BuyNowForm } from './BuyNowForm';
import { renderWithClient } from './test-utils';

const ad = {
  id: 'ad-1',
  user_id: 'seller-1',
  title: '200 L aquarium with fish',
  status: 'active',
  price: 1550,
  price_type: 'fixed',
  currency: 'QAR',
  ad_type: 'offering',
  quantity: 1,
} as DealAd;

function signIn(id = 'buyer-1') {
  useAuthStore.setState({ user: { id, phone_verified: true } as User, accessToken: 'AT', isHydrated: true });
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  signIn();
});

describe('BuyNowForm', () => {
  it('sends the request and opens the chat with the seller', async () => {
    vi.mocked(createPurchaseRequest).mockResolvedValue(buildPurchaseRequest({ conversation_id: 'conv-7' }));
    renderWithClient(<BuyNowForm ad={ad} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Buy Now' })).toBeInTheDocument();
    expect(screen.getByText('Message with seller')).toBeInTheDocument();
    expect(screen.queryByLabelText(/Quantity/)).not.toBeInTheDocument();

    await userEvent.type(screen.getByRole('textbox', { name: /Message/ }), 'Pick up tonight?');
    await userEvent.click(screen.getByRole('button', { name: 'Send request' }));

    await waitFor(() => expect(createPurchaseRequest).toHaveBeenCalledWith('ad-1', { quantity: 1, note: 'Pick up tonight?' }));
    expect(push).toHaveBeenCalledWith('/account/messages?c=conv-7');
  });

  it('asks for a quantity within what the ad offers', async () => {
    vi.mocked(createPurchaseRequest).mockResolvedValue(buildPurchaseRequest());
    renderWithClient(<BuyNowForm ad={{ ...ad, quantity: 3 }} />);

    const quantity = screen.getByLabelText(/Quantity/);
    await userEvent.clear(quantity);
    await userEvent.type(quantity, '4');
    await userEvent.click(screen.getByRole('button', { name: 'Send request' }));

    expect(await screen.findByText('Choose a quantity between 1 and 3.')).toBeInTheDocument();
    expect(createPurchaseRequest).not.toHaveBeenCalled();
  });

  it('shows why the API refused, in place', async () => {
    vi.mocked(createPurchaseRequest).mockRejectedValue(
      new ApiClientError({ status: 422, code: 'PURCHASE_002', messageKey: 'x', message: 'Pending exists' }),
    );
    renderWithClient(<BuyNowForm ad={ad} />);

    await userEvent.click(screen.getByRole('button', { name: 'Send request' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('You already have a pending request on this ad.');
  });

  it('rejects markup in the message before sending', async () => {
    renderWithClient(<BuyNowForm ad={ad} />);

    await userEvent.type(screen.getByRole('textbox', { name: /Message/ }), '<b>hi</b>');
    await userEvent.click(screen.getByRole('button', { name: 'Send request' }));

    expect(await screen.findByText('Please remove the < and > characters.')).toBeInTheDocument();
    expect(createPurchaseRequest).not.toHaveBeenCalled();
  });

  it.each([
    ['own ad', { user_id: 'buyer-1' }, "This is your ad, so you can't buy it or make an offer on it."],
    ['ad without a price', { price_type: 'contact' as const, price: null }, "Buy Now isn't available"],
    ['reserved item', { is_reserved: true }, 'This item is reserved for another buyer at the moment.'],
  ])('explains instead of showing the form for an %s', (_case, overrides, text) => {
    renderWithClient(<BuyNowForm ad={{ ...ad, ...overrides } as DealAd} />);

    expect(screen.getByRole('status')).toHaveTextContent(text);
    expect(screen.queryByRole('button', { name: 'Send request' })).not.toBeInTheDocument();
  });
});
