import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('@/lib/queries/help', () => ({ MIN_HELP_QUERY_LENGTH: 2, useHelpSearchQuery: vi.fn() }));

import { setClientLocale } from '@/lib/i18n/locale';
import { useHelpSearchQuery } from '@/lib/queries/help';
import type { HelpArticleListItem } from '@/lib/api/types';

import { HelpSearchBar } from './HelpSearchBar';

const article = (slug: string, en: string): HelpArticleListItem => ({
  id: slug,
  slug,
  title: { ar: en, en },
  excerpt: null,
  display_order: 1,
});

const mockSearch = vi.mocked(useHelpSearchQuery);

function searchReturns(data: HelpArticleListItem[] | undefined) {
  mockSearch.mockImplementation(
    (q: string) => ({ data: q ? data : undefined, isFetching: false }) as unknown as ReturnType<typeof useHelpSearchQuery>,
  );
}

beforeEach(() => {
  setClientLocale('en');
  vi.useFakeTimers();
  searchReturns([article('making-an-offer', 'Making an offer'), article('contacting-sellers', 'Contacting sellers')]);
});

afterEach(() => {
  vi.useRealTimers();
  vi.clearAllMocks();
});

function type(value: string) {
  const input = screen.getByRole('searchbox', { name: 'Search the help center' });
  fireEvent.focus(input);
  fireEvent.change(input, { target: { value } });
  act(() => vi.advanceTimersByTime(300));
  return input;
}

describe('HelpSearchBar', () => {
  it('opens the shareable results page with the trimmed query', () => {
    render(<HelpSearchBar />);
    const input = type('  refund & fees ');
    fireEvent.submit(input.closest('form')!);

    expect(push).toHaveBeenCalledWith('/help/search?q=refund%20%26%20fees');
  });

  it('does not search for a single character', () => {
    render(<HelpSearchBar />);
    fireEvent.submit(type('a').closest('form')!);

    expect(push).not.toHaveBeenCalled();
    expect(screen.queryByRole('list')).toBeNull();
  });

  it('suggests matching articles and announces how many', () => {
    render(<HelpSearchBar />);
    type('offer');

    const list = screen.getByRole('list', { name: 'Suggested articles' });
    expect(list.querySelectorAll('a')).toHaveLength(2);
    expect(screen.getByRole('link', { name: 'Making an offer' })).toHaveAttribute('href', '/help/articles/making-an-offer');
    expect(screen.getByRole('status')).toHaveTextContent('2 results');
  });

  it('closes the suggestions with Escape and keeps the query', () => {
    render(<HelpSearchBar />);
    const input = type('offer');
    const escape = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
    act(() => {
      input.dispatchEvent(escape);
    });

    expect(screen.queryByRole('list')).toBeNull();
    expect(escape.defaultPrevented).toBe(true);
    expect(input).toHaveValue('offer');
  });

  it('returns focus to the field when Escape closes the list from a suggestion', () => {
    render(<HelpSearchBar />);
    const input = type('offer');
    const suggestion = screen.getByRole('link', { name: 'Making an offer' });
    suggestion.focus();
    fireEvent.keyDown(suggestion, { key: 'Escape' });

    expect(screen.queryByRole('list')).toBeNull();
    expect(input).toHaveFocus();
  });

  it('says so when nothing matches', () => {
    searchReturns([]);
    render(<HelpSearchBar />);
    type('zzz');

    expect(screen.getByText('No results')).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('0 results');
  });

  it('keeps suggestions off on the results page and follows the URL query', () => {
    const { rerender } = render(<HelpSearchBar initialQuery="offer" hideSuggestions />);
    type('offers');

    expect(screen.queryByRole('list')).toBeNull();

    rerender(<HelpSearchBar initialQuery="fees" hideSuggestions />);
    expect(screen.getByRole('searchbox')).toHaveValue('fees');
  });
});
