/**
 * Compact status pill rendered inside `OfferBubble`.
 *
 * Colours follow the Bazzar palette:
 *  - pending   → coral fill (live, actionable)
 *  - accepted  → sage fill (success terminal)
 *  - rejected  → ink-500 fill (negative terminal)
 *  - withdrawn → ink-300 with strikethrough (silently cancelled)
 *  - expired   → ink-300 (timed out)
 */
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { OfferStatus } from '@/lib/api/types';

interface Props {
  status: OfferStatus;
  className?: string;
}

const STATUS_STYLES: Record<OfferStatus, string> = {
  pending: 'bg-coral text-white',
  accepted: 'bg-sage text-white',
  rejected: 'bg-ink-500 text-white',
  withdrawn: 'bg-ink-300 text-ink-700 line-through',
  expired: 'bg-ink-300 text-ink-700',
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
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold',
        STATUS_STYLES[status],
        className,
      )}
    >
      {label}
    </span>
  );
}
