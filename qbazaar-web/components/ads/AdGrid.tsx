/**
 * Responsive grid of `AdCard`s.
 *
 * Server-friendly (no client state). Empty state slot lets the parent inject
 * a contextual message (e.g. "no ads in this category yet").
 */
import { AdCard } from './AdCard';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';

interface Props {
  ads: AdSummary[];
  className?: string;
  empty?: React.ReactNode;
}

export function AdGrid({ ads, className, empty }: Props) {
  if (ads.length === 0) {
    return (
      <div
        className={cn(
          'rounded-xl border border-dashed border-ink-200 bg-cream-50 px-6 py-12 text-center text-sm text-ink-500',
          className,
        )}
      >
        {empty ?? t('ads.empty.no_ads', 'لا توجد إعلانات هنا بعد.')}
      </div>
    );
  }
  return (
    <ul
      className={cn(
        'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
        className,
      )}
    >
      {ads.map((ad) => (
        <li key={ad.id}>
          <AdCard ad={ad} />
        </li>
      ))}
    </ul>
  );
}
