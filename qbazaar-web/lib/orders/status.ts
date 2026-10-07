import type {
  AdPromotion,
  DealOfferStatus,
  Order,
  OrderStatus,
  PurchaseRequestStatus,
  Settlement,
  Withdrawal,
} from '@/lib/api/commerce-types';

/** The Badge tones a status maps onto. */
export type StatusTone = 'brand' | 'success' | 'danger' | 'info' | 'neutral';

export const ORDER_STATUS_TONE: Record<OrderStatus, StatusTone> = {
  created: 'brand',
  awaiting_handover: 'info',
  completed: 'success',
  cancelled: 'neutral',
  disputed: 'danger',
};

/** As the chat cards of 721:41875 and 721:42157: accepted and paid green, rejected and closed red. */
export const PURCHASE_REQUEST_TONE: Record<PurchaseRequestStatus, StatusTone> = {
  pending: 'brand',
  accepted: 'success',
  paid: 'success',
  rejected: 'danger',
  cancelled: 'danger',
};

export const OFFER_TONE: Record<DealOfferStatus, StatusTone> = {
  pending: 'brand',
  accepted: 'success',
  countered: 'info',
  rejected: 'danger',
  withdrawn: 'danger',
  expired: 'danger',
};

export const REVIEW_TONE: Record<Settlement['status'] | Withdrawal['status'], StatusTone> = {
  pending: 'brand',
  approved: 'success',
  paid: 'success',
  rejected: 'danger',
};

export const PROMOTION_TONE: Record<AdPromotion['status'], StatusTone> = {
  pending_payment: 'brand',
  active: 'success',
  expired: 'neutral',
  rejected: 'danger',
};

export interface OrderActions {
  checkout: boolean;
  cancel: boolean;
  confirmHandover: boolean;
  reportProblem: boolean;
}

/** What the viewer may do with the order now; the API enforces the same rules. */
export function orderActions(order: Order, now: number = Date.now()): OrderActions {
  const isBuyer = order.viewer_role === 'buyer';
  const isOpen = order.status === 'created' || order.status === 'awaiting_handover';
  const reportUntil = order.report_problem_until ? Date.parse(order.report_problem_until) : Number.NaN;

  return {
    checkout: isBuyer && order.status === 'created',
    cancel: isOpen,
    confirmHandover: !isBuyer && order.status === 'awaiting_handover',
    reportProblem:
      isBuyer && order.status !== 'disputed' && order.status !== 'cancelled' && !Number.isNaN(reportUntil) && now < reportUntil,
  };
}

export type TimelineStepKey = 'placed' | 'checked_out' | 'handed_over' | 'cancelled' | 'disputed' | 'resolved';

export interface TimelineStep {
  key: TimelineStepKey;
  at: string | null;
  state: 'done' | 'current' | 'upcoming';
}

/**
 * The order's progress as steps: the happy path (placed → checked out →
 * handed over), or where it left that path (cancelled, disputed and ruled).
 */
export function orderTimeline(order: Order): TimelineStep[] {
  const steps: TimelineStep[] = [{ key: 'placed', at: order.created_at, state: 'done' }];

  if (order.status === 'cancelled' && !order.disputed_at) {
    if (order.awaiting_handover_at) steps.push({ key: 'checked_out', at: order.awaiting_handover_at, state: 'done' });
    steps.push({ key: 'cancelled', at: order.cancelled_at, state: 'done' });
    return steps;
  }

  steps.push({
    key: 'checked_out',
    at: order.awaiting_handover_at,
    state: order.awaiting_handover_at ? 'done' : 'current',
  });

  if (order.handed_over_at || order.status !== 'disputed') {
    steps.push({
      key: 'handed_over',
      at: order.handed_over_at ?? order.completed_at,
      state: order.handed_over_at || order.completed_at ? 'done' : order.awaiting_handover_at ? 'current' : 'upcoming',
    });
  }

  if (order.disputed_at) {
    const resolvedAt = order.dispute?.resolved_at ?? null;
    steps.push({ key: 'disputed', at: order.disputed_at, state: 'done' });
    steps.push({ key: 'resolved', at: resolvedAt, state: resolvedAt ? 'done' : 'current' });
  }

  return steps;
}
