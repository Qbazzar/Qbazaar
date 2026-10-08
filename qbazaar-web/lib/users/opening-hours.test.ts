import { describe, expect, it } from 'vitest';

import type { OpeningHours, Weekday } from '@/lib/api/types';

import { groupOpeningHours } from './opening-hours';

const open = (day: Weekday, from = '09:00', to = '18:00'): OpeningHours => ({ day, closed: false, open: from, close: to });
const closed = (day: Weekday): OpeningHours => ({ day, closed: true, open: null, close: null });

describe('groupOpeningHours', () => {
  it('merges consecutive days with the same hours', () => {
    const hours = ['sun', 'mon', 'tue', 'wed', 'thu'].map((day) => open(day as Weekday));

    expect(groupOpeningHours([...hours, closed('fri')], 'en-US')).toEqual([
      { days: 'Sun – Thu', hours: '09:00 – 18:00' },
      { days: 'Fri', hours: null },
    ]);
  });

  it('follows the week from Saturday whatever order the API sends', () => {
    const lines = groupOpeningHours([open('mon'), open('sat', '10:00', '14:00'), open('sun')], 'en-US');

    expect(lines.map((line) => line.days)).toEqual(['Sat', 'Sun – Mon']);
  });

  it('does not merge days that are not next to each other', () => {
    expect(groupOpeningHours([open('sun'), open('tue')], 'en-US').map((line) => line.days)).toEqual(['Sun', 'Tue']);
  });

  it('names the days in the page language', () => {
    expect(groupOpeningHours([open('fri')], 'ar-EG')[0]?.days).toBe('الجمعة');
  });

  it('returns nothing for a company without hours', () => {
    expect(groupOpeningHours([], 'en-US')).toEqual([]);
  });
});
