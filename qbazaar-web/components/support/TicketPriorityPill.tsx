import { Badge } from '@/components/design-system/Badge';
import { statusChipClass } from '@/components/account/AdStatusBadge';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SupportTicketPriority } from '@/lib/api/types';

interface Props {
  priority: SupportTicketPriority;
  showAll?: boolean;
  className?: string;
}

/**
 * Chip for a ticket's priority. Only `high` and `urgent` show by default —
 * low and normal are the silent baseline. Pass `showAll` to render every level.
 */
export function TicketPriorityPill({ priority, showAll, className }: Props) {
  if (!showAll && (priority === 'low' || priority === 'normal')) return null;
  return (
    <Badge tone={priority === 'urgent' ? 'danger' : 'neutral'} font="label" className={cn(statusChipClass, className)}>
      {t(`support.priority.${priority}`, priority)}
    </Badge>
  );
}
