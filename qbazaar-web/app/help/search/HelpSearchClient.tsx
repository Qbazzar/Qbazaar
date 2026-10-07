'use client';

/**
 * Help center search results, driven by `?q=` (nuqs) so the page stays
 * shareable. No Figma frame: the search result / "Search Not Found" states of
 * category.html (492:20208, 655:55973) with article rows.
 */
import { parseAsString, useQueryState } from 'nuqs';
import { Search } from 'lucide-react';

import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { BrowseTopicsLink } from '@/components/help/BrowseTopicsLink';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { HelpContactCard } from '@/components/help/HelpContactCard';
import { HelpSearchBar } from '@/components/help/HelpSearchBar';
import { HelpRowsSkeleton } from '@/components/help/HelpSkeletons';
import { RetryPanel } from '@/components/status/RetryPanel';
import { useRetry } from '@/hooks/useRetry';
import type { HelpArticleListItem } from '@/lib/api/types';
import { MIN_HELP_QUERY_LENGTH, useHelpSearchQuery } from '@/lib/queries/help';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

export function HelpSearchClient() {
  const [q] = useQueryState('q', parseAsString.withDefault(''));
  const query = q.trim();
  const enabled = query.length >= MIN_HELP_QUERY_LENGTH;
  const { data: results, isPending, isPlaceholderData, isError, refetch } = useHelpSearchQuery(query);
  const { retrying, retry } = useRetry(refetch);
  const resultCount = enabled && results ? tPlural('help.result_count', results.length) : '';
  // The previous query's results stay on screen while the next loads; only the settled count is announced.
  const announcement = enabled && isError ? t('errors.generic_heading') : isPlaceholderData ? '' : resultCount;

  return (
    <PageShell
      breadcrumb={[
        { label: t('home.breadcrumb'), href: '/' },
        { label: t('help.title'), href: '/help' },
        { label: t('help.search_title') },
      ]}
      title={t('help.search_title')}
      meta={enabled ? resultCount || '\u00a0' : undefined}
    >
      <p role="status" className="sr-only">
        {announcement}
      </p>
      <HelpSearchBar initialQuery={q} hideSuggestions className="mb-6 qb-desktop:mb-10 qb-desktop:max-w-[1125px]" />
      <SearchResults
        enabled={enabled}
        loading={isPending}
        failed={isError || retrying}
        results={results}
        onRetry={retry}
        retrying={retrying}
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

  if (failed) return <RetryPanel onRetry={onRetry} retrying={retrying} />;

  if (loading || !results) return <HelpRowsSkeleton />;

  if (results.length === 0) {
    return (
      <StatePanel
        icon={<StateIcon icon={Search} tone="muted" />}
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
