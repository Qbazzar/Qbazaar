'use client';

import type { ReactElement, ReactNode } from 'react';
import { Dialog } from '@base-ui/react/dialog';
import { X } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';
import { Icon } from './Icon';

export interface DialogShellProps {
  title: string;
  description?: ReactNode;
  children?: ReactNode;
  /** Element that opens the dialog, e.g. a `<Button>`. Omit when `open` is controlled. */
  trigger?: ReactElement;
  open?: boolean;
  defaultOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  showCloseButton?: boolean;
  className?: string;
}

const backdrop =
  'fixed inset-0 z-50 bg-qb-overlay transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none';

function Shell({ trigger, open, defaultOpen, onOpenChange, children }: DialogShellProps & { children: ReactNode }) {
  return (
    <Dialog.Root open={open} defaultOpen={defaultOpen} onOpenChange={onOpenChange}>
      {trigger ? <Dialog.Trigger render={trigger} /> : null}
      <Dialog.Portal>
        <Dialog.Backdrop className={backdrop} />
        {children}
      </Dialog.Portal>
    </Dialog.Root>
  );
}

function CloseButton({ className }: { className?: string }) {
  return (
    <Dialog.Close
      aria-label={t('ui.close')}
      className={cn('inline-flex size-10 items-center justify-center rounded-qb-md text-qb-ink hover:bg-qb-fill', focusRing, className)}
    >
      <Icon icon={X} size="lg" />
    </Dialog.Close>
  );
}

/** Centred confirmation dialog ("Delete Chat?", 367:16294): focus trap, Esc and backdrop close. */
export function Modal(props: DialogShellProps) {
  const { title, description, children, showCloseButton = false, className } = props;
  return (
    <Shell {...props}>
      <Dialog.Popup
        className={cn(
          'fixed inset-x-4 top-1/2 z-50 mx-auto max-h-[calc(100dvh-32px)] max-w-[538px] -translate-y-1/2 overflow-y-auto',
          'rounded-qb-2xl border border-qb-line bg-qb-surface p-6 font-qb text-qb-ink',
          'transition-[opacity,scale] duration-200 data-starting-style:scale-95 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none',
          focusRing,
          className,
        )}
      >
        {showCloseButton ? <CloseButton className="absolute end-3 top-3" /> : null}
        <Dialog.Title className="text-center text-qb-h3 font-semibold tracking-normal">{title}</Dialog.Title>
        {description ? (
          <Dialog.Description className="mt-2.5 text-center text-qb-body text-qb-ink-secondary">{description}</Dialog.Description>
        ) : null}
        {children ? <div className="mt-4">{children}</div> : null}
      </Dialog.Popup>
    </Shell>
  );
}

/** Bottom sheet used under 1000 px for filters and pickers (618:26974). */
export function Sheet(props: DialogShellProps) {
  const { title, description, children, showCloseButton = true, className } = props;
  return (
    <Shell {...props}>
      <Dialog.Popup
        className={cn(
          'fixed inset-x-0 bottom-0 z-50 max-h-[90dvh] overflow-y-auto rounded-t-qb-2xl bg-qb-surface px-6 pt-3 pb-6 font-qb text-qb-ink',
          'transition-transform duration-300 data-starting-style:translate-y-full data-ending-style:translate-y-full motion-reduce:transition-none',
          focusRing,
          className,
        )}
      >
        <div aria-hidden="true" className="mx-auto mb-4 h-1 w-12 rounded-qb-pill bg-qb-line" />
        <div className="flex items-center justify-between gap-4">
          <Dialog.Title className="text-qb-h5 font-medium tracking-normal">{title}</Dialog.Title>
          {showCloseButton ? <CloseButton className="-me-2" /> : null}
        </div>
        {description ? <Dialog.Description className="mt-1 text-qb-caption text-qb-ink-subtle">{description}</Dialog.Description> : null}
        {children ? <div className="mt-4">{children}</div> : null}
      </Dialog.Popup>
    </Shell>
  );
}
