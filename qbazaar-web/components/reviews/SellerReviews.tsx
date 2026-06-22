'use client';

/**
 * Read-only reviews block for a seller's public profile: a rating summary
 * (average + count + stars) followed by the list of individual reviews.
 */
import { useQuery } from '@tanstack/react-query';
import Image from 'next/image';
import { getUserReviews } from '@/lib/api/users';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { RatingStars } from './RatingStars';
import { formatRelativeTime } from '@/components/messaging/relative-time';
import { t } from '@/lib/i18n/messages';

export function SellerReviews({
  userId,
  ratingAvg,
  ratingCount,
}: {
  userId: string;
  ratingAvg: number;
  ratingCount: number;
}) {
  const { data, isLoading } = useQuery({
    queryKey: ['users', userId, 'reviews'],
    queryFn: () => getUserReviews(userId),
    enabled: ratingCount > 0,
  });

  return (
    <section className="bg-card ring-foreground/10 rounded-2xl p-5 ring-1 sm:p-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-display text-xl tracking-tight">
          {t('reviews.title', 'التقييمات')}
        </h2>
        {ratingCount > 0 ? (
          <div className="flex items-center gap-2">
            <span className="text-ink-900 text-lg font-bold">
              {ratingAvg.toFixed(1)}
            </span>
            <RatingStars value={ratingAvg} />
            <span className="text-ink-500 text-sm">
              ({ratingCount})
            </span>
          </div>
        ) : null}
      </header>

      {ratingCount === 0 ? (
        <p className="text-ink-500 mt-4 text-sm">
          {t('reviews.empty', 'لا توجد تقييمات بعد.')}
        </p>
      ) : isLoading ? (
        <p className="text-ink-500 mt-4 text-sm">
          {t('common.loading', 'جاري التحميل…')}
        </p>
      ) : (
        <ul className="mt-4 space-y-4">
          {(data?.data ?? []).map((review) => (
            <li
              key={review.id}
              className="border-ink-100 flex gap-3 border-b pb-4 last:border-0 last:pb-0"
            >
              <Avatar className="size-9 shrink-0">
                {review.reviewer?.avatar_url ? (
                  <Image
                    src={review.reviewer.avatar_url}
                    alt={review.reviewer.full_name}
                    width={36}
                    height={36}
                    className="size-full rounded-full object-cover"
                  />
                ) : (
                  <AvatarFallback>
                    {review.reviewer?.full_name?.charAt(0) ?? '؟'}
                  </AvatarFallback>
                )}
              </Avatar>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <span className="text-ink-900 truncate text-sm font-semibold">
                    {review.reviewer?.full_name ?? '—'}
                  </span>
                  <span className="text-ink-400 shrink-0 text-xs">
                    {formatRelativeTime(review.created_at)}
                  </span>
                </div>
                <RatingStars value={review.rating} size={13} className="mt-0.5" />
                {review.comment ? (
                  <p className="text-ink-700 mt-1 text-sm leading-relaxed whitespace-pre-wrap break-words">
                    {review.comment}
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
