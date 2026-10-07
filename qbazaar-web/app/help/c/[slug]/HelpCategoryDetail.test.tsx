import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import type { HelpCategoryWithArticles } from '@/lib/api/help';
import { setClientLocale } from '@/lib/i18n/locale';

import { HelpCategoryDetail } from './HelpCategoryDetail';

const topic = (articles: HelpCategoryWithArticles['articles']): HelpCategoryWithArticles => ({
  id: 'buying',
  slug: 'buying',
  name: { ar: 'الشراء', en: 'Buying' },
  description: { ar: 'كل ما يخص الشراء', en: 'Everything about buying' },
  icon: 'ShoppingBag',
  display_order: 1,
  articles,
});

describe('HelpCategoryDetail', () => {
  beforeEach(() => setClientLocale('en'));

  it('lists the topic articles under its title and article count', () => {
    render(
      <HelpCategoryDetail
        category={topic([
          { id: 'a', slug: 'searching-for-items', title: { ar: 'البحث', en: 'Searching for items' }, excerpt: null, display_order: 1 },
          { id: 'b', slug: 'making-an-offer', title: { ar: 'عرض', en: 'Making an offer' }, excerpt: null, display_order: 2 },
        ])}
      />,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Buying' })).toBeInTheDocument();
    expect(screen.getByText('2 articles')).toBeInTheDocument();
    expect(screen.getByText('Everything about buying')).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Articles in this topic' }).children).toHaveLength(2);
    expect(screen.getByRole('link', { name: 'Making an offer' })).toHaveAttribute('href', '/help/articles/making-an-offer');
  });

  it('says so when the topic has no articles yet', () => {
    render(<HelpCategoryDetail category={topic([])} />);

    expect(screen.getByRole('heading', { level: 2, name: 'No articles in this topic yet' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Browse help topics' })).toHaveAttribute('href', '/help');
  });
});
