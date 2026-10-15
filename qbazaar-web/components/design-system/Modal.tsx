'use client';

import type { ReactElement, ReactNode } from 'react';
import { Dialog } from '@base-ui/react/dialog';

import { CloseIcon } from '@/components/design-system/icons';
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

/** Dimmed page behind a dialog; for dialogs built on the base-ui primitives directly. */
export const modalBackdrop =
  'fixed inset-0 z-50 bg-qb-overlay transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none';

function Shell({
  trigger,
  open,
  defaultOpen,
  onOpenChange,
  backdropClassName,
  children,
}: DialogShellProps & { backdropClassName?: string; children: ReactNode }) {
  return (
    <Dialog.Root open={open} defaultOpen={defaultOpen} onOpenChange={onOpenChange}>
      {trigger ? <Dialog.Trigger render={trigger} /> : null}
      <Dialog.Portal>
        <Dialog.Backdrop className={cn(modalBackdrop, backdropClassName)} />
        {children}
      </Dialog.Portal>
    </Dialog.Root>
  );
}

/**
 * The 40 px close button of the dialogs; it must sit inside a base-ui `Dialog.Popup`.
 * `glyph` replaces the X icon (the menu drawer's is a × character).
 */
export function ModalCloseButton({ className, glyph }: { className?: string; glyph?: ReactNode }) {
  return (
    <Dialog.Close
      aria-label={t('ui.close')}
      className={cn('inline-flex size-10 items-center justify-center rounded-qb-md text-qb-ink hover:bg-qb-fill', focusRing, className)}
    >
      {glyph ?? <Icon icon={CloseIcon} size="lg" />}
    </Dialog.Close>
  );
}

/**
 * The reference's bare "✕" (22 px, #9e9e9e, line height 1; app.js share, report and category modals).
 * The glyph keeps that size, and 9 px of padding taken back by a negative margin make the touch target
 * 40 px without moving anything. It must sit inside a base-ui `Dialog.Popup`.
 */
export function GlyphCloseButton({ className }: { className?: string }) {
  return (
    <Dialog.Close
      aria-label={t('ui.close')}
      className={cn('-m-[9px] shrink-0 cursor-pointer rounded-qb-sm p-[9px] text-[22px] leading-none text-qb-ink-subtle', focusRing, className)}
    >
      <span aria-hidden="true" className="block">
        ✕
      </span>
    </Dialog.Close>
  );
}

const MODAL_TITLES = {
  /** Confirmations ("Delete Chat?", 367:16294). */
  center: { title: 'text-center text-qb-h3 font-semibold', description: 'text-center' },
  /** Edit dialogs ("Edit Password", 411:9755 and 401:11108). */
  start: { title: 'pe-10 text-start text-qb-h5 font-medium', description: 'text-start' },
} as const;

export interface ModalProps extends DialogShellProps {
  titleAlign?: keyof typeof MODAL_TITLES;
  /** Control on the title row before the title, e.g. the back button of a dialog with steps. */
  headerStart?: ReactNode;
  /** Lets the body run to the dialog's edges, for lists that draw their own dividers. */
  flushBody?: boolean;
}

/** Centred dialog: focus trap, Esc and backdrop close. */
export function Modal(props: ModalProps) {
  const { title, description, children, showCloseButton = false, titleAlign = 'center', headerStart, flushBody = false, className } = props;
  const titles = MODAL_TITLES[titleAlign];
  const heading = <Dialog.Title className={cn('font-qb tracking-normal', titles.title, headerStart ? 'min-w-0 flex-1' : null)}>{title}</Dialog.Title>;
  return (
    <Shell {...props}>
      <Dialog.Popup
        className={cn(
          'fixed inset-x-4 top-1/2 z-50 mx-auto max-h-[calc(100dvh-32px)] max-w-[538px] -translate-y-1/2 overflow-y-auto',
          'rounded-qb-2xl border border-qb-line bg-qb-surface p-6 font-qb text-qb-ink',
          // The reference's dialogs fade in rising 8 px (qbFade .2s) and close at once.
          'animate-qb-dialog-enter motion-reduce:animate-none',
          focusRing,
          className,
        )}
      >
        {showCloseButton ? <ModalCloseButton className="absolute end-3 top-3" /> : null}
        {headerStart ? (
          <div className="flex items-center gap-2">
            {headerStart}
            {heading}
          </div>
        ) : (
          heading
        )}
        {description ? (
          <Dialog.Description className={cn('mt-2.5 text-qb-body text-qb-ink-secondary', titles.description)}>{description}</Dialog.Description>
        ) : null}
        {children ? <div className={cn('mt-4', flushBody ? '-mx-6 -mb-6' : null)}>{children}</div> : null}
      </Dialog.Popup>
    </Shell>
  );
}

/**
 * The reference slides its sheets with `transform` alone: the bottom sheet in
 * .34 s from 106 % down, the menu drawer in .28 s from 102 % beside the edge
 * (mobilemenu.js), both on the standard ease.
 */
const SHEET_SIDES = {
  bottom: {
    popup:
      'inset-x-0 bottom-0 max-h-[90dvh] rounded-t-qb-2xl px-6 pt-3 pb-6 duration-[340ms] data-starting-style:transform-[translateY(106%)] data-ending-style:transform-[translateY(106%)]',
    // .qb-sheet-back (mobilemenu.js): rgba(20,20,20,.45) fading in over .25 s.
    backdrop: 'bg-qb-overlay-strong duration-[250ms]',
    header: '',
    close: '-me-2',
    closeGlyph: undefined,
    body: 'mt-4',
  },
  start: {
    popup:
      'inset-y-0 start-0 w-[86vw] max-w-[340px] shadow-qb-drawer duration-[280ms] data-starting-style:transform-[translateX(-102%)] data-ending-style:transform-[translateX(-102%)] rtl:data-starting-style:transform-[translateX(102%)] rtl:data-ending-style:transform-[translateX(102%)]',
    backdrop: 'bg-qb-overlay-strong',
    header: 'border-b border-qb-line px-5 py-[18px]',
    // `.qb-mdrawer-x`: a 34 px white circle holding a 16 px × character.
    close: 'size-[34px] rounded-full border border-qb-line bg-qb-surface text-qb-body text-qb-icon-close hover:bg-qb-surface',
    closeGlyph: <span aria-hidden="true">×</span>,
    body: 'flex flex-col',
  },
} as const;

export interface SheetProps extends DialogShellProps {
  /** `bottom` for filters and pickers (618:26974), `start` for the phone menu drawer (648:47458). */
  side?: keyof typeof SHEET_SIDES;
  /** Visible content in place of the title (e.g. the logo); the title then names the dialog for screen readers only. */
  heading?: ReactNode;
  /** The grab bar of a bottom sheet; the reference's filter sheet has none. */
  showHandle?: boolean;
}

/** Sheet that slides in from the bottom or the start edge. */
export function Sheet(props: SheetProps) {
  const { title, heading, description, children, showCloseButton = true, showHandle = true, side = 'bottom', className } = props;
  const layout = SHEET_SIDES[side];
  return (
    <Shell {...props} backdropClassName={layout.backdrop}>
      <Dialog.Popup
        className={cn(
          'fixed z-50 flex flex-col overflow-y-auto bg-qb-surface font-qb text-qb-ink',
          'transition-[transform] ease-[cubic-bezier(.4,0,.2,1)] motion-reduce:transition-none',
          layout.popup,
          focusRing,
          className,
        )}
      >
        {side === 'bottom' && showHandle ? <div aria-hidden="true" className="mx-auto mb-4 h-1 w-12 shrink-0 rounded-qb-pill bg-qb-line" /> : null}
        <div className={cn('flex items-center justify-between gap-4', layout.header)}>
          <Dialog.Title className={cn('font-qb text-qb-h5 font-medium tracking-normal', heading ? 'sr-only' : null)}>{title}</Dialog.Title>
          {heading}
          {showCloseButton ? <ModalCloseButton className={layout.close} glyph={layout.closeGlyph} /> : null}
        </div>
        {description ? <Dialog.Description className="mt-1 text-qb-caption text-qb-ink-subtle">{description}</Dialog.Description> : null}
        {children ? <div className={cn('flex-1', layout.body)}>{children}</div> : null}
      </Dialog.Popup>
    </Shell>
  );
}
