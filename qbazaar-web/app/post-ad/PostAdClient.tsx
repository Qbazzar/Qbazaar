'use client';

import { LoaderCircle } from 'lucide-react';

import { PhoneVerificationNotice } from '@/components/auth/PhoneVerificationNotice';
import { Icon } from '@/components/design-system/Icon';
import { PostAdFlow } from '@/components/post-ad/PostAdFlow';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { t } from '@/lib/i18n/messages';

/** Signed-in sellers with a verified phone post here; others are sent on first (FE-16.10). */
export function PostAdClient() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading || !user) {
    return (
      <div role="status" className="flex min-h-[60vh] items-center justify-center">
        <Icon icon={LoaderCircle} size="lg" label={t('common.loading')} className="animate-spin text-qb-ink-subtle motion-reduce:animate-none" />
      </div>
    );
  }

  if (!user.phone_verified) return <PhoneVerificationNotice context="post_ad" />;

  return (
    <PostAdFlow
      user={user}
      breadcrumb={[
        { label: t('post_ad.breadcrumb.home'), href: '/' },
        { label: t('post_ad.breadcrumb.add_ads') },
      ]}
    />
  );
}
