import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/account', () => ({ changePassword: vi.fn() }));

import { toast } from 'sonner';

import { renderWithClient } from '@/components/orders/test-utils';
import { changePassword } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import { PasswordChangeForm } from './PasswordChangeForm';

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('PasswordChangeForm', () => {
  it('marks the three fields required and gives each an eye', async () => {
    renderWithClient(<PasswordChangeForm />);

    const current = screen.getByLabelText(t('account.security.current_password_label'));
    expect(current).toBeRequired();
    expect(current).toHaveAttribute('aria-required', 'true');
    const eyes = screen.getAllByRole('button', { name: t('auth.password.toggle') });
    expect(eyes).toHaveLength(3);

    await userEvent.click(eyes[0]);
    expect(current).toHaveAttribute('type', 'text');
  });

  it('shows a wrong current password (USER_004) on that field', async () => {
    vi.mocked(changePassword).mockRejectedValue(
      new ApiClientError({ status: 422, code: 'USER_004', messageKey: 'x', message: 'raw' }),
    );
    renderWithClient(<PasswordChangeForm />);

    await userEvent.type(screen.getByLabelText(t('account.security.current_password_label')), 'Wrong-pass1!');
    await userEvent.type(screen.getByLabelText(t('account.security.new_password_label')), 'Brand-new1!');
    await userEvent.type(screen.getByLabelText(t('account.security.password_confirmation_label')), 'Brand-new1!');
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(t('account.errors.USER_004')));
    expect(screen.getByText(t('account.errors.USER_004'))).toBeInTheDocument();
  });
});
