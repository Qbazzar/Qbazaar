/**
 * Coloured pill for a support ticket's priority.
 *
 * Only renders for `high` / `urgent` by default — low/normal priorities are
 * the silent baseline. Pass `showAll` to render every level.
 */
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SupportTicketPriority } from '@/lib/api/types';

interface Props {
  priority: SupportTicketPriority;
  showAll?: boolean;
  className?: string;
}

export function TicketPriorityPill({ priority, showAll, className }: Props) {
  if (!showAll && (priority === 'low' || priority === 'normal')) return null;
  return (
    <span
      className={cn(
        'ticket-pill',
        `ticket-pill--priority-${priority}`,
        className,
      )}
    >
      {t(`support.priority.${priority}`, priority)}
    </span>
  );
}
