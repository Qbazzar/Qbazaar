import { describe, expect, it } from 'vitest';

import { buildOffer, buildPurchaseRequest } from '@/lib/orders/test-fixtures';

import { asDealOffer, isDealMessage } from './DealCardForMessage';

describe('isDealMessage', () => {
  it('recognises purchase-request and offer messages with their payload', () => {
    expect(isDealMessage({ type: 'purchase_request', conversation_id: 'c', purchase_request: buildPurchaseRequest() })).toBe(true);
    expect(isDealMessage({ type: 'offer', conversation_id: 'c', offer: buildOffer() })).toBe(true);
  });

  it('keeps bubbles for text, and for cards whose payload a broadcast left out', () => {
    expect(isDealMessage({ type: 'text', conversation_id: 'c' })).toBe(false);
    expect(isDealMessage({ type: 'offer', conversation_id: 'c', offer: null })).toBe(false);
    expect(isDealMessage({ type: 'purchase_request', conversation_id: 'c' })).toBe(false);
  });
});

describe('asDealOffer', () => {
  it('turns a legacy numeric amount into a string and fills the M1 fields', () => {
    const legacy = { ...buildOffer(), amount: 1500, proposed_by: undefined, counter_round: undefined };

    expect(asDealOffer(legacy)).toMatchObject({ amount: '1500', proposed_by: 'buyer', counter_round: 0 });
  });
});
