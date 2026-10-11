import { describe, expect, it } from 'vitest';

import { isPlaceholderBody } from './cms-placeholder';

describe('isPlaceholderBody', () => {
  it('is true for the seeded placeholder lines, with or without markup', () => {
    expect(
      isPlaceholderBody({
        ar: 'سيتم إضافة محتوى هذه الصفحة قريباً.',
        en: '<p>Content for this page is coming soon.</p>',
      }),
    ).toBe(true);
    expect(
      isPlaceholderBody({
        ar: '',
        en: 'Content for this page is coming soon.',
      }),
    ).toBe(true);
  });

  it('is false once either side has real content', () => {
    expect(
      isPlaceholderBody({
        ar: 'سيتم إضافة محتوى هذه الصفحة قريباً.',
        en: 'We are hiring.',
      }),
    ).toBe(false);
  });

  it('is true for an empty body', () => {
    expect(isPlaceholderBody(null)).toBe(true);
  });
});
