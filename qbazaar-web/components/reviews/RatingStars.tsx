import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Read-only star rating. Renders 5 stars, filling `value` (0..5, rounded to
 * the nearest half is overkill here — we fill whole stars by rounding).
 */
export function RatingStars({
  value,
  className,
  size = 16,
}: {
  value: number;
  className?: string;
  size?: number;
}) {
  const filled = Math.round(value);

  return (
    <span
      className={cn('inline-flex items-center gap-0.5', className)}
      role="img"
      aria-label={`${value.toFixed(1)} / 5`}
    >
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          width={size}
          height={size}
          className={
            i < filled ? 'fill-coral text-coral' : 'text-ink-300'
          }
          aria-hidden
        />
      ))}
    </span>
  );
}
