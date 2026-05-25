/**
 * TanStack Query hooks for the offers domain (Sprint 9).
 *
 * The offers list is conversation-scoped and short-cached (30s) — the Echo
 * channel broadcasts `offer.*` events on `conversation.{id}` and the
 * realtime hook simply invalidates this key. Each mutation also invalidates
 * the conversation's messages query so the new/updated offer message
 * reconciles into the timeline without a manual cache mutation.
 */
import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query';
import {
  acceptOffer,
  listConversationOffers,
  makeOffer,
  rejectOffer,
  withdrawOffer,
} from '@/lib/api/offers';
import type { ApiClientError } from '@/lib/api/auth';
import type { CreateOfferRequest, Offer } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';
import { messagingKeys } from './messaging';

const SECOND = 1000;

export const offersKeys = {
  all: ['offers'] as const,
  byConversation: (conversationId: string) =>
    [...offersKeys.all, 'conversation', conversationId] as const,
};

// ── Queries ────────────────────────────────────────────────────────────────

export function useConversationOffersQuery(
  conversationId: string | null | undefined,
): UseQueryResult<Offer[], ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery<Offer[], ApiClientError>({
    queryKey: offersKeys.byConversation(conversationId ?? ''),
    queryFn: () => listConversationOffers(conversationId as string),
    enabled: isAuthenticated && Boolean(conversationId),
    staleTime: 30 * SECOND,
  });
}

// ── Mutations ──────────────────────────────────────────────────────────────

interface MakeOfferVars {
  conversationId: string;
  payload: CreateOfferRequest;
}

/**
 * Submit a new offer. On success we invalidate the offers list AND the
 * conversation's messages query so the auto-generated "offer" message
 * shows up in the timeline. We deliberately skip optimistic insertion —
 * offers are rare enough that the small server round-trip is fine.
 */
export function useMakeOfferMutation(): UseMutationResult<
  Offer,
  ApiClientError,
  MakeOfferVars
> {
  const qc = useQueryClient();
  return useMutation<Offer, ApiClientError, MakeOfferVars>({
    mutationFn: ({ conversationId, payload }) =>
      makeOffer(conversationId, payload),
    onSuccess: (offer) => {
      qc.invalidateQueries({
        queryKey: offersKeys.byConversation(offer.conversation_id),
      });
      qc.invalidateQueries({
        queryKey: messagingKeys.messages(offer.conversation_id),
      });
      // List previews carry the latest message preview — refresh them too.
      qc.invalidateQueries({ queryKey: messagingKeys.lists() });
    },
  });
}

/**
 * Shared helper: invalidate both the offers list and the conversation
 * messages query after a status transition. Accept/reject/withdraw all
 * follow the same invalidation recipe.
 */
function invalidateOfferCaches(
  qc: ReturnType<typeof useQueryClient>,
  offer: Offer,
) {
  qc.invalidateQueries({
    queryKey: offersKeys.byConversation(offer.conversation_id),
  });
  qc.invalidateQueries({
    queryKey: messagingKeys.messages(offer.conversation_id),
  });
  qc.invalidateQueries({ queryKey: messagingKeys.lists() });
}

export function useAcceptOfferMutation(): UseMutationResult<
  Offer,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<Offer, ApiClientError, string>({
    mutationFn: (offerId) => acceptOffer(offerId),
    onSuccess: (offer) => invalidateOfferCaches(qc, offer),
  });
}

export function useRejectOfferMutation(): UseMutationResult<
  Offer,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<Offer, ApiClientError, string>({
    mutationFn: (offerId) => rejectOffer(offerId),
    onSuccess: (offer) => invalidateOfferCaches(qc, offer),
  });
}

export function useWithdrawOfferMutation(): UseMutationResult<
  Offer,
  ApiClientError,
  string
> {
  const qc = useQueryClient();
  return useMutation<Offer, ApiClientError, string>({
    mutationFn: (offerId) => withdrawOffer(offerId),
    onSuccess: (offer) => invalidateOfferCaches(qc, offer),
  });
}
