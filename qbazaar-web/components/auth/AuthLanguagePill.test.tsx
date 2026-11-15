import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

vi.mock('@/components/i18n/switch-locale', () => ({ switchLocale: vi.fn() }));

import { AuthLanguagePill } from './AuthLanguagePill';

describe('AuthLanguagePill', () => {
  afterEach(() => setClientLocale('ar'));

  it.each([
    ['en', 'EN', 'English'],
    ['ar', 'AR', 'العربية'],
  ] as const)('keeps the visible code %s in the trigger name (label in name)', (locale, code, name) => {
    setClientLocale(locale);
    render(<AuthLanguagePill />);
    const trigger = screen.getByRole('button', { expanded: false });

    const label = trigger.getAttribute('aria-label') ?? '';

    expect(trigger).toHaveTextContent(code);
    // axe drops bracketed text before matching, so the code must stand on its own, first.
    expect(label.startsWith(code)).toBe(true);
    expect(label).not.toContain(`(${code})`);
    expect(label).toContain(name);
  });
});
