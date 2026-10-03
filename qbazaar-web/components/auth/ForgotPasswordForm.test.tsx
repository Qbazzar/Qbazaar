import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/turnstile', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/turnstile')>()),
  TURNSTILE_SITE_KEY: 'site-key',
}));
vi.mock('@/lib/api/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/auth')>()),
  forgotPassword: vi.fn(),
}));

import { toast } from 'sonner';
import { ApiClientError, forgotPassword } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import type { TurnstileApi, TurnstileRenderOptions } from '@/lib/turnstile';
import { ForgotPasswordForm } from './ForgotPasswordForm';

const mockForgotPassword = vi.mocked(forgotPassword);
let options: TurnstileRenderOptions;
const turnstileApi = {
  render: vi.fn((_el: HTMLElement, opts: TurnstileRenderOptions) => {
    options = opts;
    return 'widget-1';
  }),
  reset: vi.fn(),
  remove: vi.fn(),
};

beforeEach(() => {
  vi.clearAllMocks();
  window.turnstile = turnstileApi as unknown as TurnstileApi;
});

async function submitWith(email: string) {
  render(<ForgotPasswordForm />);
  await waitFor(() => expect(turnstileApi.render).toHaveBeenCalled());
  options.callback('tok-1');

  const input = screen.getByLabelText(t('auth.forgot_password.email_label'));
  fireEvent.change(input, { target: { value: email } });
  fireEvent.submit(input.closest('form')!);
  return input as HTMLInputElement;
}

describe('ForgotPasswordForm with Turnstile', () => {
  it('sends the widget token with the request', async () => {
    mockForgotPassword.mockResolvedValue();
    await submitWith('user@example.qa');

    await waitFor(() =>
      expect(mockForgotPassword).toHaveBeenCalledWith({ email: 'user@example.qa' }, 'tok-1'),
    );
  });

  it('shows the localized Turnstile error, keeps the input and fetches a fresh challenge', async () => {
    mockForgotPassword.mockRejectedValue(
      new ApiClientError({
        status: 422,
        code: 'TURNSTILE_001',
        messageKey: 'errors.turnstile.failed',
        message: 'The security check failed. Please try again.',
      }),
    );
    const input = await submitWith('user@example.qa');

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(t('auth.errors.TURNSTILE_001')),
    );
    expect(turnstileApi.reset).toHaveBeenCalledWith('widget-1');
    expect(input.value).toBe('user@example.qa');
  });
});
