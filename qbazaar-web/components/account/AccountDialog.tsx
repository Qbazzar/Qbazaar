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
  /** The title row keeps 24 px below it; "Edit Email Address" and "Edit Address" keep 22 px. */
  headerClassName?: string;
}

/**
 * The edit dialogs of the settings screen (acctModalOpen in account.html):
 * up to 560 px wide inside a 24 px page margin, r22, a 24 px title with the
 * `.qb-btn` × on the end, faded in with qbFade .2s. Focus is trapped. As in
 * the design a click on the dimmed page leaves it open (only × and Cancel
 * close it there); Escape still closes it for keyboard users.
 */
export function AccountDialog({ open, onOpenChange, title, children, className, headerClassName }: AccountDialogProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange} disablePointerDismissal>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-acct-overlay animate-qb-dialog-enter motion-reduce:animate-none" />
        <Dialog.Popup
          className={cn(
            'fixed start-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%-48px)] max-w-[560px] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rtl:translate-x-1/2',
            'rounded-[22px] bg-qb-surface p-[clamp(24px,4vw,34px)] font-qb text-qb-ink shadow-qb-acct-modal',
            'animate-qb-dialog-enter motion-reduce:animate-none',
            focusRing,
            className,
          )}
        >
          <div className={cn('mb-6 flex items-center justify-between gap-4', headerClassName)}>
            <Dialog.Title className="text-qb-h3 leading-[normal] font-semibold tracking-normal">{title}</Dialog.Title>
            <Dialog.Close
              aria-label={t('ui.close')}
              className={cn('qb-motion cursor-pointer text-[26px] leading-none text-qb-ink-body hover:brightness-[.96]', focusRing)}
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

/**
 * Input box of the dialogs: #e0e0e0 border, r12, 14×16 padding, 15 px text in the
 * browser's own black with its grey placeholder, an orange border on focus.
 */
export const accountInputClass =
  'h-auto rounded-qb-lg border-qb-acct-field px-4 py-3.5 text-qb-body-sm text-qb-black placeholder:text-qb-ink-muted focus-visible:ring-0 aria-invalid:focus-visible:ring-0';

/** "Save" of the dialogs: orange, as wide as "Cancel", 15 px padding all round on the .qb-btn 1.15 line. */
export const accountSaveClass = cn(
  buttonVariants({ size: 'lg' }),
  'h-auto min-w-0 flex-1 basis-0 rounded-qb-lg p-[15px] text-qb-body leading-[1.15]',
);

/** "Cancel" of the dialogs: a #f4f4f4 `.qb-btn` with grey text that dims 4 % under the pointer. */
export const accountCancelClass = cn(
  buttonVariants({ size: 'lg' }),
  'h-auto min-w-0 flex-1 basis-0 rounded-qb-lg bg-qb-fill-strong p-[15px] text-qb-body leading-[1.15] text-qb-ink-body hover:bg-qb-fill-strong hover:brightness-[.96]',
);

/** The equal-width Save / Cancel pair at the bottom of a dialog. */
export function AccountDialogActions({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('mt-[26px] flex gap-3.5', className)}>{children}</div>;
}
