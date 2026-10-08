import { describe, expect, it } from 'vitest';

import { buildOrder } from './test-fixtures';

import { orderActions, orderTimeline } from './status';

const NOW = Date.parse('2026-10-06T12:00:00Z');

describe('orderActions', () => {
  it('lets the buyer check out or cancel a placed order', () => {
    expect(orderActions(buildOrder({ status: 'created' }), NOW)).toEqual({
      checkout: true,
      cancel: true,
      confirmHandover: false,
      reportProblem: false,
    });
  });

  it('lets only the seller confirm the handover', () => {
    const awaiting = { status: 'awaiting_handover' as const, awaiting_handover_at: '2026-10-05T10:00:00Z' };
    expect(orderActions(buildOrder({ ...awaiting, viewer_role: 'seller' }), NOW).confirmHandover).toBe(true);
    expect(orderActions(buildOrder({ ...awaiting, viewer_role: 'buyer' }), NOW).confirmHandover).toBe(false);
  });

  it('offers the problem report only inside the window', () => {
    const completed = buildOrder({ status: 'completed', report_problem_until: '2026-10-07T12:00:00Z' });
    expect(orderActions(completed, NOW).reportProblem).toBe(true);
    expect(orderActions(completed, Date.parse('2026-10-08T00:00:00Z')).reportProblem).toBe(false);
    expect(orderActions(buildOrder({ status: 'completed', report_problem_until: null }), NOW).reportProblem).toBe(false);
    expect(
      orderActions(buildOrder({ status: 'disputed', report_problem_until: '2026-10-07T12:00:00Z' }), NOW).reportProblem,
    ).toBe(false);
  });

  it('allows nothing on a closed order', () => {
    expect(orderActions(buildOrder({ status: 'cancelled' }), NOW)).toEqual({
      checkout: false,
      cancel: false,
      confirmHandover: false,
      reportProblem: false,
    });
  });
});

describe('orderTimeline', () => {
  const keys = (order: Parameters<typeof orderTimeline>[0]) =>
    orderTimeline(order).map((step) => `${step.key}:${step.state}`);

  it('shows the next step of a placed order', () => {
    expect(keys(buildOrder({ status: 'created' }))).toEqual(['placed:done', 'checked_out:current', 'handed_over:upcoming']);
  });

  it('marks the handover as current once checked out', () => {
    expect(keys(buildOrder({ status: 'awaiting_handover', awaiting_handover_at: '2026-10-05T10:00:00Z' }))).toEqual([
      'placed:done',
      'checked_out:done',
      'handed_over:current',
    ]);
  });

  it('ends a cancelled order with the cancellation', () => {
    expect(keys(buildOrder({ status: 'cancelled', cancelled_at: '2026-10-05T10:00:00Z' }))).toEqual([
      'placed:done',
      'cancelled:done',
    ]);
  });

  it('follows a dispute to its ruling', () => {
    const disputed = {
      awaiting_handover_at: '2026-10-04T10:00:00Z',
      handed_over_at: '2026-10-05T10:00:00Z',
      completed_at: '2026-10-05T10:00:00Z',
      disputed_at: '2026-10-05T12:00:00Z',
    };
    expect(keys(buildOrder({ ...disputed, status: 'disputed', dispute: { reason: 'Broken', resolution: null, resolution_note: null, resolved_at: null } }))).toEqual([
      'placed:done',
      'checked_out:done',
      'handed_over:done',
      'disputed:done',
      'resolved:current',
    ]);
    expect(
      keys(
        buildOrder({
          ...disputed,
          status: 'cancelled',
          cancelled_at: '2026-10-06T10:00:00Z',
          dispute: { reason: 'Broken', resolution: 'cancelled', resolution_note: 'Refunded', resolved_at: '2026-10-06T10:00:00Z' },
        }),
      ),
    ).toEqual(['placed:done', 'checked_out:done', 'handed_over:done', 'disputed:done', 'resolved:done']);
  });
});
