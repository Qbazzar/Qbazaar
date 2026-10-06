'use client';

/**
 * Edit-ad client island: guards the route, loads the ad, answers 404 for an
 * ad the user doesn't own, and opens the post-ad flow on it ("Complete" on
 * My Ads lands here for drafts).
 */
import Link from 'next/link';
import { FileQuestion, LoaderCircle } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { PostAdFlow } from '@/components/post-ad/PostAdFlow';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';
import { useAdQuery } from '@/lib/queries/ads';

interface Props {
  adId: string;
}

export function EditAdClient({ adId }: Props) {
  const { user, isLoading: authLoading } = useRequireAuth();
  const { data: ad, isLoading: adLoading } = useAdQuery(user ? adId : null);

  if (authLoading || !user || adLoading) {
    return (
      <div role="status" aria-live="polite" className="flex min-h-[60vh] items-center justify-center">
        <Icon icon={LoaderCircle} size="lg" label={t('common.loading')} className="animate-spin text-qb-ink-subtle motion-reduce:animate-none" />
      </div>
    );
  }

  // Someone else's ad is a 404 too, so its title and contents don't leak.
  if (!ad || ad.user_id !== user.id) {
    return (
      <EmptyState
        className="rounded-qb-2xl border border-qb-line bg-qb-surface"
        headingLevel="h2"
        icon={<Icon icon={FileQuestion} size="lg" />}
        title={t('ads.errors.ad_not_found', 'لم نعثر على هذا الإعلان')}
        description={t('ads.errors.ad_not_found_body', 'الإعلان ربما تم حذفه أو الرابط غير صحيح.')}
        action={
          <Link href="/account/ads" className={buttonVariants()}>
            {t('ads.my.title', 'إعلاناتي')}
          </Link>
        }
      />
    );
  }

  return (
    <div className="font-qb text-qb-ink">
      <PostAdFlow
        user={user}
        ad={ad}
        breadcrumb={[
          { label: t('ads.my.title', 'إعلاناتي'), href: '/account/ads' },
          { label: t('post_ad.edit_title') },
        ]}
      />
    </div>
  );
}
