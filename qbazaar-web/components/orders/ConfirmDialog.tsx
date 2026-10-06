'use client';

import type { ReactNode } from 'react';
import { Loader2 } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';

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
      <div className="mt-6 flex flex-col-reverse gap-3 qb-tablet:flex-row">
        <Button variant="muted" fullWidth onClick={() => onOpenChange(false)} disabled={busy}>
          {cancelLabel}
        </Button>
        <Button variant={tone === 'danger' ? 'danger' : 'primary'} fullWidth onClick={onConfirm} disabled={busy} aria-busy={busy}>
          {busy ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
          {confirmLabel}
        </Button>
      </div>
    </Modal>
  );
}
