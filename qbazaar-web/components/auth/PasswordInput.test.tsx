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

  it('draws the same slashed eye in both states, as auth.js only switches the type', () => {
    render(<PasswordInput aria-label="Password" />);
    const toggle = screen.getByRole('button', { name: t('auth.password.toggle') });
    const hidden = toggle.innerHTML;

    fireEvent.click(toggle);
    expect(toggle.innerHTML).toBe(hidden);
  });

  it('types left to right with the 16 px padding of .qb-field input and the eye on the end side', () => {
    render(<PasswordInput aria-label="Password" />);
    const input = screen.getByLabelText('Password');
    const toggle = screen.getByRole('button', { name: t('auth.password.toggle') });

    expect(input).toHaveAttribute('dir', 'ltr');
    expect(input).toHaveClass('px-4');
    expect(input).not.toHaveClass('pr-12');
    expect(toggle).toHaveClass('end-3.5');
  });

  it('keeps the standard placeholder colour unless the auth pages ask for theirs', () => {
    render(
      <>
        <PasswordInput aria-label="Plain" />
        <PasswordInput aria-label="Auth" auth />
      </>,
    );

    expect(screen.getByLabelText('Plain')).not.toHaveClass('placeholder:text-qb-placeholder');
    expect(screen.getByLabelText('Plain')).toHaveClass('placeholder:text-qb-ink-muted');
    expect(screen.getByLabelText('Auth')).toHaveClass('placeholder:text-qb-placeholder');
  });
});
