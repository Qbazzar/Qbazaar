/**
 * TanStack Query mutations for "Buy Now" purchase requests. The request is a
 * card in the chat, so every change refreshes that conversation's transcript
 * and the inbox previews; an acceptance also places an order.
 */
import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import type { PurchaseRequest, SavePurchaseRequestPayload } from '@/lib/api/commerce-types';
import {
  acceptPurchaseRequest,
  cancelPurchaseRequest,
  createPurchaseRequest,
  rejectPurchaseRequest,
  updatePurchaseRequest,
} from '@/lib/api/purchase-requests';

import { messagingKeys } from './messaging';
import { orderKeys } from './orders';

function refreshThread(qc: QueryClient, request: PurchaseRequest): void {
  qc.invalidateQueries({ queryKey: messagingKeys.messages(request.conversation_id) });
  qc.invalidateQueries({ queryKey: messagingKeys.lists() });
}

export function useCreatePurchaseRequestMutation(adId: string) {
  const qc = useQueryClient();
  return useMutation<PurchaseRequest, ApiClientError, SavePurchaseRequestPayload>({
    mutationFn: (payload) => createPurchaseRequest(adId, payload),
    onSuccess: (request) => refreshThread(qc, request),
  });
}

export function useUpdatePurchaseRequestMutation() {
  const qc = useQueryClient();
  return useMutation<PurchaseRequest, ApiClientError, { id: string; payload: SavePurchaseRequestPayload }>({
    mutationFn: ({ id, payload }) => updatePurchaseRequest(id, payload),
    onSuccess: (request) => refreshThread(qc, request),
  });
}

export function useAcceptPurchaseRequestMutation() {
  const qc = useQueryClient();
  return useMutation<PurchaseRequest, ApiClientError, { id: string; idempotencyKey: string }>({
    mutationFn: ({ id, idempotencyKey }) => acceptPurchaseRequest(id, idempotencyKey),
    onSuccess: (request) => {
      refreshThread(qc, request);
      qc.invalidateQueries({ queryKey: orderKeys.lists() });
    },
  });
}

export function useRejectPurchaseRequestMutation() {
  const qc = useQueryClient();
  return useMutation<PurchaseRequest, ApiClientError, string>({
    mutationFn: (id) => rejectPurchaseRequest(id),
    onSuccess: (request) => refreshThread(qc, request),
  });
}

export function useCancelPurchaseRequestMutation() {
  const qc = useQueryClient();
  return useMutation<PurchaseRequest, ApiClientError, string>({
    mutationFn: (id) => cancelPurchaseRequest(id),
    onSuccess: (request) => refreshThread(qc, request),
  });
}
