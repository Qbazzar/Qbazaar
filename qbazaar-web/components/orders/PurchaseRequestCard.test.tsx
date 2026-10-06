import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ getEcho: vi.fn().mockResolvedValue(null) }));
vi.mock('@/lib/api/orders', () => ({ getOrder: vi.fn() }));
vi.mock('@/lib/api/purchase-requests', () => ({
  acceptPurchaseRequest: vi.fn(),
  rejectPurchaseRequest: vi.fn(),
  cancelPurchaseRequest: vi.fn(),
  updatePurchaseRequest: vi.fn(),
  createPurchaseRequest: vi.fn(),
}));

import { toast } from 'sonner';

import { ApiClientError } from '@/lib/api/auth';
import { getOrder } from '@/lib/api/orders';
import {
  acceptPurchaseRequest,
  cancelPurchaseRequest,
  rejectPurchaseRequest,
  updatePurchaseRequest,
} from '@/lib/api/purchase-requests';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildOrder, buildPurchaseRequest } from '@/lib/orders/test-fixtures';
import type { User } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';

import { PurchaseRequestCard } from './PurchaseRequestCard';
import { renderWithClient } from './test-utils';

const ad = { title: '200 L aquarium with fish', thumbUrl: null };

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'seller-1' } as User, accessToken: 'AT', isHydrated: true });
});

describe('PurchaseRequestCard', () => {
  it('lets the seller accept a pending request after confirming', async () => {
    const request = buildPurchaseRequest();
    vi.mocked(acceptPurchaseRequest).mockResolvedValue({ ...request, status: 'accepted', order_id: 'order-1' });
    renderWithClient(<PurchaseRequestCard request={request} role="seller" ad={ad} align="start" />);

    expect(screen.getByRole('article', { name: /Purchase request/ })).toBeInTheDocument();
    expect(screen.getByText('Pending')).toBeInTheDocument();
    expect(screen.getByText('Can I pick it up this evening?')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Accept request' }));
    const dialog = await screen.findByRole('dialog', { name: 'Accept this request?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Accept and place order' }));

    await waitFor(() => expect(acceptPurchaseRequest).toHaveBeenCalledWith('pr-1', expect.any(String)));
    expect(toast.success).toHaveBeenCalledWith('Request accepted. The order is placed.');
  });

  it('declines straight away and reports API refusals', async () => {
    vi.mocked(rejectPurchaseRequest).mockRejectedValue(
      new ApiClientError({ status: 422, code: 'PURCHASE_003', messageKey: 'x', message: 'Not pending' }),
    );
    renderWithClient(<PurchaseRequestCard request={buildPurchaseRequest()} role="seller" ad={ad} align="start" />);

    await userEvent.click(screen.getByRole('button', { name: 'Decline' }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith('This request has already been answered.'));
  });

  it('lets the buyer edit the request and validates the quantity', async () => {
    const request = buildPurchaseRequest({ viewer_role: 'buyer' });
    vi.mocked(updatePurchaseRequest).mockResolvedValue({ ...request, quantity: 2 });
    renderWithClient(<PurchaseRequestCard request={request} role="buyer" ad={ad} align="end" />);

    expect(screen.getByText('Pending')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Edit request' }));

    const quantity = await screen.findByLabelText(/Quantity/);
    await userEvent.clear(quantity);
    await userEvent.type(quantity, '0');
    await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(await screen.findByText('Choose a quantity between 1 and 999.')).toBeInTheDocument();
    expect(updatePurchaseRequest).not.toHaveBeenCalled();

    await userEvent.clear(quantity);
    await userEvent.type(quantity, '2');
    await userEvent.click(screen.getByRole('button', { name: 'Save changes' }));

    await waitFor(() =>
      expect(updatePurchaseRequest).toHaveBeenCalledWith('pr-1', { quantity: 2, note: 'Can I pick it up this evening?' }),
    );
  });

  it('lets the buyer cancel after confirming', async () => {
    const request = buildPurchaseRequest({ viewer_role: 'buyer' });
    vi.mocked(cancelPurchaseRequest).mockResolvedValue({ ...request, status: 'cancelled', cancelled_by: 'buyer-1' });
    renderWithClient(<PurchaseRequestCard request={request} role="buyer" ad={ad} align="end" />);

    await userEvent.click(screen.getByRole('button', { name: 'Cancel request' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancel your request?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel request' }));

    await waitFor(() => expect(cancelPurchaseRequest).toHaveBeenCalledWith('pr-1'));
  });

  it('sends the buyer of an accepted request to the checkout while the order waits for it', async () => {
    vi.mocked(getOrder).mockResolvedValue(buildOrder({ id: 'order-9', status: 'created' }));
    renderWithClient(
      <PurchaseRequestCard
        request={buildPurchaseRequest({ status: 'accepted', order_id: 'order-9', viewer_role: 'buyer' })}
        role="buyer"
        ad={ad}
        align="end"
      />,
    );

    expect(await screen.findByRole('link', { name: 'Complete checkout' })).toHaveAttribute('href', '/checkout/order-9');
    expect(screen.getByRole('link', { name: 'View order' })).toHaveAttribute('href', '/account/orders/order-9');
    expect(screen.getByText('Accepted. Complete the checkout to confirm your order.')).toBeInTheDocument();
    expect(getOrder).toHaveBeenCalledWith('order-9');
  });

  it('stops offering the checkout once the buyer has checked out', async () => {
    vi.mocked(getOrder).mockResolvedValue(buildOrder({ id: 'order-9', status: 'awaiting_handover' }));
    renderWithClient(
      <PurchaseRequestCard
        request={buildPurchaseRequest({ status: 'accepted', order_id: 'order-9', viewer_role: 'seller' })}
        role="seller"
        ad={ad}
        align="start"
      />,
    );

    expect(await screen.findByText('The buyer checked out. Hand over the item, then confirm it in the order.')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Complete checkout' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'View order' })).toBeInTheDocument();
  });

  it.each([
    [{ status: 'paid' as const, order_id: 'order-9' }, 'Sale completed: QAR 1,550.00'],
    [{ status: 'rejected' as const }, 'The seller declined this request.'],
    [{ status: 'cancelled' as const, cancelled_by: null }, 'Closed: the item was sold or is no longer available.'],
    [{ status: 'cancelled' as const, cancelled_by: 'buyer-1' }, 'The buyer cancelled this request.'],
  ])('explains a closed request (%o)', (overrides, line) => {
    renderWithClient(<PurchaseRequestCard request={buildPurchaseRequest(overrides)} role="seller" ad={ad} align="start" />);

    expect(screen.getByText(line)).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('shows the unit price when more than one is requested', () => {
    renderWithClient(
      <PurchaseRequestCard
        request={buildPurchaseRequest({ quantity: 2, unit_price: '775.00', total: '1550.00' })}
        role="seller"
        ad={ad}
        align="start"
      />,
    );

    expect(screen.getByText('2 × QAR 775.00')).toBeInTheDocument();
  });
});
