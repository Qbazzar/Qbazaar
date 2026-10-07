'use client';

import type { ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
import { cn } from '@/lib/utils';

export interface ConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: ReactNode;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
  busy?: boolean;
  /** `danger` for actions that close something for good. */
  tone?: 'primary' | 'danger';
  /** Extra content between the text and the buttons, e.g. a reason field. */
  children?: ReactNode;
}

/**
 * The buttons under a dialog: the action first, then the way out, as the
 * account dialogs (401:10866). Stacked on phones, two equal halves from the
 * tablet up; the DOM order is the order on screen.
 */
export function DialogActions({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'mt-6 flex flex-col gap-3 qb-tablet:flex-row qb-tablet:gap-5 qb-tablet:*:min-w-0 qb-tablet:*:flex-1 qb-tablet:*:basis-0',
        className,
      )}
    >
      {children}
    </div>
  );
}

/** Centred confirmation for consequential steps (accept, cancel, confirm handover). */
export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  cancelLabel,
  onConfirm,
  busy = false,
  tone = 'primary',
  children,
}: ConfirmDialogProps) {
  return (
    <Modal open={open} onOpenChange={onOpenChange} title={title} description={description}>
      {children}
      <DialogActions>
        <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} disabled={busy} aria-busy={busy}>
          {busy ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
          {confirmLabel}
        </Button>
        <Button variant="muted" onClick={() => onOpenChange(false)} disabled={busy}>
          {cancelLabel}
        </Button>
      </DialogActions>
    </Modal>
  );
}
