import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
let searchParams = new URLSearchParams();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => searchParams,
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/lib/api/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/auth')>()),
  login: vi.fn(),
}));

import { login } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { LoginForm } from './LoginForm';

const mockLogin = vi.mocked(login);

beforeEach(() => {
  vi.clearAllMocks();
  searchParams = new URLSearchParams();
});

describe('LoginForm', () => {
  it('labels both fields and announces the validation errors', async () => {
    render(<LoginForm />);

    fireEvent.click(screen.getByRole('button', { name: t('auth.login.submit') }));

    const alerts = await screen.findAllByRole('alert');
    expect(alerts.map((alert) => alert.textContent)).toEqual([
      t('auth.errors.identifier_required'),
      t('auth.errors.password_required'),
    ]);
    expect(screen.getByLabelText(new RegExp(t('auth.login.identifier_label')), { selector: 'input' })).toHaveAttribute('aria-invalid', 'true');
    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('signs in and returns to the page the user came from', async () => {
    searchParams = new URLSearchParams('from=/account/ads');
    mockLogin.mockResolvedValue({
      user: { id: 'u1', full_name: 'Tester' },
      tokens: { access_token: 'AT', refresh_token: 'RT', token_type: 'Bearer', expires_in: 900 },
    } as never);
    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText(new RegExp(t('auth.login.identifier_label')), { selector: 'input' }), {
      target: { value: 'buyer@example.qa' },
    });
    fireEvent.change(screen.getByLabelText(new RegExp(t('auth.login.password_label')), { selector: 'input' }), {
      target: { value: 'secret' },
    });
    fireEvent.click(screen.getByRole('button', { name: t('auth.login.submit') }));

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/account/ads'));
    expect(mockLogin).toHaveBeenCalledWith({ identifier: 'buyer@example.qa', password: 'secret' });
  });

  it('explains a just-deactivated account', () => {
    searchParams = new URLSearchParams('deactivated=1');
    render(<LoginForm />);

    expect(screen.getByRole('status')).toHaveTextContent(t('auth.login.deactivated_notice'));
  });
});
