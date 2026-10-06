'use client';

/**
 * FE-6.x — Saved searches index (381:8815, empty 381:8657).
 *
 * Auth-gated by the wrapping `app/account/layout.tsx`. Lists every saved
 * search the user owns as a card with two actions: View Result (route
 * restoration) and Delete (confirm dialog handled inside the card component).
 */
import Link from 'next/link';
import { Search } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { EmptyState } from '@/components/design-system/EmptyState';
import { Icon } from '@/components/design-system/Icon';
import { AccountPage } from '@/components/account/AccountPage';
import { PanelState } from '@/components/account/PanelState';
import { SavedSearchCard } from '@/components/account/SavedSearchCard';
import { useSavedSearchesQuery } from '@/lib/queries/search';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { ApiClientError } from '@/lib/api/auth';

export default function SavedSearchesPage() {
  const { data, isLoading, isError, error } = useSavedSearchesQuery();

  return (
    <AccountPage title={t('account.saved_searches.title', 'عمليات البحث المحفوظة')}>
      {isLoading ? (
        <PanelState loading />
      ) : isError ? (
        <PanelState
          loading={false}
          message={
            error instanceof ApiClientError
              ? translateMaybeKey(`search.errors.${error.code.toLowerCase()}`) ||
                translateMaybeKey('search.errors.load_failed') ||
                error.message
              : t('search.errors.load_failed', 'تعذّر تحميل البيانات')
          }
        />
      ) : !data || data.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Search} size="lg" />}
          title={t('account.saved_searches.empty_title', 'لا توجد عمليات بحث محفوظة بعد')}
          description={t('account.saved_searches.empty_body')}
          action={
            <Link href="/search" className={buttonVariants({ size: 'sm' })}>
              {t('home.hero.cta_browse', 'تصفّح الإعلانات')}
            </Link>
          }
          className="rounded-qb-2xl border border-qb-line bg-qb-surface py-20 shadow-qb-card"
        />
      ) : (
        <ul className="flex flex-col gap-4 qb-tablet:gap-6">
          {data.map((search) => (
            <li key={search.id}>
              <SavedSearchCard search={search} />
            </li>
          ))}
        </ul>
      )}
    </AccountPage>
  );
}
