'use client';

import { useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { useIdempotencyKey } from '@/hooks/useIdempotencyKey';
import type { Order } from '@/lib/api/commerce-types';
import { t } from '@/lib/i18n/messages';
import { dealErrorMessage, isHandledGlobally } from '@/lib/orders/errors';
import { formatMoney } from '@/lib/orders/money';
import { orderActions } from '@/lib/orders/status';
import { LIMITS, validateText } from '@/lib/orders/validation';
import { useCancelOrderMutation, useConfirmHandoverMutation, useReportProblemMutation } from '@/lib/queries/orders';
import { cn } from '@/lib/utils';

import { ConfirmDialog } from './ConfirmDialog';
import { focusFirstInvalidInDialog } from './focus-invalid';
import { NoteField } from './NoteField';

const actionButton = 'h-11 rounded-qb-sm text-qb-caption';

/**
 * The order's next steps for the viewer: check out (buyer), confirm the
 * handover (seller), report a problem within the window (buyer) or cancel
 * while it is still open (both). Each asks before it acts.
 */
export function OrderActionsBar({ order, now }: { order: Order; now?: number }) {
  const actions = orderActions(order, now);
  const confirm = useConfirmHandoverMutation(order.id);
  const cancel = useCancelOrderMutation(order.id);
  const report = useReportProblemMutation(order.id);
  const handoverKey = useIdempotencyKey();
  const cancelKey = useIdempotencyKey();
  const reportKey = useIdempotencyKey();
  const [dialog, setDialog] = useState<'handover' | 'cancel' | 'report' | null>(null);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState<string | null>(null);

  if (!actions.checkout && !actions.confirmHandover && !actions.reportProblem && !actions.cancel) return null;

  const open = (next: 'handover' | 'cancel' | 'report') => {
    setReason('');
    setReasonError(null);
    setDialog(next);
  };

  const run = async (action: () => Promise<unknown>, success: string, renew: () => void) => {
    try {
      await action();
      renew();
      setDialog(null);
      toast.success(success);
    } catch (error) {
      if (!isHandledGlobally(error)) toast.error(dealErrorMessage(error));
    }
  };

  const submitCancel = () => {
    const error = validateText(reason, { max: LIMITS.cancelReasonMax });
    setReasonError(error);
    if (error) {
      focusFirstInvalidInDialog();
      return;
    }
    void run(
      () => cancel.mutateAsync({ reason: reason.trim() || null, idempotencyKey: cancelKey.key }),
      t('orders.detail.toast.cancelled'),
      cancelKey.renew,
    );
  };

  const submitReport = () => {
    const error = validateText(reason, { min: LIMITS.disputeReasonMin, max: LIMITS.disputeReasonMax, required: true });
    setReasonError(error);
    if (error) {
      focusFirstInvalidInDialog();
      return;
    }
    void run(
      () => report.mutateAsync({ reason: reason.trim(), idempotencyKey: reportKey.key }),
      t('orders.detail.toast.reported'),
      reportKey.renew,
    );
  };

  const total = formatMoney(order.total, order.currency);
  const commission = order.commission ? formatMoney(order.commission.amount, order.currency) : formatMoney('0', order.currency);

  return (
    <>
      <div className="flex flex-col gap-3 qb-tablet:flex-row qb-tablet:flex-wrap">
        {actions.checkout ? (
          <Link href={`/checkout/${encodeURIComponent(order.id)}`} className={cn(buttonVariants(), actionButton)}>
            {t('orders.detail.checkout')}
          </Link>
        ) : null}
        {actions.confirmHandover ? (
          <Button className={actionButton} onClick={() => open('handover')}>
            {t('orders.detail.confirm_handover')}
          </Button>
        ) : null}
        {actions.reportProblem ? (
          <Button variant="outline" className={actionButton} onClick={() => open('report')}>
            {t('orders.detail.report_problem')}
          </Button>
        ) : null}
        {actions.cancel ? (
          <Button variant="danger" className={actionButton} onClick={() => open('cancel')}>
            {t('orders.detail.cancel_order')}
          </Button>
        ) : null}
      </div>

      <ConfirmDialog
        open={dialog === 'handover'}
        onOpenChange={(next) => setDialog(next ? 'handover' : null)}
        title={t('orders.detail.confirm_dialog.title')}
        description={t('orders.detail.confirm_dialog.description', { total, commission })}
        confirmLabel={t('orders.detail.confirm_dialog.confirm')}
        cancelLabel={t('orders.common.cancel')}
        busy={confirm.isPending}
        onConfirm={() =>
          void run(
            () => confirm.mutateAsync({ idempotencyKey: handoverKey.key }),
            t('orders.detail.toast.handover'),
            handoverKey.renew,
          )
        }
      />

      <ConfirmDialog
        open={dialog === 'cancel'}
        onOpenChange={(next) => setDialog(next ? 'cancel' : null)}
        title={t('orders.detail.cancel_dialog.title')}
        description={t('orders.detail.cancel_dialog.description')}
        confirmLabel={t('orders.detail.cancel_dialog.confirm')}
        cancelLabel={t('orders.detail.cancel_dialog.keep')}
        tone="danger"
        busy={cancel.isPending}
        onConfirm={submitCancel}
      >
        <NoteField
          label={t('orders.detail.cancel_dialog.reason')}
          placeholder={t('orders.detail.cancel_dialog.reason_placeholder')}
          value={reason}
          onChange={setReason}
          max={LIMITS.cancelReasonMax}
          error={reasonError}
          className="text-start"
        />
      </ConfirmDialog>

      <ConfirmDialog
        open={dialog === 'report'}
        onOpenChange={(next) => setDialog(next ? 'report' : null)}
        title={t('orders.detail.report_dialog.title')}
        description={t('orders.detail.report_dialog.description')}
        confirmLabel={t('orders.detail.report_dialog.submit')}
        cancelLabel={t('orders.common.cancel')}
        busy={report.isPending}
        onConfirm={submitReport}
      >
        <NoteField
          label={t('orders.detail.report_dialog.reason')}
          placeholder={t('orders.detail.report_dialog.reason_placeholder')}
          value={reason}
          onChange={setReason}
          max={LIMITS.disputeReasonMax}
          error={reasonError}
          required
          rows={5}
          className="text-start"
        />
      </ConfirmDialog>
    </>
  );
}
