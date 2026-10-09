'use client';

import type { ReactNode } from 'react';
import { Dialog } from '@base-ui/react/dialog';
import { Loader2, Trash2 } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

type Variant = 'block' | 'delete';

interface ChatConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** `block`: "Block User?" of chat.js (604:33981). `delete`: "Delete this conversation?" of messages.html. */
  variant: Variant;
  title: string;
  body: string;
  confirmLabel: string;
  onConfirm: () => void;
  pending?: boolean;
}

const VARIANTS: Record<
  Variant,
  { backdrop: string; popup: string; title: string; body: string; cancel: string; cancelFirst: boolean; icon: ReactNode }
> = {
  block: {
    backdrop: 'bg-qb-chat-overlay',
    popup: 'w-[min(340px,88vw)] rounded-[20px] px-[22px] pt-[26px] pb-[22px] shadow-qb-chat-dialog',
    title: 'mb-2.5 font-bold',
    body: 'mb-5 leading-normal text-qb-chat-dialog-body',
    cancel: 'bg-qb-fill-strong text-qb-ink',
    cancelFirst: false,
    icon: null,
  },
  delete: {
    backdrop: 'bg-qb-chat-delete-overlay',
    popup: 'w-[min(420px,calc(100vw-40px))] rounded-[18px] p-7 shadow-qb-chat-delete',
    title: 'mb-2 font-semibold',
    body: 'mb-[22px] leading-[1.6] text-qb-ink-subtle',
    cancel: 'border border-qb-line bg-qb-surface text-qb-ink-body',
    cancelFirst: true,
    icon: (
      <span
        aria-hidden="true"
        className="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-qb-brand-soft text-qb-brand"
      >
        <Trash2 className="size-[30px]" strokeWidth={1.7} />
      </span>
    ),
  },
};

/**
 * The centred confirmations of the messages screen, in each one's own
 * look. Escape and a click on the dimmed page close them.
 */
export function ChatConfirmDialog({
  open,
  onOpenChange,
  variant,
  title,
  body,
  confirmLabel,
  onConfirm,
  pending = false,
}: ChatConfirmDialogProps) {
  const look = VARIANTS[variant];
  const buttonClass = cn(
    'flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-qb-md p-[13px] text-qb-caption font-semibold disabled:cursor-progress',
    focusRing,
  );
  const confirm = (
    <button
      type="button"
      onClick={onConfirm}
      disabled={pending}
      className={cn(buttonClass, 'bg-qb-brand text-qb-on-brand hover:bg-qb-brand-hover')}
    >
      {pending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
      {confirmLabel}
    </button>
  );
  const cancel = (
    <Dialog.Close disabled={pending} className={cn(buttonClass, look.cancel)}>
      {t('common.cancel')}
    </Dialog.Close>
  );

  return (
    <Dialog.Root
      open={open}
      onOpenChange={(next) => {
        if (!pending) onOpenChange(next);
      }}
    >
      <Dialog.Portal>
        <Dialog.Backdrop
          className={cn(
            'fixed inset-0 z-50 transition-opacity duration-200 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none',
            look.backdrop,
          )}
        />
        <Dialog.Popup
          className={cn(
            'fixed top-1/2 left-1/2 z-50 -translate-x-1/2 -translate-y-1/2 bg-qb-surface text-center font-qb',
            'transition-[opacity,scale] duration-200 data-starting-style:scale-95 data-starting-style:opacity-0 data-ending-style:opacity-0 motion-reduce:transition-none',
            look.popup,
            focusRing,
          )}
        >
          {look.icon}
          <Dialog.Title className={cn('text-qb-h5 tracking-normal text-qb-ink', look.title)}>{title}</Dialog.Title>
          <Dialog.Description className={cn('text-qb-caption', look.body)}>{body}</Dialog.Description>
          <div className="flex gap-3">
            {look.cancelFirst ? cancel : confirm}
            {look.cancelFirst ? confirm : cancel}
          </div>
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
