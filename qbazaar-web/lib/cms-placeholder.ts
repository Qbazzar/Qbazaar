import type { LocalizedString } from '@/lib/api/types';

/** The body the API seeds into a CMS page nobody has written yet. */
const PLACEHOLDER_BODIES = ['Content for this page is coming soon.', 'سيتم إضافة محتوى هذه الصفحة قريباً.'];

const stripTags = (html: string) =>
  html
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim();

/** True while every filled-in side of the body is still the seeded placeholder line. */
export function isPlaceholderBody(body: LocalizedString | null | undefined): boolean {
  const sides = [body?.ar, body?.en].filter((side): side is string => Boolean(side && stripTags(side)));
  return sides.every((side) => PLACEHOLDER_BODIES.includes(stripTags(side)));
}
