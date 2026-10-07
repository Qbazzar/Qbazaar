import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { formatNumber } from '@/lib/i18n/format';
import { setClientLocale } from '@/lib/i18n/locale';
import type { HelpCategory } from '@/lib/api/types';

import { HelpTopics, helpTopicsSummary } from './HelpTopics';

const topic = (slug: string, en: string, articles: number): HelpCategory => ({
  id: slug,
  slug,
  name: { ar: en, en },
  description: null,
  icon: 'ShoppingBag',
  display_order: 1,
  articles_count: articles,
});

beforeEach(() => setClientLocale('en'));

describe('HelpTopics', () => {
  it('lists every topic as a tile with its article count', () => {
    render(<HelpTopics categories={[topic('buying', 'Buying', 3), topic('selling', 'Selling', 1)]} />);

    expect(screen.getByRole('list', { name: 'Browse by topic' }).children).toHaveLength(2);
    expect(screen.getByRole('link', { name: 'Buying 3 articles' })).toHaveAttribute('href', '/help/c/buying');
    expect(screen.getByRole('link', { name: 'Selling 1 article' })).toBeInTheDocument();
  });

  it('lays the tiles out without the legacy grid class, whose fixed gap would win', () => {
    render(<HelpTopics categories={[topic('buying', 'Buying', 3)]} />);

    const grid = screen.getByRole('list', { name: 'Browse by topic' });
    expect(grid).toHaveClass('[display:grid]', 'gap-2', 'qb-desktop:gap-4');
    expect(grid).not.toHaveClass('grid');
  });

  it('says when there are no topics yet', () => {
    render(<HelpTopics categories={[]} />);

    expect(screen.getByRole('heading', { name: 'No help topics yet' })).toBeInTheDocument();
  });
});

describe('helpTopicsSummary', () => {
  const topicsWith = (...articleCounts: number[]) =>
    articleCounts.map((articles, index) => topic(`topic-${index}`, `Topic ${index}`, articles));

  it('counts the topics and their articles', () => {
    expect(helpTopicsSummary([topic('buying', 'Buying', 3), topic('selling', 'Selling', 2)])).toBe('2 topics · 5 articles');
  });

  it('uses the plural form for zero in English', () => {
    expect(helpTopicsSummary([])).toBe('0 topics · 0 articles');
  });

  it('agrees both counts with every Arabic plural form', () => {
    setClientLocale('ar');

    expect(helpTopicsSummary([])).toBe('لا توجد مواضيع · لا توجد مقالات');
    expect(helpTopicsSummary(topicsWith(1))).toBe('موضوع واحد · مقال واحد');
    expect(helpTopicsSummary(topicsWith(1, 1))).toBe('موضوعان · مقالان');
    expect(helpTopicsSummary(topicsWith(1, 1, 1))).toBe(`${formatNumber(3, 'ar')} مواضيع · ${formatNumber(3, 'ar')} مقالات`);
    expect(helpTopicsSummary(topicsWith(11))).toBe(`موضوع واحد · ${formatNumber(11, 'ar')} مقالًا`);
    expect(helpTopicsSummary(topicsWith(100))).toBe(`موضوع واحد · ${formatNumber(100, 'ar')} مقال`);
  });
});
