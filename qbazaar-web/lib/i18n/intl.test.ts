import { afterEach, describe, expect, it } from 'vitest';

import { intlLocale, tPlural } from './intl';
import { setClientLocale } from './locale';

afterEach(() => setClientLocale('ar'));

describe('intlLocale', () => {
  it('maps the app locale to the tag the formatters use', () => {
    expect(intlLocale('ar')).toBe('ar-EG');
    expect(intlLocale('en')).toBe('en-US');
  });
});

describe('tPlural', () => {
  it('picks the English singular and plural forms', () => {
    setClientLocale('en');

    expect(tPlural('help.article_count', 1)).toBe('1 article');
    expect(tPlural('help.article_count', 12)).toBe('12 articles');
    expect(tPlural('help.article_count', 1250)).toBe('1,250 articles');
  });

  it('uses every Arabic plural category with Arabic-Indic digits', () => {
    setClientLocale('ar');

    expect(tPlural('help.article_count', 0)).toBe('لا توجد مقالات');
    expect(tPlural('help.article_count', 1)).toBe('مقال واحد');
    expect(tPlural('help.article_count', 2)).toBe('مقالان');
    expect(tPlural('help.article_count', 3)).toBe('٣ مقالات');
    expect(tPlural('help.article_count', 11)).toBe('١١ مقالًا');
    expect(tPlural('help.article_count', 100)).toBe('١٠٠ مقال');
  });

  it('falls back to the "other" form when a category is missing', () => {
    setClientLocale('en');

    expect(tPlural('help.article_count', 0)).toBe('0 articles');
  });
});
