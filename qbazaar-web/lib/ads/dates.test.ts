import { describe, expect, it } from 'vitest';

import { formatDayMonthYear, formatDottedDate } from './dates';

describe('formatDottedDate', () => {
  it('writes dd.mm.yyyy in Qatar time', () => {
    expect(formatDottedDate('2016-01-08T10:00:00Z')).toBe('08.01.2016');
    // 22:30 UTC is already the next day in Doha.
    expect(formatDottedDate('2026-04-11T22:30:00Z')).toBe('12.04.2026');
  });

  it('is empty without a valid date', () => {
    expect(formatDottedDate(null)).toBe('');
    expect(formatDottedDate('not a date')).toBe('');
  });
});

describe('formatDayMonthYear', () => {
  it('writes "08 Jan 2016" in English and keeps Latin digits in Arabic', () => {
    expect(formatDayMonthYear('2016-01-08T10:00:00Z', 'en')).toBe('08 Jan 2016');
    expect(formatDayMonthYear('2016-01-08T10:00:00Z', 'ar')).toMatch(/^08 .+ 2016$/);
  });
});
