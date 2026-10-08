import { ShareAdButton } from '@/components/ads/ShareAdButton';
import { ReportButton } from '@/components/reports/ReportButton';
import { formatAdDate } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Ad } from '@/lib/api/types';

import { detailCard, detailCardSide } from './detail-card';

interface AdFactsCardProps {
  ad: Pick<Ad, 'id' | 'title' | 'published_at' | 'created_at'>;
  /** The owner can't report their own ad. */
  isOwner: boolean;
  locale: Locale;
}

/** Ad id and date, with the share and report actions. */
export function AdFactsCard({ ad, isOwner, locale }: AdFactsCardProps) {
  return (
    <section aria-label={t('ads.detail.facts')} className={cn(detailCard, detailCardSide)}>
      <dl className="divide-y divide-qb-line text-qb-label qb-tablet:text-qb-caption qb-desktop:-mx-6 qb-desktop:text-qb-body">
        <FactRow label={t('ads.detail.ad_id_label')} value={ad.id} />
        <FactRow label={t('ads.detail.posted_on')} value={formatAdDate(ad.published_at ?? ad.created_at, locale)} />
      </dl>
      <div className="mt-4 flex gap-3 border-qb-line qb-tablet:flex-col qb-desktop:-mx-6 qb-desktop:flex-row qb-desktop:border-t qb-desktop:px-6 qb-desktop:pt-4">
        <ShareAdButton title={ad.title} className="h-[42px] flex-1 qb-tablet:h-10 qb-tablet:flex-none qb-desktop:flex-1" />
        {isOwner ? null : (
          <ReportButton
            target_type="ad"
            target_id={ad.id}
            label={t('ads.detail.report_ad')}
            className="h-[42px] flex-1 qb-tablet:h-10 qb-tablet:flex-none qb-desktop:flex-1"
          />
        )}
      </div>
    </section>
  );
}

function FactRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-start justify-between gap-4 py-3 first:pt-0 qb-desktop:px-6 qb-desktop:py-4 qb-desktop:first:pt-0">
      <dt className="shrink-0 text-qb-ink-subtle">{label}</dt>
      <dd className="min-w-0 text-end font-semibold [overflow-wrap:anywhere] text-qb-ink">{value}</dd>
    </div>
  );
}
