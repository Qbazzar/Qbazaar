import { describe, expect, it } from 'vitest';

import { formatNumber, intlLocale } from './format';

describe('number formatting', () => {
  it('maps the page language to an Intl locale', () => {
    expect(intlLocale('ar')).toBe('ar-QA-u-nu-latn');
    expect(intlLocale('en')).toBe('en-US');
  });

  it('writes Latin digits in both languages', () => {
    expect(formatNumber(1250, 'en')).toBe('1,250');
    expect(formatNumber(1250, 'ar')).toBe('1,250');
  });
});
