'use client';

/**
 * A seller's reviews: the average with its stars and count, then one row per
 * review in the notification-row style of the design (455:14636).
 */
import { useId } from 'react';
import { Clock } from 'lucide-react';

import { Avatar } from '@/components/design-system/Avatar';
import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import { formatRating, formatTimeAgo } from '@/lib/ads/display';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { useUserReviewsQuery } from '@/lib/queries/users';
import { cn } from '@/lib/utils';

import { RatingStars } from './RatingStars';

interface SellerReviewsProps {
  userId: string;
  ratingAvg: number;
  ratingCount: number;
  className?: string;
}

const row = cardVariants({ large: true, elevated: true, padding: 'none' });

export function SellerReviews({ userId, ratingAvg, ratingCount, className }: SellerReviewsProps) {
  const locale = getLocale();
  const titleId = useId();
  const { data, isPending } = useUserReviewsQuery(userId, ratingCount > 0);
  const reviews = data?.data ?? [];

  return (
    <section aria-labelledby={titleId} className={cn('font-qb', className)}>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h2 id={titleId} className="text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h4">
          {t('reviews.title')}
        </h2>
        {ratingCount > 0 ? (
          <p className="flex items-center gap-2 text-qb-caption text-qb-ink-subtle">
            <span className="text-qb-body-lg font-semibold text-qb-ink">{formatRating(ratingAvg, locale)}</span>
            <RatingStars value={ratingAvg} />
            <span>{tPlural('reviews.count', ratingCount, locale)}</span>
          </p>
        ) : null}
      </div>

      {ratingCount === 0 ? (
        <p className={cn(row, 'mt-4 p-6 text-qb-caption text-qb-ink-subtle')}>{t('reviews.empty')}</p>
      ) : isPending ? (
        <div aria-busy="true" className="mt-4 flex flex-col gap-4">
          <span className="sr-only">{t('common.loading')}</span>
          <div aria-hidden="true" className={cn(row, 'h-[104px] animate-pulse bg-qb-fill motion-reduce:animate-none')} />
          <div aria-hidden="true" className={cn(row, 'h-[104px] animate-pulse bg-qb-fill motion-reduce:animate-none')} />
        </div>
      ) : (
        <ul className="mt-4 flex flex-col gap-4">
          {reviews.map((review) => {
            const reviewer = review.reviewer?.full_name ?? t('reviews.deleted_user');
            return (
              <li key={review.id} className={cn(row, 'flex gap-4 p-4 qb-tablet:p-5')}>
                {/* The name follows in text. */}
                <span aria-hidden="true" className="flex shrink-0">
                  <Avatar name={reviewer} src={review.reviewer?.avatar_url} size="md" tone="brand" />
                </span>
                <div className="min-w-0 flex-1">
                  <p dir="auto" className="truncate text-qb-body font-medium text-qb-ink-body">
                    {reviewer}
                  </p>
                  <RatingStars value={review.rating} size={14} className="mt-1.5" />
                  {review.comment ? (
                    <p dir="auto" className="mt-2 text-qb-caption leading-[1.5] break-words whitespace-pre-line text-qb-ink-muted">
                      {review.comment}
                    </p>
                  ) : null}
                  <p className="mt-2 flex items-center gap-1.5 text-qb-label text-qb-ink-subtle">
                    <Icon icon={Clock} size="sm" />
                    {formatTimeAgo(review.created_at, locale)}
                  </p>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
