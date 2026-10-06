'use client';

/**
 * "Rate the seller" entry on the ad detail page. Visible to signed-in users who
 * are not the seller. Eligibility (a completed deal) is enforced server-side —
 * if the buyer hasn't closed a deal the API returns 403 and we explain why.
 */
import { useId, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Loader2Icon, Star } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { focusRing } from '@/components/design-system/focus-ring';
import { Textarea } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { createReview } from '@/lib/api/users';
import { useAuth } from '@/hooks/useAuth';
import { userKeys } from '@/lib/queries/users';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

const MAX_RATING = 5;

interface ReviewSellerButtonProps {
  adId: string;
  sellerId: string;
  className?: string;
}

export function ReviewSellerButton({ adId, sellerId, className }: ReviewSellerButtonProps) {
  const { user, isAuthenticated, isHydrated } = useAuth();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const commentId = useId();

  const mutation = useMutation({
    mutationFn: () => createReview(adId, { rating, comment: comment.trim() || null }),
    onSuccess: () => {
      toast.success(t('reviews.submit_success'));
      setOpen(false);
      setRating(0);
      setComment('');
      queryClient.invalidateQueries({ queryKey: userKeys.detail(sellerId) });
    },
    onError: (err: unknown) => {
      const code = (err as { code?: string } | null)?.code;
      if (code === 'REVIEW_NOT_ELIGIBLE') toast.error(t('reviews.errors.not_eligible'));
      else if (code === 'REVIEW_ALREADY_EXISTS') toast.error(t('reviews.errors.already'));
      else if (code === 'REVIEW_OWN_AD') toast.error(t('reviews.errors.own'));
      else toast.error(t('common.error'));
    },
  });

  // Hidden for guests and for the seller themselves.
  if (!isHydrated || !isAuthenticated || user?.id === sellerId) {
    return null;
  }

  return (
    <Modal
      open={open}
      onOpenChange={setOpen}
      showCloseButton
      title={t('reviews.rate_seller')}
      trigger={
        <Button variant="ghost" size="sm" className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink', className)}>
          <Star aria-hidden />
          {t('reviews.rate_seller')}
        </Button>
      }
    >
      <div role="group" aria-label={t('reviews.rating_label')} className="flex items-center justify-center gap-1 py-2">
        {Array.from({ length: MAX_RATING }, (_, index) => {
          const value = index + 1;
          return (
            <button
              key={value}
              type="button"
              aria-pressed={rating === value}
              aria-label={t('reviews.stars', { count: value })}
              onClick={() => setRating(value)}
              className={cn('rounded-qb-sm p-1', focusRing)}
            >
              <Star
                aria-hidden
                className={cn('size-8 transition-colors', value <= rating ? 'fill-qb-brand text-qb-brand' : 'text-qb-ink-disabled')}
              />
            </button>
          );
        })}
      </div>

      <label htmlFor={commentId} className="sr-only">
        {t('reviews.comment_label')}
      </label>
      <Textarea
        id={commentId}
        value={comment}
        onChange={(event) => setComment(event.target.value)}
        rows={3}
        maxLength={1000}
        placeholder={t('reviews.comment_placeholder')}
        className="mt-3"
      />

      <div className="mt-6 flex flex-col-reverse gap-3 qb-tablet:flex-row qb-tablet:justify-end">
        <Button variant="outline" onClick={() => setOpen(false)}>
          {t('common.cancel')}
        </Button>
        <Button disabled={rating < 1 || mutation.isPending} onClick={() => mutation.mutate()}>
          {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
          {t('reviews.submit')}
        </Button>
      </div>
    </Modal>
  );
}
