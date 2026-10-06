import { Star } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** Read-only five-star rating; `value` (0..5) is rounded to whole stars. */
export function RatingStars({ value, className, size = 16 }: { value: number; className?: string; size?: number }) {
  const filled = Math.round(value);

  return (
    <span
      role="img"
      aria-label={t('reviews.stars_label', { rating: value.toFixed(1) })}
      className={cn('inline-flex items-center gap-0.5', className)}
    >
      {Array.from({ length: 5 }, (_, index) => (
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
