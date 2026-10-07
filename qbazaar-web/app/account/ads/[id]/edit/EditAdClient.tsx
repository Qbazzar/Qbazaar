'use client';

/**
 * Edit-ad client island: guards the route, loads the ad, answers 404 for an
 * ad the user doesn't own, and opens the post-ad flow on it ("Complete" on
 * My Ads lands here for drafts). The account layout owns the `<main>`.
 */
import type { ReactNode } from 'react';
import Link from 'next/link';
import { FileQuestion } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { visibleFocus } from '@/components/post-ad/FormParts';
import { PostAdFlow } from '@/components/post-ad/PostAdFlow';
import { PostAdFrame, PostAdLoading } from '@/components/post-ad/PostAdPage';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';
import { useAdQuery } from '@/lib/queries/ads';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';
import { cn } from '@/lib/utils';

interface Props {
  adId: string;
}

export function EditAdClient({ adId }: Props) {
  const { user, isLoading: authLoading } = useRequireAuth();
  const { data: ad, isLoading: adLoading } = useAdQuery(user ? adId : null);
  // Loaded alongside the ad; the form reads them from the cache.
  useCategoryTreeQuery();
  useQatarLocationsQuery();

  let content: ReactNode;
  if (authLoading || !user || adLoading) {
    content = <PostAdLoading />;
  } else if (!ad || ad.user_id !== user.id) {
    // Someone else's ad is a 404 too, so its title and contents don't leak.
    content = (
      <StatePanel
        icon={<StateIcon icon={FileQuestion} tone="muted" />}
        title={t('ads.errors.ad_not_found', 'لم نعثر على هذا الإعلان')}
        description={t('ads.errors.ad_not_found_body', 'الإعلان ربما تم حذفه أو الرابط غير صحيح.')}
        action={
          <Link href="/account/ads" className={cn(buttonVariants(), visibleFocus)}>
            {t('ads.my.title', 'إعلاناتي')}
          </Link>
        }
      />
    );
  } else {
    content = (
      <PostAdFlow
        user={user}
        ad={ad}
        breadcrumb={[
          { label: t('post_ad.breadcrumb.home'), href: '/' },
          { label: t('post_ad.breadcrumb.my_ads'), href: '/account/ads' },
          { label: t('post_ad.edit_title') },
        ]}
      />
    );
  }

  return <PostAdFrame>{content}</PostAdFrame>;
}
