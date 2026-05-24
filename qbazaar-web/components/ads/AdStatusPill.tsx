/**
 * Coloured badge for an ad's lifecycle status.
 *
 * Used on the MyAds rows + sparingly on the detail page when an ad isn't
 * active (e.g. a draft preview). The colour map mirrors the Bazzar palette:
 *
 *   - active   → sage  (positive, live)
 *   - draft    → ink   (neutral, not visible to others)
 *   - pending  → coral (in-review)
 *   - sold     → ink-700 muted
 *   - expired  → ink-500 with strike
 *   - rejected → destructive
 *   - blocked  → destructive
 */
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { AdStatus } from '@/lib/api/types';

interface Props {
  status: AdStatus;
  className?: string;
}

const STATUS_CLASSES: Record<AdStatus, string> = {
  active: 'bg-sage/15 text-sage',
  draft: 'bg-ink-200 text-ink-700',
  pending: 'bg-coral/15 text-coral',
  sold: 'bg-ink-700/10 text-ink-700',
  expired: 'bg-ink-200 text-ink-500 line-through',
  rejected: 'bg-destructive/10 text-destructive',
  blocked: 'bg-destructive/10 text-destructive',
};

export function AdStatusPill({ status, className }: Props) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider',
        STATUS_CLASSES[status],
        className,
      )}
    >
      {t(`ads.status.${status}`, status)}
    </span>
  );
}
