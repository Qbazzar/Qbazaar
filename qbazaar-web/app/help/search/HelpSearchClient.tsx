'use client';

/**
 * Help center search results, driven by `?q=` (nuqs) so the page stays
 * shareable. No Figma frame: the search result / "Search Not Found" states of
 * category.html (492:20208, 655:55973) with article rows.
 */
import { parseAsString, useQueryState } from 'nuqs';
import { CircleAlert, Search, SearchX } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { BrowseTopicsLink } from '@/components/help/BrowseTopicsLink';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { HelpContactCard } from '@/components/help/HelpContactCard';
import { MIN_HELP_QUERY_LENGTH, HelpSearchBar } from '@/components/help/HelpSearchBar';
import { HelpRowsSkeleton } from '@/components/help/HelpSkeletons';
import type { HelpArticleListItem } from '@/lib/api/types';
import { useHelpSearchQuery } from '@/lib/queries/help';
import { tPlural } from '@/lib/i18n/intl';
import { t } from '@/lib/i18n/messages';

export function HelpSearchClient() {
  const [q] = useQueryState('q', parseAsString.withDefault(''));
  const query = q.trim();
  const enabled = query.length >= MIN_HELP_QUERY_LENGTH;
  const { data: results, isPending, isError, refetch, isFetching } = useHelpSearchQuery(query);

  return (
    <PageShell
      breadcrumb={[
        { label: t('home.breadcrumb'), href: '/' },
        { label: t('help.title'), href: '/help' },
        { label: t('help.search_title') },
      ]}
      title={t('help.search_title')}
      meta={enabled ? (results ? tPlural('help.result_count', results.length) : '\u00a0') : undefined}
    >
      <HelpSearchBar initialQuery={q} hideSuggestions className="mb-6 qb-desktop:mb-10 qb-desktop:max-w-[1125px]" />
      <SearchResults
        enabled={enabled}
        loading={isPending}
        failed={isError}
        results={results}
        onRetry={() => refetch()}
        retrying={isFetching}
      />
      <HelpContactCard className="mt-10 qb-desktop:mt-12" />
    </PageShell>
  );
}

interface SearchResultsProps {
  enabled: boolean;
  loading: boolean;
  failed: boolean;
  results: HelpArticleListItem[] | undefined;
  onRetry: () => void;
  retrying: boolean;
}

function SearchResults({ enabled, loading, failed, results, onRetry, retrying }: SearchResultsProps) {
  if (!enabled) {
    return (
      <StatePanel
        icon={<StateIcon icon={Search} tone="muted" />}
        title={t('help.search_prompt_title')}
        description={t('help.search_min_chars')}
        action={<BrowseTopicsLink />}
      />
    );
  }

  if (failed) {
    return (
      <StatePanel
        icon={<StateIcon icon={CircleAlert} tone="muted" />}
        title={t('common.error')}
        action={
          <Button size="sm" onClick={onRetry} disabled={retrying}>
            {t('common.retry')}
          </Button>
        }
      />
    );
  }

  if (loading || !results) return <HelpRowsSkeleton />;

  if (results.length === 0) {
    return (
      <StatePanel
        icon={<StateIcon icon={SearchX} tone="muted" />}
        title={t('help.no_results')}
        description={t('help.no_results_hint')}
        action={<BrowseTopicsLink />}
      />
    );
  }

  return (
    <ul aria-label={t('help.search_title')} className="flex flex-col gap-4">
      {results.map((article) => (
        <li key={article.id}>
          <HelpArticleCard article={article} />
        </li>
      ))}
    </ul>
  );
}
