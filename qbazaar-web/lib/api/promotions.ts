/**
 * Paid promotion (M1b, BE-14.40): the admin-priced catalogue, buying one for
 * an ad from the wallet or by bank transfer, and the caller's promotions.
 */
import { api } from './client';
import type { AdPromotion, CursorPage, PromotionOffer, PromotionPurchasePayload } from './commerce-types';
import { idempotent, query, unwrap, unwrapBody } from './request';
import type { SuccessEnvelope } from './types';

export function listPromotionOffers(): Promise<PromotionOffer[]> {
  return unwrap(api.get<SuccessEnvelope<PromotionOffer[]>>('/api/v1/promotions'));
}

export function purchasePromotion(
  adId: string,
  payload: PromotionPurchasePayload,
  idempotencyKey: string,
): Promise<AdPromotion> {
  return unwrap(
    api.post<SuccessEnvelope<AdPromotion>>(
      `/api/v1/ads/${encodeURIComponent(adId)}/promotions`,
      payload,
      idempotent(idempotencyKey),
    ),
  );
}

export function listMyPromotions(cursor?: string | null): Promise<CursorPage<AdPromotion>> {
  return unwrapBody(api.get<CursorPage<AdPromotion>>('/api/v1/account/promotions', { params: query({ cursor }) }));
}
