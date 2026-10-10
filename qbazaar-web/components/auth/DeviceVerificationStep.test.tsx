import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const replace = vi.fn();
vi.mock('next/navigation', () => ({
  useRouter: () => ({ replace }),
  useSearchParams: () => new URLSearchParams('from=/account/messages'),
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api/auth')>()),
  login: vi.fn(),
  verifyNewDevice: vi.fn(),
}));

import { toast } from 'sonner';

import { ApiClientError, verifyNewDevice } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';

import { DeviceVerificationStep, type PendingDeviceCheck } from './DeviceVerificationStep';

const pending: PendingDeviceCheck = {
  challenge: { challenge_token: 'c'.repeat(64), sent_to: '+974*****345', expires_in: 600, can_resend_in: 60 },
  credentials: { identifier: 'buyer@example.qa', password: 'secret' },
  remember: true,
};

function typeCode(code: string) {
  const boxes = screen.getAllByRole('textbox');
  code.split('').forEach((digit, index) => fireEvent.change(boxes[index], { target: { value: digit } }));
}

beforeEach(() => {
  vi.clearAllMocks();
  useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
});

describe('DeviceVerificationStep', () => {
  it('shows where the code went, masked as in the design', () => {
    render(<DeviceVerificationStep pending={pending} onChallenge={vi.fn()} onRestart={vi.fn()} />);

    expect(screen.getByRole('heading', { name: t('auth.verify_otp.heading') })).toBeInTheDocument();
    expect(screen.getByText(/\+974 ••• ••45/)).toBeInTheDocument();
    expect(screen.getAllByRole('textbox')).toHaveLength(6);
  });

  it('finishes the sign-in with the code and keeps the remember choice', async () => {
    vi.mocked(verifyNewDevice).mockResolvedValue({
      user: { id: 'u1' },
      tokens: { access_token: 'AT', refresh_token: 'RT', token_type: 'Bearer', expires_in: 900 },
    } as never);
    render(<DeviceVerificationStep pending={pending} onChallenge={vi.fn()} onRestart={vi.fn()} />);

    typeCode('482915');

    await waitFor(() => expect(replace).toHaveBeenCalledWith('/account/messages'));
    expect(verifyNewDevice).toHaveBeenCalledWith({ challenge_token: 'c'.repeat(64), code: '482915' }, { remember: true });
    expect(useAuthStore.getState().accessToken).toBe('AT');
  });

  it('goes back to the login form when the challenge expired', async () => {
    vi.mocked(verifyNewDevice).mockRejectedValue(
      new ApiClientError({ status: 401, code: 'AUTH_012', messageKey: 'x', message: 'Unknown challenge' }),
    );
    const onRestart = vi.fn();
    render(<DeviceVerificationStep pending={pending} onChallenge={vi.fn()} onRestart={onRestart} />);

    typeCode('482915');

    await waitFor(() => expect(onRestart).toHaveBeenCalled());
    expect(toast.error).toHaveBeenCalledWith(t('auth.errors.AUTH_012'));
  });
});
