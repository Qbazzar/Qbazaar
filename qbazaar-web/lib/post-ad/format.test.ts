import { describe, expect, it } from 'vitest';

import { formatAmount, formatDate } from './format';

describe('formatAmount', () => {
  it('groups thousands and keeps typed decimals exact', () => {
    expect(formatAmount('285000', 'en')).toBe('285,000');
    expect(formatAmount('1999.5', 'en')).toBe('1,999.50');
    expect(formatAmount('0.05', 'en')).toBe('0.05');
    expect(formatAmount('9999999.99', 'en')).toBe('9,999,999.99');
  });

  it('uses Arabic digits and separators in Arabic', () => {
    expect(formatAmount('2350', 'ar')).toBe('٢٬٣٥٠');
    expect(formatAmount('12.5', 'ar')).toBe('١٢٫٥٠');
  });
});

describe('formatDate', () => {
  it('writes the design date in English and the local short date in Arabic', () => {
    expect(formatDate('2016-01-08T10:00:00Z', 'en')).toBe('08.01.2016');
    expect(formatDate('2016-01-08T10:00:00Z', 'ar')).toMatch(/٢٠١٦/);
    expect(formatDate('not a date', 'en')).toBe('');
  });
});
