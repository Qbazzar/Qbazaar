import type { VariantProps } from 'class-variance-authority';

import { Badge, type badgeVariants } from '@/components/design-system/Badge';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdStatus } from '@/lib/api/types';

type Tone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

/** Draft red, live green, in review blue, as the Draft / Publish / Reversed chips of 518:20536. */
const STATUS_TONES: Record<AdStatus, Tone> = {
  draft: 'danger',
  pending: 'info',
  active: 'success',
  sold: 'neutral',
  expired: 'neutral',
  rejected: 'danger',
  blocked: 'danger',
};

/** Outlined Montserrat 14 px status chip of 518:20536, shared with the support ticket rows. */
export const statusChipClass = 'border border-current font-qb-label text-qb-caption';

export function AdStatusBadge({ status, className }: { status: AdStatus; className?: string }) {
  return (
    <Badge tone={STATUS_TONES[status]} className={cn(statusChipClass, className)}>
      {t(`ads.status.${status}`)}
    </Badge>
  );
}
