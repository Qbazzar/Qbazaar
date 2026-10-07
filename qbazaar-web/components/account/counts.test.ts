import { afterEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import { tPlural } from '@/lib/i18n/plural';

afterEach(() => setClientLocale('ar'));

describe('account counts', () => {
  it('agree with the number in English', () => {
    setClientLocale('en');

    expect(tPlural('account.my_ads.total', 1)).toBe('You have 1 ad');
    expect(tPlural('account.my_ads.total', 4)).toBe('You have 4 ads');
    expect(tPlural('account.my_ads.visitors', 1525)).toBe('1,525 visitors');
    expect(tPlural('account.my_ads.likes', 1)).toBe('1 like');
    expect(tPlural('support.replies_count', 0)).toBe('0 replies');
  });

  it('follow the Arabic number agreement', () => {
    setClientLocale('ar');

    expect(tPlural('account.my_ads.total', 2)).toBe('لديك 2 إعلان');
    expect(tPlural('account.my_ads.visitors', 3)).toBe('3 زوار');
    expect(tPlural('account.my_ads.likes', 11)).toBe('11 إعجابًا');
    expect(tPlural('support.replies_count', 100)).toBe('100 رد');
  });
});
