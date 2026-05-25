'use client';

/**
 * Edit-ad client island.
 *
 * Guards the route via `useRequireAuth`, loads the ad, refuses unauthorised
 * access (404), and re-mounts the existing `PostAdWizard` in `edit` mode
 * with the loaded ad as the initial values.
 */
import Link from 'next/link';
import { ArrowLeft, Loader2Icon } from 'lucide-react';
import { PostAdWizard } from '@/components/ads/PostAdWizard';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { useAdQuery } from '@/lib/queries/ads';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n/messages';

interface Props {
  adId: string;
}

export function EditAdClient({ adId }: Props) {
  const { user, isLoading: authLoading } = useRequireAuth();
  const { data: ad, isLoading: adLoading, error } = useAdQuery(
    user ? adId : null,
  );

  if (authLoading || !user || adLoading) {
    return (
      <div
        className="flex min-h-svh items-center justify-center"
        role="status"
        aria-live="polite"
      >
        <Loader2Icon
          className="text-muted-foreground size-6 animate-spin"
          aria-hidden="true"
        />
      </div>
    );
  }

  // Owner check: an ad that exists but belongs to someone else is a 404 from
  // the user's perspective — we don't want to leak the title/contents.
  const isOwner = ad && ad.user_id === user.id;
  const isNotFound =
    !ad || !isOwner || (error as { code?: string } | null)?.code === 'AD_NOT_FOUND';

  if (isNotFound) {
    return (
      <main className="bg-cream-50 grid min-h-svh place-items-center px-4">
        <div className="text-center">
          <h1 className="font-display text-4xl text-ink-900">
            {t('ads.errors.ad_not_found', 'لم نعثر على هذا الإعلان')}
          </h1>
          <p className="text-ink-500 mt-2 text-sm">
            {t(
              'ads.errors.ad_not_found_body',
              'الإعلان ربما تم حذفه أو الرابط غير صحيح.',
            )}
          </p>
          <Button asChild className="mt-6">
            <Link href="/account/ads">
              {t('ads.my.title', 'إعلاناتي')}
            </Link>
          </Button>
        </div>
      </main>
    );
  }

  return (
    <main className="bg-cream-50 min-h-svh">
      <div className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
        <Link
          href="/account/ads"
          className="text-ink-500 mb-4 inline-flex items-center gap-1.5 text-sm hover:text-coral"
        >
          <ArrowLeft className="size-3.5 rtl:rotate-180" />
          {t('ads.edit.back_to_my_ads', 'العودة لإعلاناتي')}
        </Link>
        <h1 className="font-display text-4xl text-ink-900 md:text-5xl">
          {t('ads.edit.title', 'تعديل الإعلان')}
        </h1>
        <p className="text-ink-500 mt-2 max-w-lg text-sm">
          {t(
            'ads.edit.subtitle',
            'حدّث تفاصيل إعلانك ثم اضغط حفظ التعديلات. لن نعيد نشر الإعلان.',
          )}
        </p>
        <div className="mt-8">
          <PostAdWizard mode="edit" ad={ad} />
        </div>
      </div>
    </main>
  );
}
