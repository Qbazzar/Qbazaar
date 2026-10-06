import type { OpeningHours, Weekday } from '@/lib/api/types';

/** The Qatari week, Saturday first, as the API orders the days. */
export const WEEK: readonly Weekday[] = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

// 2024-01-06 was a Saturday; the following days walk through the week.
const REFERENCE_DATES: Record<Weekday, Date> = Object.fromEntries(
  WEEK.map((day, index) => [day, new Date(Date.UTC(2024, 0, 6 + index, 12))]),
) as Record<Weekday, Date>;

export interface OpeningHoursLine {
  /** "Sun – Thu" or "Fri". */
  days: string;
  /** "09:00 – 18:00", or null when closed. */
  hours: string | null;
}

function sameHours(a: OpeningHours, b: OpeningHours): boolean {
  return a.closed === b.closed && a.open === b.open && a.close === b.close;
}

/**
 * Groups consecutive days with the same hours into lines such as
 * "Sun – Thu: 09:00 – 18:00", with weekday names in `intlLocale`.
 */
export function groupOpeningHours(hours: OpeningHours[], intlLocale: string): OpeningHoursLine[] {
  const dayName = new Intl.DateTimeFormat(intlLocale, { weekday: 'short', timeZone: 'UTC' });
  const byDay = new Map(hours.map((entry) => [entry.day, entry]));
  const ordered = WEEK.map((day) => byDay.get(day)).filter((entry): entry is OpeningHours => Boolean(entry));

  const groups: OpeningHours[][] = [];
  for (const entry of ordered) {
    const current = groups[groups.length - 1];
    const previous = current?.[current.length - 1];
    const consecutive = previous && WEEK.indexOf(entry.day) === WEEK.indexOf(previous.day) + 1;
    if (current && consecutive && sameHours(previous, entry)) current.push(entry);
    else groups.push([entry]);
  }

  return groups.map((group) => {
    const first = group[0];
    const last = group[group.length - 1];
    const days =
      group.length === 1
        ? dayName.format(REFERENCE_DATES[first.day])
        : `${dayName.format(REFERENCE_DATES[first.day])} – ${dayName.format(REFERENCE_DATES[last.day])}`;
    const open = !first.closed && first.open && first.close;
    return { days, hours: open ? `${first.open} – ${first.close}` : null };
  });
}
