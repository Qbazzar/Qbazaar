'use client';

import { useState } from 'react';
import { Dialog } from '@base-ui/react/dialog';
import { Loader2 } from 'lucide-react';

import { showDesignToast } from '@/components/design-system/design-toast';
import { focusRing } from '@/components/design-system/focus-ring';
import type { ReportCategory } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { useSubmitReportMutation } from '@/lib/queries/reports';
import { cn } from '@/lib/utils';

/**
 * The nine problems of chat.js, each filed under the API's nearest report
 * category; the reason itself travels as the report's description.
 */
const REASONS: readonly { key: string; category: ReportCategory }[] = [
  { key: 'inappropriate_content', category: 'inappropriate' },
  { key: 'harassment', category: 'offensive' },
  { key: 'privacy', category: 'other' },
  { key: 'spam', category: 'spam' },
  { key: 'impersonation', category: 'fraud' },
  { key: 'terms', category: 'other' },
  { key: 'security', category: 'fraud' },
  { key: 'hate_speech', category: 'offensive' },
  { key: 'not_listed', category: 'other' },
];

interface ChatReportSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  peerId: string;
}

/**
 * The report sheet of chat.js (604:34196): "Report", "Select a problem to
 * report", the reasons with their radio on the end side (the first one
 * picked) and a pill "Submit Report". A centred card from 601 px, a bottom
 * sheet on phones; Escape or a click on the dimmed page closes it.
 */
export function ChatReportSheet({ open, onOpenChange, peerId }: ChatReportSheetProps) {
  const mutation = useSubmitReportMutation();
  const [reasonKey, setReasonKey] = useState(REASONS[0].key);

  const close = (next: boolean) => {
    if (mutation.isPending) return;
    if (!next) setReasonKey(REASONS[0].key);
    onOpenChange(next);
  };

  const submit = () => {
    const reason = REASONS.find((item) => item.key === reasonKey) ?? REASONS[0];
    mutation.mutate(
      {
        target_type: 'user',
        target_id: peerId,
        category: reason.category,
        description: t(`messaging.report.reasons.${reason.key}`),
      },
      {
        onSuccess: () => {
          showDesignToast(t('messaging.report.sent'));
          close(false);
        },
        // Already reported, or the user themselves: the hook explains it.
        onError: (err) => {
          if (err.code === 'REPORT_002' || err.code === 'REPORT_001') close(false);
        },
      },
    );
  };

  return (
    <Dialog.Root open={open} onOpenChange={close}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-chat-overlay transition-opacity duration-200 data-ending-style:opacity-0 data-starting-style:opacity-0 motion-reduce:transition-none" />
        <Dialog.Popup
          className={cn(
            'fixed inset-x-0 bottom-0 z-50 max-h-[86vh] overflow-y-auto rounded-t-qb-2xl bg-qb-surface px-5 pt-6 pb-7 text-start font-qb',
            'qb-tablet:inset-x-auto qb-tablet:top-1/2 qb-tablet:bottom-auto qb-tablet:left-1/2 qb-tablet:w-[min(430px,94vw)] qb-tablet:-translate-x-1/2 qb-tablet:-translate-y-1/2 qb-tablet:rounded-[20px] qb-tablet:px-[22px] qb-tablet:py-6',
            'transition-[opacity,translate] duration-200 data-ending-style:opacity-0 data-starting-style:opacity-0 motion-reduce:transition-none',
            focusRing,
          )}
        >
          <Dialog.Title className="mb-1 text-qb-h5 font-bold tracking-normal text-qb-ink">
            {t('messaging.report.title')}
          </Dialog.Title>
          <Dialog.Description className="mb-3.5 text-qb-caption text-qb-ink-subtle">
            {t('messaging.report.subtitle')}
          </Dialog.Description>
          <form
            noValidate
            onSubmit={(event) => {
              event.preventDefault();
              submit();
            }}
          >
            <div role="radiogroup" aria-label={t('messaging.report.subtitle')} className="flex flex-col">
              {REASONS.map((reason) => (
                <label
                  key={reason.key}
                  className="flex cursor-pointer items-center justify-between gap-3 px-0.5 py-3 text-qb-body-sm text-qb-ink-title"
                >
                  <span>{t(`messaging.report.reasons.${reason.key}`)}</span>
                  <input
                    type="radio"
                    name="chat-report-reason"
                    value={reason.key}
                    checked={reasonKey === reason.key}
                    onChange={() => setReasonKey(reason.key)}
                    className={cn(
                      'size-5 shrink-0 cursor-pointer appearance-none rounded-full border-[1.5px] border-qb-acct-check-ring checked:border-[5.5px] checked:border-qb-brand',
                      focusRing,
                    )}
                  />
                </label>
              ))}
            </div>
            <button
              type="submit"
              disabled={mutation.isPending}
              className={cn(
                'mt-3.5 flex w-full cursor-pointer items-center justify-center gap-2 rounded-qb-pill bg-qb-brand p-[15px] text-qb-body-sm font-semibold text-qb-on-brand hover:bg-qb-brand-hover disabled:cursor-progress',
                focusRing,
              )}
            >
              {mutation.isPending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {t('messaging.report.submit')}
            </button>
          </form>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
