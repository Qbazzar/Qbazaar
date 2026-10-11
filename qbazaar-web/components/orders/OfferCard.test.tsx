import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/echo/client', () => ({ getEcho: vi.fn().mockResolvedValue(null) }));
vi.mock('@/lib/api/orders', () => ({ listOrders: vi.fn() }));
vi.mock('@/lib/api/offers', () => ({
  acceptOffer: vi.fn(),
  rejectOffer: vi.fn(),
  withdrawOffer: vi.fn(),
  counterOffer: vi.fn(),
  makeOffer: vi.fn(),
  listConversationOffers: vi.fn(),
}));

import { showDesignToast } from '@/components/design-system/design-toast';
import { acceptOffer, counterOffer, withdrawOffer } from '@/lib/api/offers';
import { listOrders } from '@/lib/api/orders';
import type { DealOffer, Order } from '@/lib/api/commerce-types';
import type { Offer, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildOffer, buildOrder } from '@/lib/orders/test-fixtures';
import { useAuthStore } from '@/store/auth';

import { OfferCard } from './OfferCard';
import { renderWithClient } from './test-utils';

const ad = { title: 'Sony WH-1000XM5', thumbUrl: null };
const NOW = Date.parse('2026-10-06T10:00:00Z');
const answered = { conversation_id: 'conv-1', ad_id: 'ad-1' } as Offer;
const countered = { conversation_id: 'conv-1' } as DealOffer;

function ordersPage(orders: Order[]) {
  return { success: true as const, data: orders, meta: { has_more: false, next_cursor: null } } as never;
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'buyer-1' } as User, accessToken: 'AT', isHydrated: true });
  vi.mocked(listOrders).mockResolvedValue(ordersPage([]));
});

describe('OfferCard', () => {
  it('lets the responder accept after confirming', async () => {
    vi.mocked(acceptOffer).mockResolvedValue(answered);
    renderWithClient(<OfferCard offer={buildOffer()} role="seller" ad={ad} listedPrice="QAR 955" align="start" now={NOW} />);

    expect(screen.getByText('QAR 900.00')).toBeInTheDocument();
    expect(screen.getByText('Listed at QAR 955')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Accept Request' }));
    const dialog = await screen.findByRole('dialog', { name: 'Accept this offer?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Accept offer' }));

    await waitFor(() => expect(acceptOffer).toHaveBeenCalledWith('offer-1'));
    expect(showDesignToast).toHaveBeenCalledWith('Offer accepted. The order is placed.');
  });

  it('sends a validated counter-offer as an exact decimal', async () => {
    vi.mocked(counterOffer).mockResolvedValue(countered);
    renderWithClient(<OfferCard offer={buildOffer()} role="seller" ad={ad} listedPrice={null} align="start" now={NOW} />);

    await userEvent.click(screen.getByRole('button', { name: 'Counter' }));
    const amount = await screen.findByLabelText(/Your price/);
    await userEvent.type(amount, '12.345');
    await userEvent.click(screen.getByRole('button', { name: 'Send counter-offer' }));
    expect(await screen.findByText('Enter an amount like 1500 or 1500.50.')).toBeInTheDocument();

    await userEvent.clear(amount);
    await userEvent.type(amount, '١٬٢٥٠');
    await userEvent.click(screen.getByRole('button', { name: 'Send counter-offer' }));

    await waitFor(() => expect(counterOffer).toHaveBeenCalledWith('offer-1', { amount: '1250.00', note: null }));
  });

  it('lets the proposer cancel a pending offer', async () => {
    vi.mocked(withdrawOffer).mockResolvedValue(answered);
    renderWithClient(
      <OfferCard offer={buildOffer({ viewer_role: 'buyer' })} role="buyer" ad={ad} listedPrice={null} align="end" now={NOW} />,
    );

    expect(screen.getByText('Pending')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Accept Request' })).not.toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(1);
    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    const dialog = await screen.findByRole('dialog', { name: 'Withdraw your offer?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Withdraw offer' }));

    await waitFor(() => expect(withdrawOffer).toHaveBeenCalledWith('offer-1'));
  });

  it('titles a counter-offer and says who wrote the note', () => {
    renderWithClient(
      <OfferCard
        offer={buildOffer({ proposed_by: 'seller', counter_round: 1, note: 'Lowest I can go.' })}
        role="buyer"
        ad={ad}
        listedPrice={null}
        align="start"
        now={NOW}
      />,
    );

    expect(screen.getByRole('article', { name: /Counter Offer/ })).toBeInTheDocument();
    expect(screen.getByText('Message from Seller')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Counter' })).toBeInTheDocument();
  });

  it('warns the responder when the offer expires within a day', () => {
    renderWithClient(
      <OfferCard
        offer={buildOffer({ expires_at: '2026-10-06T15:30:00Z' })}
        role="seller"
        ad={ad}
        listedPrice={null}
        align="start"
        now={NOW}
      />,
    );

    expect(screen.getByText('Expires in 6 hours')).toBeInTheDocument();
  });

  it('sends the buyer of an accepted offer to pay its order', async () => {
    vi.mocked(listOrders).mockResolvedValue(
      ordersPage([buildOrder({ id: 'order-7', source: 'offer', source_id: 'offer-1', status: 'created' })]),
    );
    renderWithClient(
      <OfferCard offer={buildOffer({ status: 'accepted' })} role="buyer" ad={ad} listedPrice={null} align="end" now={NOW} />,
    );

    expect(await screen.findByRole('link', { name: 'Proceed to payment' })).toHaveAttribute('href', '/checkout/order-7');
    expect(screen.getByText('Accepted')).toBeInTheDocument();
    expect(screen.queryByText('Offer accepted. An order was placed.')).not.toBeInTheDocument();
    expect(listOrders).toHaveBeenCalledWith({ role: 'buyer', status: undefined, cursor: null });
  });

  it('tells the seller of an accepted offer that the payment is pending, without a button', async () => {
    vi.mocked(listOrders).mockResolvedValue(
      ordersPage([buildOrder({ id: 'order-7', source: 'offer', source_id: 'offer-1', status: 'created' })]),
    );
    renderWithClient(
      <OfferCard offer={buildOffer({ status: 'accepted' })} role="seller" ad={ad} listedPrice={null} align="start" now={NOW} />,
    );

    expect(await screen.findByText("Request Accepted, Waiting for buyer's payment")).toBeInTheDocument();
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('shows only "Payment Successful" once the order is paid', async () => {
    vi.mocked(listOrders).mockResolvedValue(
      ordersPage([buildOrder({ id: 'order-7', source: 'offer', source_id: 'offer-1', status: 'awaiting_handover' })]),
    );
    renderWithClient(
      <OfferCard offer={buildOffer({ status: 'accepted' })} role="buyer" ad={ad} listedPrice={null} align="end" now={NOW} />,
    );

    expect(await screen.findByText('Payment Successful')).toBeInTheDocument();
    expect(screen.getByText('Paid')).toBeInTheDocument();
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('links an accepted offer to the orders when its order is not in the first page', async () => {
    renderWithClient(
      <OfferCard offer={buildOffer({ status: 'accepted' })} role="buyer" ad={ad} listedPrice={null} align="end" now={NOW} />,
    );

    expect(await screen.findByRole('link', { name: 'View my orders' })).toHaveAttribute('href', '/account/orders?role=buyer');
    expect(screen.getByText('Offer accepted. An order was placed.')).toBeInTheDocument();
  });

  it.each([
    ['countered', 'Answered with a counter-offer.'],
    ['rejected', 'This request was rejected'],
    ['withdrawn', 'This request is no longer active'],
    ['expired', 'This request is no longer active'],
  ] as const)('explains a %s offer without actions', (status, line) => {
    renderWithClient(<OfferCard offer={buildOffer({ status })} role="seller" ad={ad} listedPrice={null} align="start" now={NOW} />);

    expect(screen.getByText(line)).toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});
