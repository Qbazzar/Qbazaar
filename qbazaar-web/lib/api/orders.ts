/**
 * Orders and checkout (M1b, BE-14.35 to BE-14.37). An order is placed when the
 * seller accepts a purchase request or either side accepts an offer; the buyer
 * checks out, the seller confirms the cash handover. State changes carry an
 * idempotency key so a retried click never runs twice.
 */
import { api } from './client';
import type { Checkout, CheckoutPayload, CursorPage, DealRole, Order, OrderStatus } from './commerce-types';
import { idempotent, query, unwrap, unwrapBody } from './request';
import type { SuccessEnvelope } from './types';

const ORDERS = '/api/v1/orders';

export interface ListOrdersParams {
  role: DealRole;
  status?: OrderStatus;
  cursor?: string | null;
}

export function listOrders({ role, status, cursor }: ListOrdersParams): Promise<CursorPage<Order>> {
  return unwrapBody(
    api.get<CursorPage<Order>>('/api/v1/account/orders', { params: query({ role, status, cursor }) }),
  );
}

export function getOrder(id: string): Promise<Order> {
  return unwrap(api.get<SuccessEnvelope<Order>>(`${ORDERS}/${encodeURIComponent(id)}`));
}

export function getCheckout(orderId: string): Promise<Checkout> {
  return unwrap(api.get<SuccessEnvelope<Checkout>>(`${ORDERS}/${encodeURIComponent(orderId)}/checkout`));
}

export function submitCheckout(orderId: string, payload: CheckoutPayload, idempotencyKey: string): Promise<Order> {
  return unwrap(
    api.post<SuccessEnvelope<Order>>(
      `${ORDERS}/${encodeURIComponent(orderId)}/checkout`,
      payload,
      idempotent(idempotencyKey),
    ),
  );
}

export function confirmHandover(orderId: string, idempotencyKey: string): Promise<Order> {
  return unwrap(
    api.post<SuccessEnvelope<Order>>(
      `${ORDERS}/${encodeURIComponent(orderId)}/confirm-handover`,
      undefined,
      idempotent(idempotencyKey),
    ),
  );
}

export function cancelOrder(orderId: string, reason: string | null, idempotencyKey: string): Promise<Order> {
  return unwrap(
    api.post<SuccessEnvelope<Order>>(
      `${ORDERS}/${encodeURIComponent(orderId)}/cancel`,
      { reason },
      idempotent(idempotencyKey),
    ),
  );
}

export function reportOrderProblem(orderId: string, reason: string, idempotencyKey: string): Promise<Order> {
  return unwrap(
    api.post<SuccessEnvelope<Order>>(
      `${ORDERS}/${encodeURIComponent(orderId)}/report-problem`,
      { reason },
      idempotent(idempotencyKey),
    ),
  );
}
