import { describe, expect, it } from 'vitest';
import { safeReturnTo } from './safe-return-to';

describe('safeReturnTo', () => {
  it.each([
    ['/post-ad', '/post-ad'],
    ['/ads/42?tab=photos#top', '/ads/42?tab=photos#top'],
    ['/account/messages?c=abc', '/account/messages?c=abc'],
    ['/search?q=%2F%2Fevil', '/search?q=%2F%2Fevil'],
  ])('keeps the same-origin path %s', (raw, expected) => {
    expect(safeReturnTo(raw)).toBe(expected);
  });

  it.each([
    null,
    undefined,
    '',
    'post-ad',
    'https://evil.example',
    '//evil.example',
    '/\\evil.example',
    '/foo\\bar',
    'javascript:alert(1)',
    '/foo bar',
    '/foo\nbar',
  ])('falls back for unsafe value %s', (raw) => {
    expect(safeReturnTo(raw, '/fallback')).toBe('/fallback');
  });

  it('defaults the fallback to the home page', () => {
    expect(safeReturnTo('//evil.example')).toBe('/');
  });
});
