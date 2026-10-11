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

/**
 * Title, price and the location / age / views lines: product.html's first
 * card under the photos. The title runs 22-28 px with the page width
 * (clamp(22px, 3vw, 28px)); the price stays 26 px.
 */
export function AdOverviewCard({ ad, locale }: AdOverviewCardProps) {
  const location = ad.location ? localized(ad.location.name, locale) : '';
  const hasAmount = ad.price != null && (ad.price_type === 'fixed' || ad.price_type === 'negotiable');

  return (
    <section className={cn(detailCard, detailCardMain)}>
      <div className="flex items-start justify-between gap-5">
        <div className="min-w-0 flex-1">
          {/* A sold ad, or an owner's ad that isn't live, wears its status chip as on My Ads. */}
          {ad.status !== 'active' ? <AdStatusBadge status={ad.status} className="mb-3" /> : null}
          <h1 dir="auto" className="text-[clamp(22px,3vw,28px)] leading-[1.3] font-semibold tracking-normal text-qb-ink">
            {ad.title}
          </h1>
        </div>
        <p className="flex shrink-0 flex-col items-end text-end">
          <span className={cn('text-[26px] font-semibold', hasAmount || ad.price_type === 'free' ? 'text-qb-brand' : 'text-qb-ink-subtle')}>
            {formatAdPrice(ad, locale)}
          </span>
          {ad.price_type === 'negotiable' && hasAmount ? (
            <span className="text-qb-body-sm text-qb-ink-subtle">{t('ads.price.negotiable')}</span>
          ) : null}
        </p>
      </div>

      <ul className="mt-5 flex flex-col gap-3.5 text-qb-body-sm text-qb-ink-secondary">
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

/** One meta line with its 18 px grey line icon. */
function MetaRow({ icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <li className="flex items-center gap-2.5">
      <Icon icon={icon} strokeWidth={1.6} className="size-[18px] text-qb-ink-subtle" />
      <span className="min-w-0">{children}</span>
    </li>
  );
}
