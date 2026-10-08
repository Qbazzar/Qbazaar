import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));
vi.mock('@/lib/api/orders', () => ({ confirmHandover: vi.fn(), cancelOrder: vi.fn(), reportOrderProblem: vi.fn() }));

import { toast } from 'sonner';

import { cancelOrder, confirmHandover, reportOrderProblem } from '@/lib/api/orders';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildOrder } from '@/lib/orders/test-fixtures';

import { OrderActionsBar } from './OrderActionsBar';
import { renderWithClient } from './test-utils';

const NOW = Date.parse('2026-10-06T12:00:00Z');

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('OrderActionsBar', () => {
  it('lets the seller confirm the handover after reading what it means', async () => {
    const order = buildOrder({
      status: 'awaiting_handover',
      viewer_role: 'seller',
      awaiting_handover_at: '2026-10-05T10:00:00Z',
      commission: { rate: '5.00', amount: '77.50' },
    });
    vi.mocked(confirmHandover).mockResolvedValue({ ...order, status: 'completed' });
    renderWithClient(<OrderActionsBar order={order} now={NOW} />);

    expect(screen.queryByRole('link', { name: 'Complete checkout' })).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Confirm handover' }));
    const dialog = await screen.findByRole('dialog', { name: 'Confirm the handover?' });
    expect(dialog).toHaveTextContent('QAR 1,550.00');
    expect(dialog).toHaveTextContent('QAR 77.50');

    await userEvent.click(within(dialog).getByRole('button', { name: 'Yes, confirm handover' }));

    await waitFor(() => expect(confirmHandover).toHaveBeenCalledWith(order.id, expect.any(String)));
    expect(toast.success).toHaveBeenCalledWith('Handover confirmed. The order is completed.');
  });

  it('lets the buyer report a problem inside the window, with a real reason', async () => {
    const order = buildOrder({ status: 'completed', report_problem_until: '2026-10-07T12:00:00Z' });
    vi.mocked(reportOrderProblem).mockResolvedValue({ ...order, status: 'disputed' });
    renderWithClient(<OrderActionsBar order={order} now={NOW} />);

    await userEvent.click(screen.getByRole('button', { name: 'Report a problem' }));
    const dialog = await screen.findByRole('dialog', { name: 'Report a problem' });
    await userEvent.type(within(dialog).getByRole('textbox'), 'Broken');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Send report' }));
    expect(await within(dialog).findByText('Write at least 10 characters.')).toBeInTheDocument();
    expect(reportOrderProblem).not.toHaveBeenCalled();

    await userEvent.type(within(dialog).getByRole('textbox'), ' glass on arrival');
    await userEvent.click(within(dialog).getByRole('button', { name: 'Send report' }));

    await waitFor(() => expect(reportOrderProblem).toHaveBeenCalledWith(order.id, 'Broken glass on arrival', expect.any(String)));
  });

  it('cancels an open order with an optional reason', async () => {
    const order = buildOrder({ status: 'created' });
    vi.mocked(cancelOrder).mockResolvedValue({ ...order, status: 'cancelled' });
    renderWithClient(<OrderActionsBar order={order} now={NOW} />);

    expect(screen.getByRole('link', { name: 'Complete checkout' })).toHaveAttribute('href', `/checkout/${order.id}`);
    await userEvent.click(screen.getByRole('button', { name: 'Cancel order' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancel this order?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancel order' }));

    await waitFor(() => expect(cancelOrder).toHaveBeenCalledWith(order.id, null, expect.any(String)));
  });

  it('renders nothing when the order needs no action from the viewer', () => {
    const { container } = renderWithClient(<OrderActionsBar order={buildOrder({ status: 'cancelled' })} now={NOW} />);

    expect(container).toBeEmptyDOMElement();
  });
});
