import { Star } from 'lucide-react';

import { formatRating } from '@/lib/ads/display';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const MAX_RATING = 5;

/** Read-only five-star rating; `value` (0..5) is rounded to whole stars. */
export function RatingStars({ value, className, size = 16 }: { value: number; className?: string; size?: number }) {
  const locale = getLocale();
  const filled = Math.round(value);

  return (
    <span
      role="img"
      aria-label={t('reviews.stars_label', { rating: formatRating(value, locale), max: formatNumber(MAX_RATING, locale) })}
      className={cn('inline-flex items-center gap-0.5', className)}
    >
      {Array.from({ length: MAX_RATING }, (_, index) => (
        <Star
          key={index}
          width={size}
          height={size}
          aria-hidden
          className={index < filled ? 'fill-qb-brand text-qb-brand' : 'text-qb-ink-disabled'}
        />
      ))}
    </span>
  );
}
