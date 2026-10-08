'use client';

/**
 * "Rate the seller" entry on the ad detail page. Shown from the first render
 * to everyone but the seller; guests are sent to login and come back.
 * Eligibility (a completed deal) is enforced server-side: without one the API
 * answers `REVIEW_001` and we explain why.
 */
import { useId, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Loader2Icon, Star } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/design-system/Button';
import { Textarea } from '@/components/design-system/Input';
import { Modal } from '@/components/design-system/Modal';
import { createReview } from '@/lib/api/users';
import { useAuth } from '@/hooks/useAuth';
import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { currentLocationPath } from '@/lib/navigation/safe-return-to';
import { userKeys } from '@/lib/queries/users';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

const MAX_RATING = 5;

const REVIEW_ERROR_KEYS: Record<string, string> = {
  REVIEW_001: 'reviews.errors.not_eligible',
  REVIEW_002: 'reviews.errors.already',
  REVIEW_003: 'reviews.errors.own',
};

interface ReviewSellerButtonProps {
  adId: string;
  sellerId: string;
  className?: string;
}

export function ReviewSellerButton({ adId, sellerId, className }: ReviewSellerButtonProps) {
  const router = useRouter();
  const { user, isAuthenticated, isHydrated } = useAuth();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [missingRating, setMissingRating] = useState(false);
  const starsName = useId();
  const commentId = useId();
  const errorId = useId();
  const locale = getLocale();

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
      toast.error(t(REVIEW_ERROR_KEYS[code ?? ''] ?? 'common.error'));
    },
  });

  if (isHydrated && user?.id === sellerId) return null;

  const openDialog = () => {
    if (!isHydrated) return;
    if (!isAuthenticated) {
      router.push(`/login?from=${encodeURIComponent(currentLocationPath())}`);
      return;
    }
    setMissingRating(false);
    setOpen(true);
  };

  const submit = () => {
    if (mutation.isPending) return;
    if (rating < 1) {
      setMissingRating(true);
      return;
    }
    mutation.mutate();
  };

  const choose = (value: number) => {
    setRating(value);
    setMissingRating(false);
  };

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={openDialog}
        className={cn('font-normal text-qb-ink-subtle hover:text-qb-ink focus-visible:outline-solid', className)}
      >
        <Star aria-hidden />
        {t('reviews.rate_seller')}
      </Button>
      <Modal open={open} onOpenChange={setOpen} showCloseButton title={t('reviews.rate_seller')}>
        <fieldset aria-describedby={missingRating ? errorId : undefined}>
          <legend className="sr-only">{t('reviews.rating_label')}</legend>
          <div className="flex items-center justify-center gap-1 py-2">
            {Array.from({ length: MAX_RATING }, (_, index) => {
              const value = index + 1;
              return (
                <label
                  key={value}
                  className="cursor-pointer rounded-qb-sm p-1 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active"
                >
                  <input
                    type="radio"
                    name={starsName}
                    value={value}
                    checked={rating === value}
                    onChange={() => choose(value)}
                    className="sr-only"
                  />
                  <Star
                    aria-hidden
                    className={cn('size-8 transition-colors', value <= rating ? 'fill-qb-brand text-qb-brand' : 'text-qb-ink-disabled')}
                  />
                  <span className="sr-only">
                    {t('reviews.stars', { count: formatNumber(value, locale), max: formatNumber(MAX_RATING, locale) })}
                  </span>
                </label>
              );
            })}
          </div>
          {missingRating ? (
            <p id={errorId} role="alert" className="text-center text-qb-caption text-qb-danger">
              {t('reviews.errors.rating_required')}
            </p>
          ) : null}
        </fieldset>

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
          <Button variant="outline" onClick={() => setOpen(false)} className="focus-visible:outline-solid">
            {t('common.cancel')}
          </Button>
          <Button aria-disabled={mutation.isPending || undefined} onClick={submit} className="focus-visible:outline-solid">
            {mutation.isPending ? <Loader2Icon className="animate-spin" aria-hidden /> : null}
            {t('reviews.submit')}
          </Button>
        </div>
      </Modal>
    </>
  );
}
