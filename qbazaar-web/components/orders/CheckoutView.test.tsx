import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push, replace: vi.fn() }), usePathname: () => '/checkout/order-1' }));
vi.mock('@/lib/api/orders', () => ({ getCheckout: vi.fn(), submitCheckout: vi.fn() }));

import { ApiClientError } from '@/lib/api/auth';
import { getCheckout, submitCheckout } from '@/lib/api/orders';
import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildCheckout, buildOrder } from '@/lib/orders/test-fixtures';
import { useAuthStore } from '@/store/auth';

import { CheckoutView } from './CheckoutView';
import { renderWithClient } from './test-utils';

const savedAddress = {
  id: 'addr-1',
  label: 'Home',
  full_name: 'Hessa Al Mulla',
  phone: '+97479000003',
  street: 'Al Sadd Street',
  house_number: '12',
  supplement: null,
  city: 'Doha',
  postal_code: null,
  location_id: null,
  is_default: true,
  created_at: '2026-10-01T10:00:00Z',
  updated_at: '2026-10-01T10:00:00Z',
};

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({
    user: { id: 'buyer-1', full_name: 'Hessa Al Mulla', phone: '+97479000003' } as User,
    accessToken: 'AT',
    isHydrated: true,
  });
  vi.mocked(submitCheckout).mockResolvedValue(buildOrder({ id: 'order-1', status: 'awaiting_handover' }));
});

describe('CheckoutView', () => {
  it('confirms a pickup order paid in cash and keeps the confirmation once the order moves on', async () => {
    vi.mocked(getCheckout)
      .mockResolvedValueOnce(buildCheckout({ order: buildOrder({ id: 'order-1' }) }))
      .mockResolvedValue(buildCheckout({ order: buildOrder({ id: 'order-1', status: 'awaiting_handover' }) }));
    renderWithClient(<CheckoutView orderId="order-1" />);

    expect(await screen.findByRole('radio', { name: /Pickup/ })).toBeChecked();
    expect(screen.getByRole('radio', { name: /Cash/ })).toBeChecked();
    expect(screen.getAllByText('QAR 1,550.00')).toHaveLength(2);

    await userEvent.click(screen.getByRole('button', { name: 'Confirm order' }));

    await waitFor(() =>
      expect(submitCheckout).toHaveBeenCalledWith('order-1', { fulfillment: 'pickup', payment_method: 'cash' }, expect.any(String)),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Order confirmed' });
    expect(within(dialog).getByRole('link', { name: 'View order' })).toHaveAttribute('href', '/account/orders/order-1');
    await waitFor(() => expect(getCheckout).toHaveBeenCalledTimes(2));
    expect(screen.getByRole('dialog', { name: 'Order confirmed' })).toBeInTheDocument();
  });

  it('sends a typed delivery address after checking it, with the delivery fee in the total', async () => {
    vi.mocked(getCheckout).mockResolvedValue(buildCheckout({ order: buildOrder({ id: 'order-1' }) }));
    renderWithClient(<CheckoutView orderId="order-1" />);

    await userEvent.click(await screen.findByRole('radio', { name: /Delivery/ }));
    expect(screen.getByText('QAR 1,565.00')).toBeInTheDocument();
    expect(screen.getByLabelText(/Full name/)).toHaveValue('Hessa Al Mulla');

    await userEvent.click(screen.getByRole('button', { name: 'Confirm order' }));
    expect((await screen.findAllByText('This field is required.')).length).toBe(3);
    expect(submitCheckout).not.toHaveBeenCalled();

    await userEvent.click(screen.getByLabelText(/^Street/));
    await userEvent.paste('Al Sadd Street');
    await userEvent.click(screen.getByLabelText(/Building or house number/));
    await userEvent.paste('12');
    await userEvent.click(screen.getByLabelText(/^City/));
    await userEvent.paste('Doha');
    await userEvent.click(screen.getByRole('button', { name: 'Confirm order' }));

    await waitFor(() =>
      expect(submitCheckout).toHaveBeenCalledWith(
        'order-1',
        {
          fulfillment: 'delivery',
          payment_method: 'cash',
          address: {
            full_name: 'Hessa Al Mulla',
            phone: '+97479000003',
            street: 'Al Sadd Street',
            house_number: '12',
            supplement: null,
            city: 'Doha',
            postal_code: null,
          },
        },
        expect.any(String),
      ),
    );
  });

  it('uses the default saved address for delivery', async () => {
    vi.mocked(getCheckout).mockResolvedValue(
      buildCheckout({ order: buildOrder({ id: 'order-1' }), fulfillment_options: ['delivery'], saved_addresses: [savedAddress] }),
    );
    renderWithClient(<CheckoutView orderId="order-1" />);

    expect(await screen.findByRole('radio', { name: /Home · Hessa Al Mulla/ })).toBeChecked();
    await userEvent.click(screen.getByRole('button', { name: 'Confirm order' }));

    await waitFor(() =>
      expect(submitCheckout).toHaveBeenCalledWith(
        'order-1',
        { fulfillment: 'delivery', payment_method: 'cash', address_id: 'addr-1' },
        expect.any(String),
      ),
    );
  });

  it('explains a refusal and lets the buyer try again', async () => {
    vi.mocked(getCheckout).mockResolvedValue(buildCheckout({ order: buildOrder({ id: 'order-1' }) }));
    vi.mocked(submitCheckout).mockRejectedValueOnce(
      new ApiClientError({ status: 422, code: 'ORDER_002', messageKey: 'x', message: 'Invalid transition' }),
    );
    renderWithClient(<CheckoutView orderId="order-1" />);

    await userEvent.click(await screen.findByRole('button', { name: 'Confirm order' }));
    const dialog = await screen.findByRole('dialog', { name: "We couldn't confirm your order" });
    expect(dialog).toHaveTextContent('This order has moved on');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Try again' }));
    await waitFor(() => expect(submitCheckout).toHaveBeenCalledTimes(2));
    const [, , firstKey] = vi.mocked(submitCheckout).mock.calls[0];
    const [, , secondKey] = vi.mocked(submitCheckout).mock.calls[1];
    expect(secondKey).toBe(firstKey);
  });

  it('points the seller and a finished order to the order page', async () => {
    vi.mocked(getCheckout).mockRejectedValueOnce(new ApiClientError({ status: 403, code: 'FORBIDDEN', messageKey: 'x', message: 'No' }));
    const { unmount } = renderWithClient(<CheckoutView orderId="order-1" />);
    expect(await screen.findByText('Only the buyer completes the checkout of this order.')).toBeInTheDocument();
    unmount();

    vi.mocked(getCheckout).mockResolvedValue(buildCheckout({ order: buildOrder({ id: 'order-1', status: 'awaiting_handover' }) }));
    renderWithClient(<CheckoutView orderId="order-1" />);
    expect(await screen.findByText('This order is already checked out.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Confirm order' })).not.toBeInTheDocument();
  });
});
