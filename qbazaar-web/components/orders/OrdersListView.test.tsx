import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NuqsTestingAdapter } from 'nuqs/adapters/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api/orders', () => ({ listOrders: vi.fn() }));

import type { Order } from '@/lib/api/commerce-types';
import { listOrders } from '@/lib/api/orders';
import type { User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { buildOrder } from '@/lib/orders/test-fixtures';
import { useAuthStore } from '@/store/auth';

import { OrdersListView } from './OrdersListView';

function page(data: Order[], nextCursor: string | null = null) {
  return { success: true as const, data, meta: { per_page: 20, has_more: nextCursor !== null, next_cursor: nextCursor } };
}

function renderList(search = '') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <NuqsTestingAdapter searchParams={search}>
      <QueryClientProvider client={client}>
        <OrdersListView />
      </QueryClientProvider>
    </NuqsTestingAdapter>,
  );
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  useAuthStore.setState({ user: { id: 'buyer-1' } as User, accessToken: 'AT', isHydrated: true });
});

describe('OrdersListView', () => {
  it('lists purchases with their status and total, and loads the next page', async () => {
    vi.mocked(listOrders)
      .mockResolvedValueOnce(page([buildOrder({ id: '01ORDERAAAAAAAAAAAAA1', fulfillment: 'delivery', status: 'awaiting_handover' })], 'cursor-2'))
      .mockResolvedValueOnce(page([buildOrder({ id: '01ORDERBBBBBBBBBBBBB2', ad: { id: 'ad-9', title: 'Rolex Submariner' } })]));
    renderList();

    const link = await screen.findByRole('link', { name: /200 L aquarium with fish.*Open order AAAAAAA1/ });
    expect(link).toHaveAttribute('href', '/account/orders/01ORDERAAAAAAAAAAAAA1');
    expect(screen.getAllByText('Awaiting handover').length).toBeGreaterThan(0);
    expect(screen.getByText('Cash · Delivery')).toBeInTheDocument();
    expect(listOrders).toHaveBeenCalledWith({ role: 'buyer', status: undefined, cursor: null });

    await userEvent.click(screen.getByRole('button', { name: 'Load more' }));
    expect(await screen.findByText('Rolex Submariner')).toBeInTheDocument();
    expect(listOrders).toHaveBeenLastCalledWith({ role: 'buyer', status: undefined, cursor: 'cursor-2' });
  });

  it('switches to sales and filters by status', async () => {
    vi.mocked(listOrders).mockResolvedValue(page([buildOrder({ viewer_role: 'seller', status: 'completed' })]));
    renderList();

    await userEvent.click(await screen.findByRole('tab', { name: 'Sales' }));
    await waitFor(() => expect(listOrders).toHaveBeenCalledWith({ role: 'seller', status: undefined, cursor: null }));

    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'completed');
    await waitFor(() => expect(listOrders).toHaveBeenCalledWith({ role: 'seller', status: 'completed', cursor: null }));
  });

  it('shows an empty state that fits the tab', async () => {
    vi.mocked(listOrders).mockResolvedValue(page([]));
    renderList('?role=seller');

    expect(await screen.findByRole('heading', { name: 'No sales yet' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'My ads' })).toHaveAttribute('href', '/account/ads');
  });
});
