/**
 * Builders for order-cycle payloads in tests, shaped like the live API
 * responses (decimal strings, ISO dates). Each takes overrides for the
 * fields a test cares about.
 */
import type {
  Checkout,
  DealOffer,
  Order,
  PurchaseRequest,
  Wallet,
  WalletEntry,
} from '@/lib/api/commerce-types';

export function buildOrder(overrides: Partial<Order> = {}): Order {
  return {
    id: '01m48bfd0zgytmt8wt5j29a0qr',
    status: 'created',
    source: 'purchase_request',
    source_id: '01m48bftcab36czk1w1yf8zpgk',
    ad: { id: '01m48bam2zb1w4zm15wjes1esj', title: '200 L aquarium with fish' },
    buyer_id: 'buyer-1',
    seller_id: 'seller-1',
    viewer_role: 'buyer',
    currency: 'QAR',
    unit_price: '1550.00',
    quantity: 1,
    shipping_fee: '0.00',
    total: '1550.00',
    payment_method: 'cash',
    fulfillment: null,
    delivery_address: null,
    commission: null,
    cancellation_reason: null,
    created_at: '2026-10-04T10:00:00Z',
    awaiting_handover_at: null,
    completed_at: null,
    cancelled_at: null,
    disputed_at: null,
    handed_over_at: null,
    report_problem_until: null,
    dispute: null,
    ...overrides,
  };
}

export function buildCheckout(overrides: Partial<Checkout> = {}): Checkout {
  return {
    order: buildOrder(),
    currency: 'QAR',
    fulfillment_options: ['pickup', 'delivery'],
    delivery_fee: '15.00',
    max_quantity: 1,
    payment_methods: ['cash'],
    saved_addresses: [],
    quotes: {
      pickup: { unit_price: '1550.00', quantity: 1, items_subtotal: '1550.00', shipping_fee: '0.00', total: '1550.00' },
      delivery: { unit_price: '1550.00', quantity: 1, items_subtotal: '1550.00', shipping_fee: '15.00', total: '1565.00' },
    },
    ...overrides,
  };
}

export function buildPurchaseRequest(overrides: Partial<PurchaseRequest> = {}): PurchaseRequest {
  return {
    id: 'pr-1',
    conversation_id: 'conv-1',
    ad_id: '01m48bam2zb1w4zm15wjes1esj',
    buyer_id: 'buyer-1',
    seller_id: 'seller-1',
    message_id: 'msg-1',
    order_id: null,
    status: 'pending',
    currency: 'QAR',
    unit_price: '1550.00',
    quantity: 1,
    total: '1550.00',
    note: 'Can I pick it up this evening?',
    cancelled_by: null,
    accepted_at: null,
    rejected_at: null,
    cancelled_at: null,
    paid_at: null,
    created_at: '2026-10-06T09:30:00Z',
    updated_at: '2026-10-06T09:30:00Z',
    viewer_role: 'seller',
    ...overrides,
  };
}

export function buildOffer(overrides: Partial<DealOffer> = {}): DealOffer {
  return {
    id: 'offer-1',
    conversation_id: 'conv-1',
    ad_id: '01m48bc82v9vn0cs7sram9d1ra',
    buyer_id: 'buyer-1',
    seller_id: 'seller-1',
    proposed_by: 'buyer',
    parent_offer_id: null,
    counter_round: 0,
    amount: '900.00',
    currency: 'QAR',
    note: null,
    status: 'pending',
    expires_at: '2026-10-09T09:30:00Z',
    accepted_at: null,
    rejected_at: null,
    withdrawn_at: null,
    countered_at: null,
    created_at: '2026-10-06T09:30:00Z',
    viewer_role: 'seller',
    ...overrides,
  };
}

export function buildWallet(overrides: Partial<Wallet> = {}): Wallet {
  return {
    currency: 'QAR',
    available_balance: '0.00',
    withdrawable_balance: '0.00',
    commission_debt: '277.50',
    debt_ceiling: '500.00',
    can_accept_orders: true,
    ...overrides,
  };
}

export function buildWalletEntry(overrides: Partial<WalletEntry> = {}): WalletEntry {
  return {
    id: 'entry-1',
    transaction_id: 'tx-1',
    type: 'commission_charged',
    account: 'commission_receivable',
    direction: 'increase',
    amount: '277.50',
    balance_after: '277.50',
    currency: 'QAR',
    description: 'QBazaar commission on a cash sale',
    reference: { type: 'order', id: '01m48bftd5b7jy14zjc42hwmce' },
    posted_at: '2026-10-05T11:52:16Z',
    ...overrides,
  };
}
