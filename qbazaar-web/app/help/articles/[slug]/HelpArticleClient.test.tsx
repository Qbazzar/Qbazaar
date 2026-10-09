import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/queries/help', () => ({ useHelpArticleQuery: vi.fn(), useHelpCategoryQuery: vi.fn() }));

import { ApiClientError } from '@/lib/api/auth';
import type { HelpArticle, HelpArticleListItem } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useHelpArticleQuery, useHelpCategoryQuery } from '@/lib/queries/help';

import { HelpArticleClient } from './HelpArticleClient';

const listItem = (slug: string, en: string): HelpArticleListItem => ({
  id: slug,
  slug,
  title: { ar: en, en },
  excerpt: null,
  display_order: 1,
});

const article: HelpArticle = {
  ...listItem('making-an-offer', 'Making an offer'),
  body: { ar: '<p>عرض</p>', en: '<p>Tap the handshake icon to submit a formal offer.</p>' },
  views_count: 12,
  category: { id: 'buying', slug: 'buying', name: { ar: 'الشراء', en: 'Buying' }, icon: null },
};

const refetch = vi.fn();

function articleQuery(state: Partial<{ data: HelpArticle; isError: boolean; error: ApiClientError }>) {
  vi.mocked(useHelpArticleQuery).mockReturnValue({
    data: undefined,
    isError: false,
    error: null,
    isFetching: false,
    refetch,
    ...state,
  } as unknown as ReturnType<typeof useHelpArticleQuery>);
}

const apiError = (code: string, status: number) =>
  new ApiClientError({ status, code, messageKey: 'errors.x', message: code });

beforeEach(() => {
  setClientLocale('en');
  vi.mocked(useHelpCategoryQuery).mockReturnValue({
    data: { articles: [listItem('making-an-offer', 'Making an offer'), listItem('contacting-sellers', 'Contacting sellers')] },
  } as unknown as ReturnType<typeof useHelpCategoryQuery>);
});

afterEach(() => vi.clearAllMocks());

describe('HelpArticleClient', () => {
  it('shows the article with its trail, views and the other articles of the topic', () => {
    articleQuery({ data: article });
    render(<HelpArticleClient slug="making-an-offer" />);

    expect(screen.getByRole('heading', { level: 1, name: 'Making an offer' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Buying' })).toHaveAttribute('href', '/help/c/buying');
    expect(screen.getByText('12 views')).toBeInTheDocument();
    expect(screen.getByText('Tap the handshake icon to submit a formal offer.')).toBeInTheDocument();
    const related = screen.getByRole('region', { name: 'Related articles' });
    expect(related.querySelectorAll('a')).toHaveLength(1);
    expect(screen.getByRole('link', { name: 'Contacting sellers' })).toBeInTheDocument();
    expect(document.title).toBe('Making an offer · Help center | QBazaar');
  });

  it('holds the layout while the article loads', () => {
    articleQuery({});
    render(<HelpArticleClient slug="making-an-offer" />);

    expect(screen.getByRole('status')).toHaveTextContent('Loading…');
    expect(screen.getByRole('heading', { level: 1, name: 'Loading…' })).toBeInTheDocument();
  });

  it('says the article is missing when the API answers HELP_001', () => {
    articleQuery({ isError: true, error: apiError('HELP_001', 404) });
    render(<HelpArticleClient slug="gone" />);

    expect(screen.getByRole('heading', { level: 2, name: "We couldn't find this article" })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Browse help topics' })).toHaveAttribute('href', '/help');
    expect(document.title).toBe('Page not found | QBazaar');
    expect(document.head.querySelector('meta[name="robots"]')).toHaveAttribute('content', 'noindex');
  });

  it('offers a retry for any other failure', async () => {
    articleQuery({ isError: true, error: apiError('SERVER_ERROR', 500) });
    render(<HelpArticleClient slug="making-an-offer" />);
    await act(async () => fireEvent.click(screen.getByRole('button', { name: 'Try again' })));

    expect(refetch).toHaveBeenCalled();
  });

  it('keeps the retry button, and the focus on it, while the retry runs', async () => {
    let settle = () => {};
    refetch.mockReturnValue(new Promise<void>((resolve) => (settle = resolve)));
    articleQuery({ isError: true, error: apiError('SERVER_ERROR', 500) });
    const { rerender } = render(<HelpArticleClient slug="making-an-offer" />);
    const button = screen.getByRole('button', { name: 'Try again' });
    button.focus();
    fireEvent.click(button);

    // A refetching query without data reports pending, not failed.
    articleQuery({});
    rerender(<HelpArticleClient slug="making-an-offer" />);
    expect(screen.getByRole('button', { name: 'Try again' })).toBe(button);
    expect(button).toHaveAttribute('aria-disabled', 'true');
    expect(button).toHaveFocus();

    articleQuery({ isError: true, error: apiError('SERVER_ERROR', 500) });
    await act(async () => settle());
    expect(button).not.toHaveAttribute('aria-disabled');
    expect(button).toHaveFocus();
  });
});
