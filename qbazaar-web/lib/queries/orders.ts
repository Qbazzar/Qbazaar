/**
 * TanStack Query hooks for orders and checkout. Lists are cursor-paginated
 * infinite queries; every state change invalidates the order, the lists and
 * the chat transcripts (the purchase-request card follows the order).
 */
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
} from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import type { Checkout, CheckoutPayload, DealRole, Order, OrderStatus } from '@/lib/api/commerce-types';
import {
  cancelOrder,
  confirmHandover,
  getCheckout,
  getOrder,
  listOrders,
  reportOrderProblem,
  submitCheckout,
} from '@/lib/api/orders';
import { useIsAuthenticated } from '@/hooks/useIsAuthenticated';

import { messagingKeys } from './messaging';
import { walletKeys } from './wallet';

const SECOND = 1000;

export const orderKeys = {
  all: ['orders'] as const,
  lists: () => [...orderKeys.all, 'list'] as const,
  list: (role: DealRole, status?: OrderStatus) => [...orderKeys.lists(), role, status ?? 'all'] as const,
  detail: (id: string) => [...orderKeys.all, 'detail', id] as const,
  checkout: (id: string) => [...orderKeys.all, 'checkout', id] as const,
};

export function useOrdersQuery(role: DealRole, status?: OrderStatus) {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: orderKeys.list(role, status),
    queryFn: ({ pageParam }) => listOrders({ role, status, cursor: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: 15 * SECOND,
  });
}

export function useOrderQuery(id: string) {
  const enabled = useIsAuthenticated();
  return useQuery<Order, ApiClientError>({
    queryKey: orderKeys.detail(id),
    queryFn: () => getOrder(id),
    enabled: enabled && Boolean(id),
    staleTime: 10 * SECOND,
  });
}

export function useCheckoutQuery(orderId: string) {
  const enabled = useIsAuthenticated();
  return useQuery<Checkout, ApiClientError>({
    queryKey: orderKeys.checkout(orderId),
    queryFn: () => getCheckout(orderId),
    enabled: enabled && Boolean(orderId),
    staleTime: 10 * SECOND,
    retry: false,
  });
}

function settleOrder(qc: QueryClient, order: Order): void {
  qc.setQueryData(orderKeys.detail(order.id), order);
  qc.invalidateQueries({ queryKey: orderKeys.lists() });
  qc.invalidateQueries({ queryKey: orderKeys.checkout(order.id) });
  qc.invalidateQueries({ queryKey: [...messagingKeys.all, 'messages'] });
}

interface WithKey {
  idempotencyKey: string;
}

export function useSubmitCheckoutMutation(orderId: string) {
  const qc = useQueryClient();
  return useMutation<Order, ApiClientError, WithKey & { payload: CheckoutPayload }>({
    mutationFn: ({ payload, idempotencyKey }) => submitCheckout(orderId, payload, idempotencyKey),
    onSuccess: (order) => settleOrder(qc, order),
  });
}

export function useConfirmHandoverMutation(orderId: string) {
  const qc = useQueryClient();
  return useMutation<Order, ApiClientError, WithKey>({
    mutationFn: ({ idempotencyKey }) => confirmHandover(orderId, idempotencyKey),
    onSuccess: (order) => {
      settleOrder(qc, order);
      qc.invalidateQueries({ queryKey: walletKeys.all });
    },
  });
}

export function useCancelOrderMutation(orderId: string) {
  const qc = useQueryClient();
  return useMutation<Order, ApiClientError, WithKey & { reason: string | null }>({
    mutationFn: ({ reason, idempotencyKey }) => cancelOrder(orderId, reason, idempotencyKey),
    onSuccess: (order) => settleOrder(qc, order),
  });
}

export function useReportProblemMutation(orderId: string) {
  const qc = useQueryClient();
  return useMutation<Order, ApiClientError, WithKey & { reason: string }>({
    mutationFn: ({ reason, idempotencyKey }) => reportOrderProblem(orderId, reason, idempotencyKey),
    onSuccess: (order) => settleOrder(qc, order),
  });
}
