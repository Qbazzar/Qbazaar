'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { UserX } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { isCompanyTab } from '@/components/users/CompanyTabs';
import { SellerProfileView } from '@/components/users/SellerProfileView';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { usePublicProfileQuery } from '@/lib/queries/users';
import type { PublicUserProfile } from '@/lib/api/types';

interface SellerProfileClientProps {
  id: string;
  initialProfile?: PublicUserProfile;
}

/**
 * Public profile client island: renders the server's copy at once and
 * refetches it with the viewer's session, which also tells whether the
 * viewer follows this seller.
 */
export function SellerProfileClient({ id, initialProfile }: SellerProfileClientProps) {
  const locale = getLocale();
  const tab = useSearchParams().get('tab');
  const { data, isPending, isFetchedAfterMount } = usePublicProfileQuery(id, initialProfile);

  if (data) {
    return (
      <SellerProfileView
        profile={data}
        locale={locale}
        viewerStateKnown={isFetchedAfterMount}
        defaultTab={isCompanyTab(tab) ? tab : 'ads'}
      />
    );
  }

  if (isPending) {
    return (
      <main aria-busy="true" className="bg-qb-page px-4 pt-10 pb-16 qb-tablet:px-6 qb-tablet:pt-[165px] qb-desktop:px-10">
        <div className="mx-auto h-[600px] max-w-[1360px] animate-pulse rounded-qb-2xl bg-qb-fill" />
      </main>
    );
  }

  return (
    <main className="bg-qb-page px-4 py-16 font-qb">
      <EmptyState
        icon={<Icon icon={UserX} size="lg" />}
        title={t('users.profile.not_found_title')}
        description={t('users.profile.not_found_body')}
        action={
          <Link href="/" className={buttonVariants()}>
            {t('users.profile.back_home')}
          </Link>
        }
      />
    </main>
  );
}
