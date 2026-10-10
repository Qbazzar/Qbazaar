import type { VariantProps } from 'class-variance-authority';

import { Badge, type badgeVariants } from '@/components/design-system/Badge';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdStatus } from '@/lib/api/types';

type Tone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

/** Draft red, live green, in review blue, as the Draft / Publish / Reversed chips of my-ads (stBg / stFg). */
const STATUS_TONES: Record<AdStatus, Tone> = {
  draft: 'danger',
  pending: 'info',
  active: 'success',
  sold: 'neutral',
  expired: 'neutral',
  rejected: 'danger',
  blocked: 'danger',
};

/** Soft 13 px status chip of my-ads (Montserrat 500, 5×14 padding, r8), shared with the support ticket rows. */
export const statusChipClass = 'rounded-qb-sm px-3.5 py-[5px] text-qb-label';

/** A live ad the seller holds for a buyer shows the blue "Reserved" chip instead of "Active". */
export function AdStatusBadge({
  status,
  reserved = false,
  className,
}: {
  status: AdStatus;
  reserved?: boolean;
  className?: string;
}) {
  const isReserved = reserved && status === 'active';
  return (
    <Badge tone={isReserved ? 'info' : STATUS_TONES[status]} font="label" className={cn(statusChipClass, className)}>
      {isReserved ? t('account.my_ads.reserved') : t(`ads.status.${status}`)}
    </Badge>
  );
}
