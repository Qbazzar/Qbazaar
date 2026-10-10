import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
let searchParams = new URLSearchParams();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => searchParams,
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn(), info: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/auth')>()),
  login: vi.fn(),
}));

import { toast } from 'sonner';

import { login } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { LoginForm } from './LoginForm';

const mockLogin = vi.mocked(login);
const signedIn = {
  status: 'signed_in',
  data: {
    user: { id: 'u1', full_name: 'Tester' },
    tokens: { access_token: 'AT', refresh_token: 'RT', token_type: 'Bearer', expires_in: 900 },
  },
} as never;

function fillAndSubmit() {
  fireEvent.change(screen.getByLabelText(new RegExp(t('auth.login.identifier_label')), { selector: 'input' }), {
    target: { value: 'buyer@example.qa' },
  });
  fireEvent.change(screen.getByLabelText(new RegExp(t('auth.login.password_label')), { selector: 'input' }), {
    target: { value: 'secret' },
  });
  fireEvent.click(screen.getByRole('button', { name: t('auth.login.submit') }));
}

beforeEach(() => {
  vi.clearAllMocks();
  searchParams = new URLSearchParams();
});

describe('LoginForm', () => {
  it('labels both fields and announces the validation errors', async () => {
    render(<LoginForm onDeviceCheck={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: t('auth.login.submit') }));

    const alerts = await screen.findAllByRole('alert');
    expect(alerts.map((alert) => alert.textContent)).toEqual([
      t('auth.errors.identifier_required'),
      t('auth.errors.password_required'),
    ]);
    expect(screen.getByLabelText(new RegExp(t('auth.login.identifier_label')), { selector: 'input' })).toHaveAttribute('aria-invalid', 'true');
    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('signs in for this browser session and returns to the page the user came from', async () => {
    searchParams = new URLSearchParams('from=/account/ads');
    mockLogin.mockResolvedValue(signedIn);
    render(<LoginForm onDeviceCheck={vi.fn()} />);

    expect(screen.getByRole('checkbox', { name: t('auth.login.remember') })).not.toBeChecked();
    fillAndSubmit();

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/account/ads'));
    expect(mockLogin).toHaveBeenCalledWith({ identifier: 'buyer@example.qa', password: 'secret' }, { remember: false });
  });

  it('keeps the sign-in when "Remember me" is ticked', async () => {
    mockLogin.mockResolvedValue(signedIn);
    render(<LoginForm onDeviceCheck={vi.fn()} />);

    fireEvent.click(screen.getByRole('checkbox', { name: t('auth.login.remember') }));
    fillAndSubmit();

    await waitFor(() => expect(mockLogin).toHaveBeenCalledWith(expect.anything(), { remember: true }));
  });

  it('hands a new-device challenge to the verify step', async () => {
    const challenge = { challenge_token: 'c'.repeat(64), sent_to: '+974*****345', expires_in: 600, can_resend_in: 60 };
    mockLogin.mockResolvedValue({ status: 'device_check', challenge });
    const onDeviceCheck = vi.fn();
    render(<LoginForm onDeviceCheck={onDeviceCheck} />);

    fillAndSubmit();

    await waitFor(() =>
      expect(onDeviceCheck).toHaveBeenCalledWith({
        challenge,
        credentials: { identifier: 'buyer@example.qa', password: 'secret' },
        remember: false,
      }),
    );
    expect(replace).not.toHaveBeenCalled();
  });

  it('shows the three social tiles, which say they are not available yet', () => {
    render(<LoginForm onDeviceCheck={vi.fn()} />);

    const google = screen.getByRole('button', { name: t('auth.social.continue_with', { provider: 'Google' }) });
    expect(screen.getByRole('button', { name: t('auth.social.continue_with', { provider: 'Facebook' }) })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: t('auth.social.continue_with', { provider: 'Apple' }) })).toBeInTheDocument();
    expect(screen.getByText(t('auth.social.divider'))).toBeInTheDocument();

    fireEvent.click(google);
    expect(toast.info).toHaveBeenCalledWith(t('auth.social.unavailable', { provider: 'Google' }));
  });

  it('explains a just-deactivated account', () => {
    searchParams = new URLSearchParams('deactivated=1');
    render(<LoginForm onDeviceCheck={vi.fn()} />);

    expect(screen.getByRole('status')).toHaveTextContent(t('auth.login.deactivated_notice'));
  });
});
