'use client';

import type { ReactNode } from 'react';
import { Dialog } from '@base-ui/react/dialog';
import { X } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';
import { Icon } from './Icon';

export interface CardDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  /** Grey line under the title ("Tell us what's wrong with this listing."). */
  description?: string;
  children: ReactNode;
  /** The card's maximum width: 460 px for the share dialog, 480 px for the report form. */
  widthClassName?: string;
}

/**
 * The centred dialog of the reference (product.html share and report): a
 * white r18 card with 28 px padding over the page dimmed with the icon ink
 * at 50 %, a 22 px / 600 title and a grey ✕. Base UI brings the focus trap,
 * Esc and the backdrop click that close it.
 */
export function CardDialog({ open, onOpenChange, title, description, children, widthClassName = 'max-w-[460px]' }: CardDialogProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-icon/50 transition-opacity duration-200 data-ending-style:opacity-0 data-starting-style:opacity-0 motion-reduce:transition-none" />
        <Dialog.Popup
          className={cn(
            'fixed top-1/2 left-1/2 z-50 max-h-[calc(100dvh-40px)] w-[calc(100%-40px)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto',
            'rounded-[18px] bg-qb-surface p-7 font-qb text-qb-ink shadow-qb-modal',
            'transition-opacity duration-200 data-ending-style:opacity-0 data-starting-style:opacity-0 motion-reduce:transition-none',
            focusRing,
            widthClassName,
          )}
        >
          <div className="flex items-center justify-between gap-4">
            <Dialog.Title className="font-qb text-qb-h4 font-semibold tracking-normal text-qb-ink">{title}</Dialog.Title>
            <Dialog.Close
              aria-label={t('ui.close')}
              className={cn('-me-1 inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-qb-sm text-qb-ink-subtle', focusRing)}
            >
              <Icon icon={X} className="size-[22px]" />
            </Dialog.Close>
          </div>
          {description ? <Dialog.Description className="mt-2 text-qb-caption text-qb-ink-subtle">{description}</Dialog.Description> : null}
          {children}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
