import { render, screen } from '@testing-library/react';
import { NuqsTestingAdapter } from 'nuqs/adapters/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock('@/lib/queries/help', () => ({ MIN_HELP_QUERY_LENGTH: 2, useHelpSearchQuery: vi.fn() }));

import type { HelpArticleListItem } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useHelpSearchQuery } from '@/lib/queries/help';

import { HelpSearchClient } from './HelpSearchClient';

const article = (slug: string, en: string): HelpArticleListItem => ({
  id: slug,
  slug,
  title: { ar: en, en },
  excerpt: null,
  display_order: 1,
});

type SearchState = Partial<{ data: HelpArticleListItem[]; isPending: boolean; isPlaceholderData: boolean; isError: boolean }>;

function searchReturns(state: SearchState) {
  vi.mocked(useHelpSearchQuery).mockReturnValue({
    data: undefined,
    isPending: false,
    isPlaceholderData: false,
    isError: false,
    isFetching: false,
    refetch: vi.fn(),
    ...state,
  } as unknown as ReturnType<typeof useHelpSearchQuery>);
}

function renderResults(q: string) {
  render(
    <NuqsTestingAdapter searchParams={{ q }}>
      <HelpSearchClient />
    </NuqsTestingAdapter>,
  );
}

/** Text of the live regions on the page; the search bar keeps its own empty one. */
const announcements = () => screen.getAllByRole('status').map((region) => region.textContent).filter(Boolean);

beforeEach(() => setClientLocale('en'));
afterEach(() => vi.clearAllMocks());

describe('HelpSearchClient', () => {
  it('lists the matches and announces how many once the search settles', () => {
    searchReturns({ data: [article('making-an-offer', 'Making an offer'), article('refunds', 'Refunds')] });
    renderResults('offer');

    expect(screen.getByRole('list', { name: 'Search results' }).children).toHaveLength(2);
    expect(announcements()).toEqual(['2 results']);
  });

  it('stays quiet while the previous results stand in for the next search', () => {
    searchReturns({ data: [article('making-an-offer', 'Making an offer')], isPlaceholderData: true });
    renderResults('offers');

    expect(announcements()).toEqual([]);
  });

  it('announces an empty search', () => {
    searchReturns({ data: [] });
    renderResults('zzzz');

    expect(screen.getByRole('heading', { name: 'No results' })).toBeInTheDocument();
    expect(announcements()).toEqual(['0 results']);
  });

  it('announces a failed search next to its retry', () => {
    searchReturns({ isError: true });
    renderResults('offer');

    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
    expect(announcements()).toEqual(["We couldn't load this page"]);
  });
});
