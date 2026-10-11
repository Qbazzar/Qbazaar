import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/account', () => ({
  requestReauthCode: vi.fn(),
  requestEmailChange: vi.fn(),
  requestPhoneChange: vi.fn(),
  confirmPhoneChange: vi.fn(),
}));

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { toast } from 'sonner';

import { requestEmailChange, requestReauthCode } from '@/lib/api/account';
import { ApiClientError } from '@/lib/api/auth';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';

import { EditEmailDialog, EditPhoneDialog } from './ContactChangeDialogs';

function renderDialog(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

function typeCode(code: string) {
  const boxes = screen.getAllByRole('textbox');
  code.split('').forEach((digit, index) => fireEvent.change(boxes[index], { target: { value: digit } }));
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
  vi.mocked(requestReauthCode).mockResolvedValue({ can_resend_in: 60 } as never);
  useAuthStore.setState({
    user: { id: 'u1', email: 'me@example.qa', phone: '+97455512345' } as never,
    accessToken: 'AT',
    isHydrated: true,
  });
});

describe('EditEmailDialog', () => {
  it('refuses the email the account already has, before asking for any code', async () => {
    renderDialog(<EditEmailDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(t('account.contact.new_email')), { target: { value: 'ME@example.qa' } });
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    expect(await screen.findByText(t('account.contact.same_email'))).toBeInTheDocument();
    expect(requestReauthCode).not.toHaveBeenCalled();
  });

  it('asks for the emailed security code, then sends the change with it', async () => {
    vi.mocked(requestEmailChange).mockResolvedValue(undefined);
    renderDialog(<EditEmailDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(t('account.contact.new_email')), { target: { value: 'new@example.qa' } });
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    expect(await screen.findByText(t('account.contact.reauth_sent', { email: 'me@example.qa' }))).toBeInTheDocument();
    typeCode('123456');
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    await waitFor(() =>
      expect(requestEmailChange).toHaveBeenCalledWith({ email: 'new@example.qa', reauth_code: '123456' }),
    );
  });

  it('shows ACCOUNT_001 under the code boxes in words', async () => {
    vi.mocked(requestEmailChange).mockRejectedValue(
      new ApiClientError({ status: 422, code: 'ACCOUNT_001', messageKey: 'x', message: 'raw english' }),
    );
    renderDialog(<EditEmailDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(t('account.contact.new_email')), { target: { value: 'new@example.qa' } });
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));
    await screen.findByText(t('account.contact.reauth_sent', { email: 'me@example.qa' }));
    typeCode('000000');
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    expect(await screen.findByText(t('account.errors.ACCOUNT_001'))).toBeInTheDocument();
    expect(screen.queryByText('raw english')).not.toBeInTheDocument();
  });

  it('keeps the dialog on the email step when the code cannot be sent', async () => {
    vi.mocked(requestReauthCode).mockRejectedValue(
      new ApiClientError({ status: 429, code: 'AUTH_006', messageKey: 'x', message: 'slow down' }),
    );
    renderDialog(<EditEmailDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByLabelText(t('account.contact.new_email')), { target: { value: 'new@example.qa' } });
    await userEvent.click(screen.getByRole('button', { name: t('common.save') }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(t('auth.errors.AUTH_006')));
    expect(screen.getByLabelText(t('account.contact.new_email'))).toBeInTheDocument();
  });
});

describe('EditPhoneDialog', () => {
  it('refuses the number the account already has, before asking for any code', async () => {
    renderDialog(<EditPhoneDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByPlaceholderText(t('account.contact.phone_placeholder')), { target: { value: '55512345' } });
    await userEvent.click(screen.getByRole('button', { name: t('account.contact.confirm_phone') }));

    expect(await screen.findByText(t('account.contact.same_phone'))).toBeInTheDocument();
    expect(requestReauthCode).not.toHaveBeenCalled();
  });

  it('goes through the security code step before texting the new number', async () => {
    renderDialog(<EditPhoneDialog open onOpenChange={vi.fn()} />);

    fireEvent.change(screen.getByPlaceholderText(t('account.contact.phone_placeholder')), { target: { value: '66612345' } });
    await userEvent.click(screen.getByRole('button', { name: t('account.contact.confirm_phone') }));

    expect(await screen.findByText(t('account.contact.reauth_sent', { email: 'me@example.qa' }))).toBeInTheDocument();
  });
});
