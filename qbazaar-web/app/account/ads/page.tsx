'use client';

/**
 * `/account/ads` — owner's ads list (acctShowDashboard, 518:20536), organised by status tab.
 *
 * Each tab issues a separate `useMyAdsQuery` so the cache can stay scoped
 * per-status. The status tabs have no frame on this page, so they use the
 * pill tabs of the notifications screen.
 *
 * Auth is enforced by the parent `app/account/layout.tsx`.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Megaphone } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { AccountPage } from '@/components/account/AccountPage';
import { MyAdsRow } from '@/components/account/MyAdsRow';
import { PanelState } from '@/components/account/PanelState';
import { PillTab } from '@/components/account/PillTab';
import { ProfileSummary } from '@/components/account/ProfileSummary';
import { useSlugLabels } from '@/components/account/useSlugLabels';
import { useAuth } from '@/hooks/useAuth';
import { useAccountSummaryQuery } from '@/lib/queries/account';
import { useMyAdsQuery } from '@/lib/queries/ads';
import { usePublicProfileQuery } from '@/lib/queries/users';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import type { AdStatus } from '@/lib/api/types';

type TabKey = 'all' | AdStatus;

const TABS: TabKey[] = ['all', 'active', 'draft', 'sold', 'expired'];

export default function MyAdsPage() {
  const { user } = useAuth();
  const [tab, setTab] = useState<TabKey>('all');
  const { data: summary } = useAccountSummaryQuery();
  const { data: publicProfile } = usePublicProfileQuery(user?.id ?? '');

  return (
    <AccountPage title={t('account.nav.my_ads')} variant="account">
      <div className="flex flex-col gap-5">
        {user ? (
          <ProfileSummary
            name={user.full_name}
            avatarUrl={user.avatar_url}
            accountType={user.account_type}
            joinedAt={user.created_at}
            subtitle={summary ? tPlural('account.my_ads.online', summary.ads_by_status.active ?? 0) : undefined}
            follows={
              publicProfile
                ? { followers: publicProfile.followers_count, following: publicProfile.following_count }
                : undefined
            }
          />
        ) : null}

        <h2 className="mt-2 text-qb-h3 leading-normal font-semibold tracking-normal text-qb-ink">
          {t('account.nav.my_ads')}
        </h2>
      </div>

      <Tabs value={tab} onValueChange={(value) => setTab(value as TabKey)} className="mt-5">
        <TabList aria-label={t('account.my_ads.tabs_label')} scrollOnPhones className="gap-4">
          {TABS.map((key) => (
            <PillTab key={key} value={key}>
              {t(`ads.my.tabs.${key}`, key)}
            </PillTab>
          ))}
        </TabList>
        {TABS.map((key) => (
          <TabPanel key={key} value={key} className="mt-5">
            <MyAdsTab status={key === 'all' ? undefined : key} />
          </TabPanel>
        ))}
      </Tabs>
    </AccountPage>
  );
}

function MyAdsTab({ status }: { status?: AdStatus }) {
  const { data, isLoading, isError } = useMyAdsQuery({ status });
  const labels = useSlugLabels();

  if (isLoading) return <PanelState loading />;
  if (isError || !data) return <PanelState loading={false} message={t('common.error', 'حدث خطأ، حاول مرة أخرى')} />;

  if (data.data.length === 0) {
    return (
      <EmptyState
        icon={<Icon icon={Megaphone} size="lg" />}
        title={t('ads.empty.no_my_ads', 'لا توجد إعلانات في هذا التبويب بعد.')}
        description={t('account.my_ads.empty_body')}
        action={
          <Link href="/post-ad" className={buttonVariants({ size: 'sm' })}>
            {t('ads.actions.post_ad', 'انشر إعلانك الأول')}
          </Link>
        }
        className="rounded-qb-2xl border border-qb-line bg-qb-surface shadow-qb-card"
      />
    );
  }

  return (
    <ul className="flex flex-col gap-5">
      {data.data.map((ad) => (
        <li key={ad.id}>
          <MyAdsRow ad={ad} category={labels.category(ad.category_slug)} />
        </li>
      ))}
    </ul>
  );
}
