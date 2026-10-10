'use client';

import type { ReactNode } from 'react';
import { Dialog } from '@base-ui/react/dialog';

import { buttonVariants } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

export interface AccountDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  children: ReactNode;
  className?: string;
}

/**
 * The edit dialogs of the settings screen (acctModalOpen in account.html):
 * up to 560 px wide, r22, a 24 px title with × on the end. Focus is trapped;
 * Escape and a click on the dimmed page close it.
 */
export function AccountDialog({ open, onOpenChange, title, children, className }: AccountDialogProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-acct-overlay transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none" />
        <Dialog.Popup
          className={cn(
            'fixed inset-x-6 top-1/2 z-50 mx-auto max-h-[90vh] max-w-[560px] -translate-y-1/2 overflow-y-auto',
            'rounded-[22px] bg-qb-surface p-[clamp(24px,4vw,34px)] font-qb text-qb-ink shadow-qb-acct-modal',
            'transition-[opacity,scale] duration-200 data-starting-style:scale-95 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none',
            focusRing,
            className,
          )}
        >
          <div className="mb-6 flex items-center justify-between gap-4">
            <Dialog.Title className="text-qb-h3 leading-normal font-semibold tracking-normal">{title}</Dialog.Title>
            <Dialog.Close
              aria-label={t('ui.close')}
              className={cn('-me-1 cursor-pointer rounded-qb-sm px-1 text-[26px] leading-none text-qb-ink-body', focusRing)}
            >
              <span aria-hidden="true">×</span>
            </Dialog.Close>
          </div>
          {children}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

/** Input box of the dialogs: #e0e0e0 border, r12, 14×16 padding, 15 px text, an orange border on focus. */
export const accountInputClass =
  'h-auto rounded-qb-lg border-qb-acct-field px-4 py-3.5 text-qb-body-sm focus-visible:ring-0 aria-invalid:focus-visible:ring-0';

/** "Save" of the dialogs: orange, as wide as "Cancel". */
export const accountSaveClass = cn(
  buttonVariants({ size: 'lg' }),
  'h-auto min-w-0 flex-1 basis-0 rounded-qb-lg py-[15px] text-qb-body',
);

/** "Cancel" of the dialogs: #f4f4f4 with grey text. */
export const accountCancelClass = cn(
  buttonVariants({ size: 'lg' }),
  'h-auto min-w-0 flex-1 basis-0 rounded-qb-lg bg-qb-fill-strong py-[15px] text-qb-body text-qb-ink-body hover:bg-qb-line active:bg-qb-line',
);

/** The equal-width Save / Cancel pair at the bottom of a dialog. */
export function AccountDialogActions({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('mt-[26px] flex gap-3.5', className)}>{children}</div>;
}
