/**
 * Status chip of an offer card, bordered like the chat's "Pending" chip
 * (667:30685): pending orange, accepted green, rejected red, withdrawn and
 * expired grey.
 */
import type { VariantProps } from 'class-variance-authority';

import { Badge, type badgeVariants } from '@/components/design-system/Badge';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { OfferStatus } from '@/lib/api/types';

interface Props {
  status: OfferStatus;
  className?: string;
}

type Tone = NonNullable<VariantProps<typeof badgeVariants>['tone']>;

const STATUS_TONES: Record<OfferStatus, Tone> = {
  pending: 'brand',
  accepted: 'success',
  rejected: 'danger',
  withdrawn: 'neutral',
  expired: 'neutral',
};

const STATUS_FALLBACK: Record<OfferStatus, string> = {
  pending: 'قيد المراجعة',
  accepted: 'تم القبول',
  rejected: 'مرفوض',
  withdrawn: 'تم السحب',
  expired: 'منتهي',
};

export function OfferStatusBadge({ status, className }: Props) {
  const label = t(`messaging.offer.status.${status}`, STATUS_FALLBACK[status]);
  return (
    <Badge
      tone={STATUS_TONES[status]}
      size="sm"
      className={cn('border border-current font-qb-label', status === 'withdrawn' && 'line-through', className)}
    >
      {label}
    </Badge>
  );
}
