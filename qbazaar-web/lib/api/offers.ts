/**
 * Typed client for the offers endpoints (Sprint 9).
 *
 * Offers are buyer→seller price proposals attached to an existing conversation.
 * Mirrors `qbazaar-contracts/openapi/v1.yaml` (BE-9.x) and routes everything
 * through the shared axios instance so cookies, locale and the 401-refresh
 * dance are inherited. All errors are normalised into `ApiClientError` so
 * the UI can switch on stable `OfferErrorCode` values.
 *
 * Note on `amount`: the backend serialises decimals as strings (e.g.
 * `"1500.00"`) to avoid float drift. Every offer payload is funnelled
 * through `normaliseOffer` which coerces it to a `number` so consumers
 * never have to worry about the wire shape.
 */
import { isAxiosError } from 'axios';
import { api } from './client';
import { ApiClientError } from './auth';
import type {
  CreateOfferRequest,
  ErrorEnvelope,
  Offer,
  SuccessEnvelope,
} from './types';

const CONVERSATIONS_BASE = '/api/v1/conversations';
const OFFERS_BASE = '/api/v1/offers';

function toApiClientError(err: unknown): ApiClientError {
  if (isAxiosError<ErrorEnvelope>(err) && err.response?.data?.error) {
    const e = err.response.data.error;
    return new ApiClientError({
      status: err.response.status,
      code: e.code,
      messageKey: e.message_key,
      message: e.message,
      details: e.details,
      requestId: e.request_id,
    });
  }
  if (err instanceof Error) {
    return new ApiClientError({
      status: 0,
      code: 'NETWORK_ERROR',
      messageKey: 'errors.network',
      message: err.message,
    });
  }
  return new ApiClientError({
    status: 0,
    code: 'UNKNOWN_ERROR',
    messageKey: 'errors.unknown',
    message: 'Unknown error',
  });
}

/**
 * Coerce the wire shape (where `amount` may be a decimal string) into the
 * `Offer` type promised to UI consumers. We always return a real number so
 * downstream comparisons (`amount >= price_min`) don't accidentally cast.
 */
export function normaliseOffer(raw: Offer | (Omit<Offer, 'amount'> & { amount: number | string })): Offer {
  const amount = typeof raw.amount === 'string' ? Number(raw.amount) : raw.amount;
  return { ...raw, amount } as Offer;
}

export async function makeOffer(
  conversationId: string,
  payload: CreateOfferRequest,
): Promise<Offer> {
  try {
    const { data } = await api.post<SuccessEnvelope<Offer>>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(conversationId)}/offers`,
      payload,
    );
    return normaliseOffer(data.data);
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function listConversationOffers(
  conversationId: string,
): Promise<Offer[]> {
  try {
    const { data } = await api.get<SuccessEnvelope<Offer[]>>(
      `${CONVERSATIONS_BASE}/${encodeURIComponent(conversationId)}/offers`,
    );
    return data.data.map(normaliseOffer);
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function acceptOffer(offerId: string): Promise<Offer> {
  try {
    const { data } = await api.post<SuccessEnvelope<Offer>>(
      `${OFFERS_BASE}/${encodeURIComponent(offerId)}/accept`,
    );
    return normaliseOffer(data.data);
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function rejectOffer(offerId: string): Promise<Offer> {
  try {
    const { data } = await api.post<SuccessEnvelope<Offer>>(
      `${OFFERS_BASE}/${encodeURIComponent(offerId)}/reject`,
    );
    return normaliseOffer(data.data);
  } catch (err) {
    throw toApiClientError(err);
  }
}

export async function withdrawOffer(offerId: string): Promise<Offer> {
  try {
    const { data } = await api.post<SuccessEnvelope<Offer>>(
      `${OFFERS_BASE}/${encodeURIComponent(offerId)}/withdraw`,
    );
    return normaliseOffer(data.data);
  } catch (err) {
    throw toApiClientError(err);
  }
}
