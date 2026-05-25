/**
 * Coloured pill for a support ticket's lifecycle status.
 *
 * Colours: coral=open, sage=resolved, blue=in_progress,
 * amber=waiting_user, ink-500=closed.
 */
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SupportTicketStatus } from '@/lib/api/types';

interface Props {
  status: SupportTicketStatus;
  className?: string;
}

export function TicketStatusPill({ status, className }: Props) {
  return (
    <span className={cn('ticket-pill', `ticket-pill--${status}`, className)}>
      {t(`support.status.${status}`, status)}
    </span>
  );
}
