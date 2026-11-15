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

    expect(trigger).toHaveTextContent(code);
    expect(trigger.getAttribute('aria-label')).toContain(code);
    expect(trigger.getAttribute('aria-label')).toContain(name);
  });
});
