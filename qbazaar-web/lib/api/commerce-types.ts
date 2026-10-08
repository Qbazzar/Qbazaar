/**
 * Types for the M1b order cycle (purchase requests, offers as cards, orders,
 * checkout, wallet, settlements, withdrawals, payout accounts, promotions),
 * 1:1 with `qbazaar-contracts/openapi/v1.yaml`. Every amount is a
 * `DecimalAmount`: an exact two-place decimal string, never a float.
 */
import type { Ad, PublicUser } from './types';

/** Exact decimal with two places, e.g. "1999.99". */
export type DecimalAmount = string;

export type DealRole = 'buyer' | 'seller';

/** Cursor pagination used by every M1b list. */
export interface CursorMeta {
  per_page: number | null;
  has_more: boolean;
  next_cursor: string | null;
}

export interface CursorPage<T> {
  success: true;
  data: T[];
  meta: CursorMeta;
}

// ── Ads as the deal pages read them ──────────────────────────────────────

export interface VerificationBadges {
  email_verified: boolean;
  phone_verified: boolean;
  business_verified?: boolean;
}

/** `GET /ads/{id}` as the deal pages read it: the shared `Ad` plus the seller's badges. */
export interface DealAd extends Omit<Ad, 'user'> {
  is_reserved?: boolean;
  user?: PublicUser & {
    business_name?: string | null;
    verification_badges?: VerificationBadges;
  };
}

// ── Offers (the card's view of `Offer`) ──────────────────────────────────

export type DealOfferStatus = 'pending' | 'accepted' | 'rejected' | 'withdrawn' | 'expired' | 'countered';

export interface DealOffer {
  id: string;
  conversation_id: string;
  ad_id: string;
  buyer_id: string;
  seller_id: string;
  proposed_by: DealRole;
  parent_offer_id: string | null;
  counter_round: number;
  amount: DecimalAmount;
  currency: string;
  note: string | null;
  status: DealOfferStatus;
  expires_at: string;
  accepted_at: string | null;
  rejected_at: string | null;
  withdrawn_at: string | null;
  countered_at: string | null;
  created_at: string;
  viewer_role: DealRole | null;
}

// ── Purchase requests ("Buy Now") ────────────────────────────────────────

export type PurchaseRequestStatus = 'pending' | 'accepted' | 'rejected' | 'cancelled' | 'paid';

export interface PurchaseRequest {
  id: string;
  conversation_id: string;
  ad_id: string;
  buyer_id: string;
  seller_id: string;
  message_id: string | null;
  order_id: string | null;
  status: PurchaseRequestStatus;
  currency: string;
  unit_price: DecimalAmount;
  quantity: number;
  total: DecimalAmount;
  note: string | null;
  cancelled_by: string | null;
  accepted_at: string | null;
  rejected_at: string | null;
  cancelled_at: string | null;
  paid_at: string | null;
  created_at: string;
  updated_at: string;
  viewer_role: DealRole | null;
}

export interface SavePurchaseRequestPayload {
  quantity?: number;
  note?: string | null;
}

/** The ad a chat card is about; the card has no ad payload of its own. */
export interface DealCardAd {
  title: string;
  thumbUrl: string | null;
}

// ── Orders and checkout ──────────────────────────────────────────────────

export type OrderStatus = 'created' | 'awaiting_handover' | 'completed' | 'cancelled' | 'disputed';
export type Fulfillment = 'pickup' | 'delivery';
export type PaymentMethod = 'cash';

export interface DeliveryAddress {
  full_name: string;
  phone: string | null;
  street: string;
  house_number: string;
  supplement: string | null;
  city: string;
  postal_code: string | null;
  location_id?: string | null;
}

export interface SavedAddress extends DeliveryAddress {
  id: string;
  label: string | null;
  is_default: boolean;
  created_at: string;
  updated_at: string;
}

export interface OrderDispute {
  reason: string | null;
  resolution: 'completed' | 'cancelled' | null;
  resolution_note: string | null;
  resolved_at: string | null;
}

export interface Order {
  id: string;
  status: OrderStatus;
  source: 'offer' | 'purchase_request';
  source_id: string;
  ad: { id: string | null; title: string };
  buyer_id: string | null;
  seller_id: string | null;
  viewer_role: DealRole;
  currency: string;
  unit_price: DecimalAmount;
  quantity: number;
  shipping_fee: DecimalAmount;
  total: DecimalAmount;
  payment_method: PaymentMethod;
  fulfillment: Fulfillment | null;
  delivery_address: DeliveryAddress | null;
  /** Seller only. */
  commission: { rate: DecimalAmount; amount: DecimalAmount } | null;
  cancellation_reason: string | null;
  created_at: string;
  awaiting_handover_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
  disputed_at: string | null;
  handed_over_at: string | null;
  /** Buyer only; null when a problem can no longer be reported. */
  report_problem_until: string | null;
  dispute: OrderDispute | null;
}

export interface CheckoutQuote {
  unit_price: DecimalAmount;
  quantity: number;
  items_subtotal: DecimalAmount;
  shipping_fee: DecimalAmount;
  total: DecimalAmount;
}

export interface Checkout {
  order: Order;
  currency: string;
  fulfillment_options: Fulfillment[];
  delivery_fee: DecimalAmount | null;
  max_quantity: number;
  payment_methods: PaymentMethod[];
  saved_addresses: SavedAddress[];
  quotes: Partial<Record<Fulfillment, CheckoutQuote>>;
}

export type CheckoutAddressInput = Omit<DeliveryAddress, 'location_id'>;

export interface CheckoutPayload {
  fulfillment: Fulfillment;
  payment_method: PaymentMethod;
  address_id?: string;
  address?: CheckoutAddressInput;
}

// ── Wallet ───────────────────────────────────────────────────────────────

export interface Wallet {
  currency: string;
  available_balance: DecimalAmount;
  withdrawable_balance: DecimalAmount;
  commission_debt: DecimalAmount;
  debt_ceiling: DecimalAmount;
  can_accept_orders: boolean;
}

export type LedgerTransactionType =
  | 'order_payment_received'
  | 'escrow_released'
  | 'commission_charged'
  | 'commission_settled'
  | 'withdrawal_requested'
  | 'withdrawal_paid'
  | 'withdrawal_rejected'
  | 'refund'
  | 'promotion_purchased'
  | 'adjustment'
  | 'reversal'
  | 'commission_refunded';

export type WalletAccount = 'wallet' | 'commission_receivable';

export interface WalletEntry {
  id: string;
  transaction_id: string;
  type: LedgerTransactionType;
  account: WalletAccount;
  direction: 'increase' | 'decrease';
  amount: DecimalAmount;
  balance_after: DecimalAmount;
  currency: string;
  description: string;
  reference: { type: 'order' | 'settlement' | 'withdrawal' | 'promotion' | 'adjustment'; id: string } | null;
  posted_at: string;
}

export type ReviewStatus = 'pending' | 'approved' | 'rejected';

export interface Settlement {
  id: string;
  method: 'bank_transfer' | 'wallet';
  status: ReviewStatus;
  currency: string;
  amount: DecimalAmount;
  bank_reference: string | null;
  rejection_reason: string | null;
  created_at: string;
  reviewed_at: string | null;
}

export type SettlementPayload =
  | { method: 'wallet'; amount: DecimalAmount }
  | { method: 'bank_transfer'; amount: DecimalAmount; bankReference: string; proof: File };

export interface Withdrawal {
  id: string;
  status: 'pending' | 'paid' | 'rejected';
  currency: string;
  amount: DecimalAmount;
  holder_name: string;
  iban_masked: string;
  transfer_reference: string | null;
  rejection_reason: string | null;
  created_at: string;
  reviewed_at: string | null;
}

export interface WithdrawalPayload {
  amount: DecimalAmount;
  bank_account_id?: string | null;
}

export interface BankAccount {
  id: string;
  holder_name: string;
  iban_masked: string;
  bank_name: string | null;
  is_default: boolean;
  created_at: string;
}

export interface BankAccountPayload {
  holder_name: string;
  iban: string;
  bank_name?: string | null;
  is_default?: boolean;
}

// ── Paid promotion ───────────────────────────────────────────────────────

export type PromotionType = 'highlight' | 'push_up' | 'gallery' | 'premium';
export type PromotionPaymentMethod = 'wallet' | 'bank_transfer';

export interface PromotionOffer {
  type: PromotionType;
  price: DecimalAmount;
  currency: string;
  duration_days: number;
}

export interface AdPromotion {
  id: string;
  ad_id: string | null;
  ad_title?: string | null;
  type: PromotionType;
  status: 'pending_payment' | 'active' | 'expired' | 'rejected';
  payment_method: PromotionPaymentMethod;
  price: DecimalAmount;
  currency: string;
  duration_days: number;
  transfer_reference: string | null;
  rejection_reason: string | null;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
}

export interface PromotionPurchasePayload {
  type: PromotionType;
  payment_method: PromotionPaymentMethod;
  transfer_reference?: string | null;
}
