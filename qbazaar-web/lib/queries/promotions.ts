/**
 * TanStack Query hooks for paid promotion. The catalogue is public and
 * admin-priced, so it is cached briefly; a wallet purchase also moves money.
 */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import type { AdPromotion, PromotionOffer, PromotionPurchasePayload } from '@/lib/api/commerce-types';
import { listMyPromotions, listPromotionOffers, purchasePromotion } from '@/lib/api/promotions';
import { useIsAuthenticated } from '@/hooks/useIsAuthenticated';

import { adKeys } from './ads';
import { walletKeys } from './wallet';

const MINUTE = 60 * 1000;

export const promotionKeys = {
  all: ['promotions'] as const,
  catalogue: () => [...promotionKeys.all, 'catalogue'] as const,
  mine: () => [...promotionKeys.all, 'mine'] as const,
};

export function usePromotionOffersQuery() {
  return useQuery<PromotionOffer[], ApiClientError>({
    queryKey: promotionKeys.catalogue(),
    queryFn: () => listPromotionOffers(),
    staleTime: 5 * MINUTE,
  });
}

export function useMyPromotionsQuery() {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: promotionKeys.mine(),
    queryFn: ({ pageParam }) => listMyPromotions(pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: MINUTE / 4,
  });
}

export function usePurchasePromotionMutation(adId: string) {
  const qc = useQueryClient();
  return useMutation<AdPromotion, ApiClientError, { payload: PromotionPurchasePayload; idempotencyKey: string }>({
    mutationFn: ({ payload, idempotencyKey }) => purchasePromotion(adId, payload, idempotencyKey),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: promotionKeys.mine() });
      qc.invalidateQueries({ queryKey: walletKeys.all });
      qc.invalidateQueries({ queryKey: adKeys.myLists() });
      qc.invalidateQueries({ queryKey: adKeys.detail(adId) });
    },
  });
}
