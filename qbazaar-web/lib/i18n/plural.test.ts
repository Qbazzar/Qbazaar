import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from './locale';
import { tPlural } from './plural';

afterEach(() => setClientLocale('ar'));

describe('tPlural', () => {
  it('picks the English singular and plural forms', () => {
    setClientLocale('en');

    expect(tPlural('catalog.ads_count', 1)).toBe('1 Ad');
    expect(tPlural('catalog.ads_count', 9)).toBe('9 Ads');
    expect(tPlural('catalog.ads_count', 42850)).toBe('42,850 Ads');
  });

  it('follows the Arabic number agreement', () => {
    setClientLocale('ar');

    expect(tPlural('catalog.ads_count', 1)).toBe('1 إعلان');
    expect(tPlural('catalog.ads_count', 3)).toBe('3 إعلانات');
    expect(tPlural('catalog.ads_count', 11)).toBe('11 إعلانًا');
    expect(tPlural('catalog.ads_count', 100)).toBe('100 إعلان');
    expect(tPlural('catalog.results_count', 2)).toBe('نتيجتان');
  });

  it('uses the plural form for zero in English', () => {
    setClientLocale('en');

    expect(tPlural('catalog.stats.ads', 0)).toBe('Ads');
  });
});
