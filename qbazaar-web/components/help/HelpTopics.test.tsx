import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/queries/help', () => ({ useHelpCategoriesQuery: vi.fn() }));

import { setClientLocale } from '@/lib/i18n/locale';
import { useHelpCategoriesQuery } from '@/lib/queries/help';
import type { HelpCategory } from '@/lib/api/types';

import { HelpTopics, HelpTopicsSummary } from './HelpTopics';

const topic = (slug: string, en: string, articles: number): HelpCategory => ({
  id: slug,
  slug,
  name: { ar: en, en },
  description: null,
  icon: 'ShoppingBag',
  display_order: 1,
  articles_count: articles,
});

const mockTopics = vi.mocked(useHelpCategoriesQuery);
const refetch = vi.fn();

function topicsQuery(state: Partial<{ data: HelpCategory[]; isPending: boolean; isError: boolean }>) {
  mockTopics.mockReturnValue({
    data: undefined,
    isPending: false,
    isError: false,
    isFetching: false,
    refetch,
    ...state,
  } as unknown as ReturnType<typeof useHelpCategoriesQuery>);
}

beforeEach(() => setClientLocale('en'));
afterEach(() => vi.clearAllMocks());

describe('HelpTopics', () => {
  it('lists every topic as a tile with its article count', () => {
    topicsQuery({ data: [topic('buying', 'Buying', 3), topic('selling', 'Selling', 1)] });
    render(<HelpTopics />);

    expect(screen.getByRole('list', { name: 'Browse by topic' }).children).toHaveLength(2);
    expect(screen.getByRole('link', { name: 'Buying 3 articles' })).toHaveAttribute('href', '/help/c/buying');
    expect(screen.getByRole('link', { name: 'Selling 1 article' })).toBeInTheDocument();
  });

  it('holds the grid with placeholder tiles while loading', () => {
    topicsQuery({ isPending: true });
    render(<HelpTopics />);

    expect(screen.getByRole('status')).toHaveTextContent('Loading…');
    expect(screen.queryByRole('link')).toBeNull();
  });

  it('offers a retry when the topics fail to load', () => {
    topicsQuery({ isError: true });
    render(<HelpTopics />);
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    expect(refetch).toHaveBeenCalled();
  });

  it('says when there are no topics yet', () => {
    topicsQuery({ data: [] });
    render(<HelpTopics />);

    expect(screen.getByRole('heading', { name: 'No help topics yet' })).toBeInTheDocument();
  });
});

describe('HelpTopicsSummary', () => {
  it('counts the topics and their articles', () => {
    topicsQuery({ data: [topic('buying', 'Buying', 3), topic('selling', 'Selling', 2)] });
    render(<p data-testid="summary"><HelpTopicsSummary /></p>);

    expect(screen.getByTestId('summary')).toHaveTextContent('2 topics · 5 articles');
  });

  it('keeps the line blank, not collapsed, while loading', () => {
    topicsQuery({ isPending: true });
    render(<p data-testid="summary"><HelpTopicsSummary /></p>);

    expect(screen.getByTestId('summary').textContent).toBe('\u00a0');
  });
});
