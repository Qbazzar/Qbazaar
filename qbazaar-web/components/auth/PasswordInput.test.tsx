import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { t } from '@/lib/i18n/messages';

import { PasswordInput } from './PasswordInput';

describe('PasswordInput', () => {
  it('hides the password until the eye button is pressed', () => {
    render(<PasswordInput aria-label="Password" id="pw" defaultValue="Secret1!" />);
    const input = screen.getByLabelText('Password');
    const toggle = screen.getByRole('button', { name: t('auth.password.toggle') });

    expect(input).toHaveAttribute('type', 'password');
    expect(toggle).toHaveAttribute('aria-pressed', 'false');
    expect(toggle).toHaveAttribute('aria-controls', 'pw');

    fireEvent.click(toggle);
    expect(input).toHaveAttribute('type', 'text');
    expect(toggle).toHaveAttribute('aria-pressed', 'true');
  });

  it('keeps the eye clear of the left-to-right text on the end side of either direction', () => {
    render(<PasswordInput aria-label="Password" />);
    const input = screen.getByLabelText('Password');

    expect(input).toHaveAttribute('dir', 'ltr');
    expect(input).toHaveClass('pr-12', 'rtl:pl-12');
  });
});
