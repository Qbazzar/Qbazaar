import type { ReactNode } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const push = vi.fn();
vi.mock('next/navigation', () => ({ useRouter: () => ({ push }) }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), info: vi.fn(), success: vi.fn(), warning: vi.fn() } }));
vi.mock('@/lib/echo/client', () => ({ disconnectEcho: vi.fn() }));
vi.mock('@/lib/push/fcm', () => ({ disablePush: vi.fn() }));
vi.mock('@/lib/api/reports', () => ({ submitReport: vi.fn() }));

import { toast } from 'sonner';
import { ApiClientError } from '@/lib/api/auth';
import { submitReport } from '@/lib/api/reports';
import type { Report, User } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';
import { useAuthStore } from '@/store/auth';

import { ReportButton } from './ReportButton';

function renderButton() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  return render(<ReportButton target_type="ad" target_id="ad-1" label="Report Ad" />, { wrapper });
}

async function reportAsFraud() {
  await userEvent.click(screen.getByRole('button', { name: 'Report Ad' }));
  await userEvent.click(await screen.findByRole('radio', { name: 'Fraud or scam' }));
  await userEvent.click(screen.getByRole('button', { name: 'Submit Report' }));
}

function refusal(code: string, status = 422) {
  return new ApiClientError({ status, code, messageKey: 'x', message: 'Refused' });
}

beforeEach(() => {
  setClientLocale('en');
  useAuthStore.setState({ user: { id: 'buyer' } as User, accessToken: 'AT', isHydrated: true });
  vi.clearAllMocks();
});

describe('ReportButton', () => {
  it('sends guests to login and back', async () => {
    useAuthStore.setState({ user: null, accessToken: null, isHydrated: true });
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Report Ad' }));

    const here = `${window.location.pathname}${window.location.search}`;
    expect(push).toHaveBeenCalledWith(`/login?from=${encodeURIComponent(here)}`);
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('opens "Report this ad" with the five reasons of the reference', async () => {
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Report Ad' }));

    expect(await screen.findByRole('dialog', { name: 'Report this ad' })).toBeInTheDocument();
    expect(screen.getAllByRole('radio').map((radio) => radio.closest('label')?.textContent)).toEqual([
      'Fraud or scam',
      'Prohibited item',
      'Wrong category',
      'Duplicate listing',
      'Offensive content',
    ]);
  });

  it('closes on Cancel without sending', async () => {
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Report Ad' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(submitReport).not.toHaveBeenCalled();
  });

  it('asks for a reason before sending', async () => {
    renderButton();

    await userEvent.click(screen.getByRole('button', { name: 'Report Ad' }));
    await userEvent.click(await screen.findByRole('button', { name: 'Submit Report' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Pick a reason');
    expect(submitReport).not.toHaveBeenCalled();
  });

  it('sends the report and then reads "Reported!"', async () => {
    vi.mocked(submitReport).mockResolvedValue({ id: 'r1' } as Report);
    renderButton();

    await reportAsFraud();

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Report submitted. Thank you.'));
    expect(submitReport).toHaveBeenCalledWith({ target_type: 'ad', target_id: 'ad-1', category: 'fraud', description: undefined });
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(screen.getByRole('button', { name: 'Reported!' })).toHaveAttribute('aria-disabled', 'true');
  });

  it('treats a repeat report (REPORT_002) as already reported', async () => {
    vi.mocked(submitReport).mockRejectedValue(refusal('REPORT_002', 429));
    renderButton();

    await reportAsFraud();

    await waitFor(() => expect(toast.warning).toHaveBeenCalledWith('Already reported'));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(screen.getByRole('button', { name: 'Reported!' })).toBeInTheDocument();
  });

  it('closes on a report of yourself (REPORT_001)', async () => {
    vi.mocked(submitReport).mockRejectedValue(refusal('REPORT_001'));
    renderButton();

    await reportAsFraud();

    await waitFor(() => expect(toast.info).toHaveBeenCalledWith("You can't report yourself"));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    expect(screen.getByRole('button', { name: 'Report Ad' })).toBeInTheDocument();
  });

  it('keeps the form open when the target is unknown (REPORT_003)', async () => {
    vi.mocked(submitReport).mockRejectedValue(refusal('REPORT_003'));
    renderButton();

    await reportAsFraud();

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith("This item can't be reported"));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});
