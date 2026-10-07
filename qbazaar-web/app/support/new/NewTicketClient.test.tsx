import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mutateAsync = vi.fn();
vi.mock('@/lib/queries/support', () => ({
  useCreateTicketMutation: () => ({ mutateAsync, isPending: false }),
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

import { ApiClientError } from '@/lib/api/auth';
import { setClientLocale } from '@/lib/i18n/locale';
import type { SupportTicket, User } from '@/lib/api/types';
import { useAuthStore } from '@/store/auth';

import { NewTicketClient } from './NewTicketClient';

const ticket = { id: '01m48b0qnwrvz1kbzws8tqxrzm' } as SupportTicket;

beforeEach(() => {
  setClientLocale('en');
  useAuthStore.setState({ user: null, accessToken: null });
});
afterEach(() => vi.clearAllMocks());

function fill(label: string, value: string) {
  fireEvent.change(screen.getByLabelText(label, { exact: false }), { target: { value } });
}

function submit() {
  fireEvent.click(screen.getByRole('button', { name: 'Submit' }));
}

describe('NewTicketClient', () => {
  it('announces each invalid field and sends nothing', async () => {
    render(<NewTicketClient />);
    submit();

    const alerts = await screen.findAllByRole('alert');
    expect(alerts.map((alert) => alert.textContent)).toEqual([
      'Subject is too short (min 3 characters)',
      'Details are too short (min 10 characters)',
      'Enter your email so we can reply',
    ]);
    const subject = screen.getByLabelText('Subject', { exact: false });
    expect(subject).toHaveAttribute('aria-invalid', 'true');
    expect(subject.getAttribute('aria-describedby')).toContain(alerts[0].parentElement!.id);
    expect(screen.getByLabelText('Your email', { exact: false })).toBeRequired();
    expect(mutateAsync).not.toHaveBeenCalled();
  });

  it('lets guests file a ticket and shows the reference to quote', async () => {
    mutateAsync.mockResolvedValue(ticket);
    render(<NewTicketClient />);

    fill('Subject', 'Cannot publish my ad');
    fireEvent.change(screen.getByLabelText('Category', { exact: false }), { target: { value: 'technical' } });
    fill('Issue details', 'The publish button does nothing on step three.');
    fill('Your email', 'guest@example.qa');
    submit();

    await waitFor(() =>
      expect(mutateAsync).toHaveBeenCalledWith({
        payload: {
          subject: 'Cannot publish my ad',
          category: 'technical',
          body: 'The publish button does nothing on step three.',
          email: 'guest@example.qa',
        },
        turnstileToken: undefined,
      }),
    );
    expect(await screen.findByRole('heading', { level: 2, name: 'Your message has been received' })).toBeInTheDocument();
    expect(screen.getByText(ticket.id)).toHaveAttribute('dir', 'ltr');
    expect(document.activeElement).toContainElement(screen.getByText(ticket.id));
  });

  it('asks members for no email', async () => {
    useAuthStore.setState({ user: { id: 'u1' } as User, accessToken: 'AT' });
    mutateAsync.mockResolvedValue(ticket);
    render(<NewTicketClient />);

    expect(screen.queryByLabelText('Your email', { exact: false })).toBeNull();
    fill('Subject', 'Cannot publish my ad');
    fill('Issue details', 'The publish button does nothing on step three.');
    submit();

    await waitFor(() =>
      expect(mutateAsync).toHaveBeenCalledWith({
        payload: { subject: 'Cannot publish my ad', category: 'general', body: 'The publish button does nothing on step three.' },
        turnstileToken: undefined,
      }),
    );
  });

  it('shows server-side field errors next to the field', async () => {
    mutateAsync.mockRejectedValue(
      new ApiClientError({
        status: 422,
        code: 'VALIDATION_FAILED',
        messageKey: 'errors.validation',
        message: 'Validation failed',
        details: { email: ['The email domain is not accepted.'] },
      }),
    );
    render(<NewTicketClient />);

    fill('Subject', 'Cannot publish my ad');
    fill('Issue details', 'The publish button does nothing on step three.');
    fill('Your email', 'guest@example.qa');
    submit();

    expect(await screen.findByRole('alert')).toHaveTextContent('The email domain is not accepted.');
    expect(screen.getByLabelText('Your email', { exact: false })).toHaveFocus();
  });
});
