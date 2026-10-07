import type { VariantProps } from 'class-variance-authority';

import { Badge, type badgeVariants } from '@/components/design-system/Badge';
import { statusChipClass } from '@/components/account/AdStatusBadge';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SupportTicketStatus } from '@/lib/api/types';

type Tone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

/**
 * Open orange, in progress blue, waiting on the user red (their turn, like a
 * draft that needs completing), resolved green, closed grey: the status chips
 * of the My Ads rows (518:20536).
 */
const STATUS_TONES: Record<SupportTicketStatus, Tone> = {
  open: 'brand',
  in_progress: 'info',
  waiting_user: 'danger',
  resolved: 'success',
  closed: 'neutral',
};

export function TicketStatusPill({ status, className }: { status: SupportTicketStatus; className?: string }) {
  return (
    <Badge tone={STATUS_TONES[status]} className={cn(statusChipClass, className)}>
      {t(`support.status.${status}`, status)}
    </Badge>
  );
}
