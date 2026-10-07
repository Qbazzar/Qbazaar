/**
 * "Buy Now" purchase requests (M1b, BE-14.34). The buyer asks to buy at the
 * ad's price; the request lives in the chat as a card and the seller's
 * acceptance places the order.
 */
import { api } from './client';
import type { PurchaseRequest, SavePurchaseRequestPayload } from './commerce-types';
import { idempotent, unwrap } from './request';
import type { SuccessEnvelope } from './types';

const REQUESTS = '/api/v1/purchase-requests';

export function createPurchaseRequest(adId: string, payload: SavePurchaseRequestPayload): Promise<PurchaseRequest> {
  return unwrap(
    api.post<SuccessEnvelope<PurchaseRequest>>(`/api/v1/ads/${encodeURIComponent(adId)}/purchase-requests`, payload),
  );
}

export function updatePurchaseRequest(id: string, payload: SavePurchaseRequestPayload): Promise<PurchaseRequest> {
  return unwrap(api.put<SuccessEnvelope<PurchaseRequest>>(`${REQUESTS}/${encodeURIComponent(id)}`, payload));
}

export function acceptPurchaseRequest(id: string, idempotencyKey: string): Promise<PurchaseRequest> {
  return unwrap(
    api.post<SuccessEnvelope<PurchaseRequest>>(
      `${REQUESTS}/${encodeURIComponent(id)}/accept`,
      undefined,
      idempotent(idempotencyKey),
    ),
  );
}

export function rejectPurchaseRequest(id: string): Promise<PurchaseRequest> {
  return unwrap(api.post<SuccessEnvelope<PurchaseRequest>>(`${REQUESTS}/${encodeURIComponent(id)}/reject`));
}

export function cancelPurchaseRequest(id: string): Promise<PurchaseRequest> {
  return unwrap(api.post<SuccessEnvelope<PurchaseRequest>>(`${REQUESTS}/${encodeURIComponent(id)}/cancel`));
}
