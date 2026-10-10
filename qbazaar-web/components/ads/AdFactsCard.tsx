import { ShareAdButton } from '@/components/ads/ShareAdButton';
import { ReportButton } from '@/components/reports/ReportButton';
import { formatDottedDate } from '@/lib/ads/dates';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Ad } from '@/lib/api/types';

import { detailCard, detailCardSide } from './detail-card';

interface AdFactsCardProps {
  ad: Pick<Ad, 'id' | 'title' | 'published_at' | 'created_at'>;
  /** The owner can't report their own ad. */
  isOwner: boolean;
}

/** "Share Ad" and "Report Ad": two equal outlined buttons that keep their look under the pointer. */
const factsAction =
  'h-auto min-h-10 min-w-0 flex-1 gap-[7px] rounded-qb-md border border-qb-line bg-qb-surface px-2.5 py-[11px] text-qb-caption leading-[1.15] font-normal whitespace-normal text-qb-ink-body hover:bg-qb-surface hover:text-qb-ink-body [&_svg]:size-4';

/** Ad id and date (dd.mm.yyyy), with the share and report actions (product.html). */
export function AdFactsCard({ ad, isOwner }: AdFactsCardProps) {
  return (
    <section aria-label={t('ads.detail.facts')} className={cn(detailCard, detailCardSide)}>
      <dl className="text-qb-body">
        <FactRow label={t('ads.detail.ad_id_label')} value={ad.id} className="border-b border-qb-line pb-3.5" />
        <FactRow label={t('ads.detail.posted_on')} value={formatDottedDate(ad.published_at ?? ad.created_at)} className="py-3.5" />
      </dl>
      <div className="flex gap-2.5 border-t border-qb-line pt-4">
        <ShareAdButton title={ad.title} className={factsAction} />
        {isOwner ? null : <ReportButton target_type="ad" target_id={ad.id} label={t('ads.detail.report_ad')} className={factsAction} />}
      </div>
    </section>
  );
}

function FactRow({ label, value, className }: { label: string; value: string; className?: string }) {
  return (
    <div className={cn('flex items-start justify-between gap-4', className)}>
      <dt className="shrink-0 text-qb-ink-faint">{label}</dt>
      {/* The 26-character ad id breaks in a narrow sidebar; balancing keeps a lone character off the last line. */}
      <dd className="min-w-0 text-end font-semibold text-balance break-all text-qb-ink">{value}</dd>
    </div>
  );
}
