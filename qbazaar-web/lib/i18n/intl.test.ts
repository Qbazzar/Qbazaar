import { afterEach, describe, expect, it } from 'vitest';

import { formatNumber } from './format';
import { tPlural } from './intl';
import { setClientLocale } from './locale';

afterEach(() => setClientLocale('ar'));

describe('tPlural', () => {
  it('picks the English singular and plural forms', () => {
    setClientLocale('en');

    expect(tPlural('help.article_count', 1)).toBe('1 article');
    expect(tPlural('help.article_count', 12)).toBe('12 articles');
    expect(tPlural('help.article_count', 1250)).toBe(`${formatNumber(1250, 'en')} articles`);
  });

  it('uses every Arabic plural category with the shared number format', () => {
    setClientLocale('ar');

    expect(tPlural('help.article_count', 0)).toBe('لا توجد مقالات');
    expect(tPlural('help.article_count', 1)).toBe('مقال واحد');
    expect(tPlural('help.article_count', 2)).toBe('مقالان');
    expect(tPlural('help.article_count', 3)).toBe(`${formatNumber(3, 'ar')} مقالات`);
    expect(tPlural('help.article_count', 11)).toBe(`${formatNumber(11, 'ar')} مقالًا`);
    expect(tPlural('help.article_count', 100)).toBe(`${formatNumber(100, 'ar')} مقال`);
  });

  it('falls back to the "other" form when a category is missing', () => {
    setClientLocale('en');

    expect(tPlural('help.article_count', 0)).toBe('0 articles');
  });
});
