import type { ReactNode } from 'react';
import { CalendarDays, Eye, MapPin, type LucideIcon } from 'lucide-react';

import { AdStatusBadge } from '@/components/account/AdStatusBadge';
import { Icon } from '@/components/design-system/Icon';
import { formatTimeAgo } from '@/lib/ads/display';
import { formatAdPrice } from '@/lib/ads/format';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';
import type { Ad } from '@/lib/api/types';

import { detailCard, detailCardMain } from './detail-card';

interface AdOverviewCardProps {
  ad: Ad;
  locale: Locale;
}

/** Title, price and the location / age / views lines (the first card under the photos). */
export function AdOverviewCard({ ad, locale }: AdOverviewCardProps) {
  const location = ad.location ? localized(ad.location.name, locale) : '';
  const hasAmount = ad.price != null && (ad.price_type === 'fixed' || ad.price_type === 'negotiable');

  return (
    <section className={cn(detailCard, detailCardMain)}>
      <div className="flex items-start justify-between gap-4 qb-desktop:gap-10">
        <div className="min-w-0">
          {/* A sold ad, or an owner's ad that isn't live, wears its status chip as on My Ads. */}
          {ad.status !== 'active' ? <AdStatusBadge status={ad.status} className="mb-3" /> : null}
          <h1
            dir="auto"
            className="text-qb-caption leading-[1.25] font-semibold tracking-normal text-balance text-qb-ink-title qb-desktop:text-qb-h3"
          >
            {ad.title}
          </h1>
        </div>
        <p className="flex shrink-0 flex-col items-end gap-1.5 text-end">
          <span
            className={cn(
              'text-qb-body font-semibold qb-tablet:text-qb-caption qb-desktop:text-qb-h3',
              hasAmount || ad.price_type === 'free' ? 'text-qb-brand' : 'text-qb-ink-subtle',
            )}
          >
            {formatAdPrice(ad, locale)}
          </span>
          {ad.price_type === 'negotiable' && hasAmount ? (
            <span className="text-qb-micro text-qb-ink-subtle qb-desktop:text-qb-caption">{t('ads.price.negotiable')}</span>
          ) : null}
        </p>
      </div>

      <ul className="mt-7 flex flex-col gap-[18px] text-qb-micro text-qb-ink-subtle qb-tablet:mt-6 qb-desktop:gap-3.5 qb-desktop:text-qb-body">
        {location ? <MetaRow icon={MapPin}>{location}</MetaRow> : null}
        {ad.published_at ? (
          <MetaRow icon={CalendarDays}>
            {/* Relative to "now", so the server and the browser may differ by a minute. */}
            <span suppressHydrationWarning>{formatTimeAgo(ad.published_at, locale)}</span>
          </MetaRow>
        ) : null}
        <MetaRow icon={Eye}>{tPlural('ads.detail.views', ad.views_count, locale)}</MetaRow>
      </ul>
    </section>
  );
}

function MetaRow({ icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <li className="flex items-center gap-2">
      <Icon icon={icon} size="sm" className="qb-desktop:size-6" />
      <span className="min-w-0">{children}</span>
    </li>
  );
}
