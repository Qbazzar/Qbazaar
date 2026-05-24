'use client';

/**
 * `/account/ads` — owner's ads list, organised by status tab.
 *
 * Each tab issues a separate `useMyAdsQuery` so the cache can stay scoped
 * per-status. Empty states differ per tab (drafts → "create your first ad",
 * sold → "nothing here yet", etc).
 *
 * Auth is enforced by the parent `app/account/layout.tsx`.
 */
import { useState } from 'react';
import Link from 'next/link';
import { Plus } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { MyAdsRow } from '@/components/account/MyAdsRow';
import { useMyAdsQuery } from '@/lib/queries/ads';
import { t } from '@/lib/i18n/messages';
import type { AdStatus } from '@/lib/api/types';

type TabKey = 'all' | AdStatus;

const TABS: TabKey[] = ['all', 'active', 'draft', 'sold', 'expired'];

export default function MyAdsPage() {
  const [tab, setTab] = useState<TabKey>('all');

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-3xl text-ink-900 md:text-4xl">
            {t('ads.my.title', 'إعلاناتي')}
          </h1>
          <p className="text-ink-500 mt-1 text-sm">
            {t('ads.my.subtitle', 'أدِر إعلاناتك من مكان واحد.')}
          </p>
        </div>
        <Button asChild className="bg-coral hover:bg-coral/90 rounded-full text-white">
          <Link href="/post-ad">
            <Plus className="size-4" />
            {t('ads.actions.post_ad', 'نشر إعلان جديد')}
          </Link>
        </Button>
      </header>

      <Tabs value={tab} onValueChange={(v) => setTab(v as TabKey)}>
        <TabsList className="bg-cream-200">
          {TABS.map((key) => (
            <TabsTrigger key={key} value={key}>
              {t(`ads.my.tabs.${key}`, key)}
            </TabsTrigger>
          ))}
        </TabsList>
        {TABS.map((key) => (
          <TabsContent key={key} value={key}>
            <MyAdsTab status={key === 'all' ? undefined : key} />
          </TabsContent>
        ))}
      </Tabs>
    </div>
  );
}

function MyAdsTab({ status }: { status?: AdStatus }) {
  const { data, isLoading, isError } = useMyAdsQuery({ status });

  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="bg-cream-200 h-64 animate-pulse rounded-xl" />
        ))}
      </div>
    );
  }

  if (isError || !data) {
    return (
      <p className="text-ink-500 py-12 text-center text-sm">
        {t('common.error', 'حدث خطأ، حاول مرة أخرى')}
      </p>
    );
  }

  if (data.data.length === 0) {
    return (
      <div className="rounded-xl border border-dashed border-ink-200 bg-cream-50 px-6 py-12 text-center">
        <p className="text-ink-500 text-sm">
          {t('ads.empty.no_my_ads', 'لا توجد إعلانات في هذا التبويب بعد.')}
        </p>
        <Button asChild variant="outline" className="mt-4">
          <Link href="/post-ad">{t('ads.actions.post_ad', 'انشر إعلانك الأول')}</Link>
        </Button>
      </div>
    );
  }

  return (
    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {data.data.map((ad) => (
        <li key={ad.id}>
          <MyAdsRow ad={ad} />
        </li>
      ))}
    </ul>
  );
}
