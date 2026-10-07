'use client';

import { PhoneVerificationNotice } from '@/components/auth/PhoneVerificationNotice';
import { Breadcrumb } from '@/components/design-system/Breadcrumb';
import { PostAdFlow } from '@/components/post-ad/PostAdFlow';
import { PostAdLoading, formBreadcrumbClass } from '@/components/post-ad/PostAdPage';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';
import { useCategoryTreeQuery } from '@/lib/queries/categories';
import { useQatarLocationsQuery } from '@/lib/queries/locations';

/** Signed-in sellers with a verified phone post here; others are sent on first (FE-16.10). */
export function PostAdClient() {
  const { user, isLoading } = useRequireAuth();
  // Loaded while sign-in resolves; the form reads them from the cache.
  useCategoryTreeQuery();
  useQatarLocationsQuery();

  if (isLoading || !user) return <PostAdLoading />;

  const breadcrumb = [{ label: t('post_ad.breadcrumb.home'), href: '/' }, { label: t('post_ad.breadcrumb.add_ads') }];

  if (!user.phone_verified) {
    return (
      <>
        <Breadcrumb items={breadcrumb} className={formBreadcrumbClass} />
        <h1 className="sr-only">{t('post_ad.page_title')}</h1>
        <PhoneVerificationNotice context="post_ad" />
      </>
    );
  }

  return <PostAdFlow user={user} breadcrumb={breadcrumb} />;
}
