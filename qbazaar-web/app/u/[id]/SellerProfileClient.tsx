'use client';

import { useSearchParams } from 'next/navigation';

import { ErrorView } from '@/components/status/ErrorView';
import { NotFoundView } from '@/components/status/NotFoundView';
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
  const { data, isPending, isFetchedAfterMount, error, refetch, isFetching } = usePublicProfileQuery(id, initialProfile);

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
      <main aria-busy="true" className="bg-qb-page px-qb-gutter pt-[clamp(20px,4vw,40px)] pb-16">
        <span className="sr-only">{t('common.loading')}</span>
        <div aria-hidden="true" className="mx-auto h-[600px] max-w-[1360px] animate-pulse rounded-qb-2xl bg-qb-fill motion-reduce:animate-none" />
      </main>
    );
  }

  if (error?.status === 404) {
    return <NotFoundView heading={t('users.profile.not_found_title')} description={t('users.profile.not_found_body')} />;
  }

  return <ErrorView onRetry={() => void refetch()} retrying={isFetching} />;
}
