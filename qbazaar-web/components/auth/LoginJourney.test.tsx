import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('./LoginForm', () => ({
  LoginForm: ({ onDeviceCheck }: { onDeviceCheck: (pending: unknown) => void }) => (
    <button
      type="button"
      onClick={() =>
        onDeviceCheck({
          challenge: { challenge_token: 'c', sent_to: '+974*****345', expires_in: 600, can_resend_in: 60 },
          credentials: { identifier: 'a@b.qa', password: 'secret' },
          remember: false,
        })
      }
    >
      go
    </button>
  ),
}));
vi.mock('./DeviceVerificationStep', () => ({ DeviceVerificationStep: () => <p>device check</p> }));

import { toast } from 'sonner';

import { t } from '@/lib/i18n/messages';

import { DEVICE_CHECK_TTL_MS, LoginJourney } from './LoginJourney';

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('LoginJourney', () => {
  it('sends the user back to the login form once the kept password is five minutes old', () => {
    render(<LoginJourney welcome={null} />);
    act(() => screen.getByRole('button', { name: 'go' }).click());
    expect(screen.getByText('device check')).toBeInTheDocument();

    act(() => {
      vi.advanceTimersByTime(DEVICE_CHECK_TTL_MS - 1000);
    });
    expect(screen.getByText('device check')).toBeInTheDocument();

    act(() => {
      vi.advanceTimersByTime(1000);
    });
    expect(screen.queryByText('device check')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'go' })).toBeInTheDocument();
    expect(toast.error).toHaveBeenCalledWith(t('auth.errors.AUTH_012'));
  });
});
