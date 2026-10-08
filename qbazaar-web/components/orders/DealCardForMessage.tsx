'use client';

import { useAuth } from '@/hooks/useAuth';
import type { DealCardAd, DealOffer, DealRole, PurchaseRequest } from '@/lib/api/commerce-types';
import { formatListPrice } from '@/lib/orders/money';
import { useConversationQuery } from '@/lib/queries/messaging';

import { OfferCard } from './OfferCard';
import { PurchaseRequestCard } from './PurchaseRequestCard';

/** The chat message fields a card needs; the shared `Message` type satisfies it. */
export interface DealMessage {
  type: string;
  conversation_id: string;
  offer?: unknown;
  purchase_request?: unknown;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

export function asPurchaseRequest(value: unknown): PurchaseRequest | null {
  if (!isRecord(value) || typeof value.id !== 'string' || typeof value.status !== 'string') return null;
  if (typeof value.total !== 'string' || typeof value.unit_price !== 'string') return null;
  return value as unknown as PurchaseRequest;
}

/** Older cached offers may carry the amount as a number; cards always get the exact string. */
export function asDealOffer(value: unknown): DealOffer | null {
  if (!isRecord(value) || typeof value.id !== 'string' || typeof value.status !== 'string') return null;
  if (typeof value.amount !== 'string' && typeof value.amount !== 'number') return null;
  return {
    ...(value as unknown as DealOffer),
    amount: String(value.amount),
    proposed_by: value.proposed_by === 'seller' ? 'seller' : 'buyer',
    counter_round: typeof value.counter_round === 'number' ? value.counter_round : 0,
  };
}

/** True when the message is rendered as a card instead of a bubble. */
export function isDealMessage(message: DealMessage): boolean {
  if (message.type === 'purchase_request') return asPurchaseRequest(message.purchase_request) !== null;
  if (message.type === 'offer') return asDealOffer(message.offer) !== null;
  return false;
}

interface ConversationAd {
  title?: string;
  thumb_url?: string | null;
  primary_image?: { sizes?: { thumbnail?: string } } | null;
  price?: number | null;
  currency?: string;
}

function cardAd(ad: ConversationAd | null | undefined): DealCardAd | null {
  if (!ad?.title) return null;
  return { title: ad.title, thumbUrl: ad.primary_image?.sizes?.thumbnail ?? ad.thumb_url ?? null };
}

function roleOf(viewerRole: DealRole | null, buyerId: string, userId: string | undefined): DealRole {
  if (viewerRole) return viewerRole;
  return userId === buyerId ? 'buyer' : 'seller';
}

/**
 * Maps a purchase-request or offer message to its card. The ad comes from
 * the thread's conversation, which the open chat has already loaded.
 */
export function DealCardForMessage({ message, isMine }: { message: DealMessage; isMine: boolean }) {
  const { user } = useAuth();
  const { data: conversation } = useConversationQuery(message.conversation_id);
  const conversationAd: ConversationAd | undefined = conversation?.ad;
  const ad = cardAd(conversationAd);
  const align = isMine ? 'end' : 'start';

  const request = message.type === 'purchase_request' ? asPurchaseRequest(message.purchase_request) : null;
  if (request) {
    return <PurchaseRequestCard request={request} role={roleOf(request.viewer_role, request.buyer_id, user?.id)} ad={ad} align={align} />;
  }

  const offer = message.type === 'offer' ? asDealOffer(message.offer) : null;
  if (offer) {
    const listedPrice =
      typeof conversationAd?.price === 'number' ? formatListPrice(conversationAd.price, conversationAd.currency ?? offer.currency) : null;
    return (
      <OfferCard
        offer={offer}
        role={roleOf(offer.viewer_role, offer.buyer_id, user?.id)}
        ad={ad}
        listedPrice={listedPrice}
        align={align}
      />
    );
  }

  return null;
}
