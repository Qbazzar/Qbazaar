'use client';

/**
 * `/account/ads` — owner's ads list (518:20536), organised by status tab.
 *
 * Each tab issues a separate `useMyAdsQuery` so the cache can stay scoped
 * per-status. The status tabs have no frame on this page, so they use the
 * pill tabs of the notifications screen.
 *
 * Auth is enforced by the parent `app/account/layout.tsx`.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Megaphone, Plus } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { Tab, TabList, TabPanel, Tabs } from '@/components/design-system/Tabs';
import { AccountPage, pillTabClass } from '@/components/account/AccountPage';
import { MyAdsRow } from '@/components/account/MyAdsRow';
import { PanelState } from '@/components/account/PanelState';
import { ProfileSummary } from '@/components/account/ProfileSummary';
import { useSlugLabels } from '@/components/account/useSlugLabels';
import { useAuth } from '@/hooks/useAuth';
import { useMyAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import type { AdStatus } from '@/lib/api/types';

type TabKey = 'all' | AdStatus;

const TABS: TabKey[] = ['all', 'active', 'draft', 'sold', 'expired'];

export default function MyAdsPage() {
  const { user } = useAuth();
  const [tab, setTab] = useState<TabKey>('all');
  // Same query as the "All" tab, so the header count costs no extra request.
  const { data: allAds } = useMyAdsQuery({});

  return (
    <AccountPage
      title={t('account.nav.my_ads')}
      actions={
        <Link href="/post-ad" className={buttonVariants({ size: 'sm' })}>
          <Plus aria-hidden="true" />
          {t('ads.actions.post_ad', 'نشر إعلان جديد')}
        </Link>
      }
    >
      {user ? (
        <ProfileSummary
          name={user.full_name}
          avatarUrl={user.avatar_url}
          accountType={user.account_type}
          joinedAt={user.created_at}
          subtitle={allAds ? tPlural('account.my_ads.total', allAds.meta.total) : undefined}
        />
      ) : null}

      <h2 className="mt-8 text-qb-h3 leading-none font-semibold tracking-normal text-qb-ink qb-desktop:text-qb-h2">
        {t('account.nav.my_ads')}
      </h2>

      <Tabs value={tab} onValueChange={(value) => setTab(value as TabKey)} className="mt-6">
        <TabList aria-label={t('account.my_ads.tabs_label')} scrollOnPhones>
          {TABS.map((key) => (
            <Tab key={key} value={key} className={pillTabClass}>
              {t(`ads.my.tabs.${key}`, key)}
            </Tab>
          ))}
        </TabList>
        {TABS.map((key) => (
          <TabPanel key={key} value={key}>
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
    <ul className="flex flex-col gap-4 qb-tablet:gap-6 qb-desktop:gap-7">
      {data.data.map((ad) => (
        <li key={ad.id}>
          <MyAdsRow ad={ad} category={labels.category(ad.category_slug)} />
        </li>
      ))}
    </ul>
  );
}
