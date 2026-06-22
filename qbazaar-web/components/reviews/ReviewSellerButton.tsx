'use client';

/**
 * "Rate the seller" entry on the ad detail page. Visible to signed-in users who
 * are not the seller. Eligibility (a completed deal) is enforced server-side —
 * if the buyer hasn't closed a deal the API returns 403 and we explain why.
 */
import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Star, Loader2Icon } from 'lucide-react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { createReview } from '@/lib/api/users';
import { useAuth } from '@/hooks/useAuth';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

export function ReviewSellerButton({
  adId,
  sellerId,
}: {
  adId: string;
  sellerId: string;
}) {
  const { user, isAuthenticated, isHydrated } = useAuth();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');

  const mutation = useMutation({
    mutationFn: () => createReview(adId, { rating, comment: comment.trim() || null }),
    onSuccess: () => {
      toast.success(t('reviews.submit_success', 'تم إرسال تقييمك، شكراً لك'));
      setOpen(false);
      setRating(0);
      setComment('');
      qc.invalidateQueries({ queryKey: ['users', sellerId] });
    },
    onError: (err: unknown) => {
      const code = (err as { code?: string } | null)?.code;
      if (code === 'REVIEW_NOT_ELIGIBLE') {
        toast.error(
          t('reviews.errors.not_eligible', 'يمكنك تقييم البائع بعد إتمام صفقة (عرض مقبول) على هذا الإعلان فقط'),
        );
      } else if (code === 'REVIEW_ALREADY_EXISTS') {
        toast.error(t('reviews.errors.already', 'لقد قيّمت هذا الإعلان من قبل'));
      } else if (code === 'REVIEW_OWN_AD') {
        toast.error(t('reviews.errors.own', 'لا يمكنك تقييم إعلانك الخاص'));
      } else {
        toast.error(t('common.error', 'حدث خطأ، حاول مرة أخرى'));
      }
    },
  });

  // Hidden for guests and for the seller themselves.
  if (!isHydrated || !isAuthenticated || user?.id === sellerId) {
    return null;
  }

  return (
    <>
      <Button
        type="button"
        variant="outline"
        size="lg"
        className="rounded-full"
        onClick={() => setOpen(true)}
      >
        <Star className="size-4" aria-hidden />
        {t('reviews.rate_seller', 'قيّم البائع')}
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('reviews.rate_seller', 'قيّم البائع')}</DialogTitle>
          </DialogHeader>

          <div className="flex items-center justify-center gap-1 py-2">
            {Array.from({ length: 5 }).map((_, i) => {
              const value = i + 1;
              return (
                <button
                  key={value}
                  type="button"
                  aria-label={`${value}`}
                  onClick={() => setRating(value)}
                  className="p-1"
                >
                  <Star
                    className={cn(
                      'size-8 transition-colors',
                      value <= rating
                        ? 'fill-coral text-coral'
                        : 'text-ink-300',
                    )}
                    aria-hidden
                  />
                </button>
              );
            })}
          </div>

          <Textarea
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            rows={3}
            maxLength={1000}
            placeholder={t('reviews.comment_placeholder', 'شارك تجربتك مع هذا البائع (اختياري)')}
          />

          <DialogFooter>
            <DialogClose
              render={
                <Button type="button" variant="outline" className="rounded-full">
                  {t('common.cancel', 'إلغاء')}
                </Button>
              }
            />
            <Button
              type="button"
              className="bg-coral hover:bg-coral/90 rounded-full text-white"
              disabled={rating < 1 || mutation.isPending}
              onClick={() => mutation.mutate()}
            >
              {mutation.isPending ? (
                <Loader2Icon className="size-4 animate-spin" aria-hidden />
              ) : null}
              {t('reviews.submit', 'إرسال التقييم')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
