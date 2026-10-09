import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

import { toast } from 'sonner';
import { setClientLocale } from '@/lib/i18n/locale';

import { ShareAdButton } from './ShareAdButton';

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('ShareAdButton', () => {
  it('opens "Share this ad" with the four channels and the ad link', async () => {
    render(<ShareAdButton title="BMW M3" />);

    await userEvent.click(screen.getByRole('button', { name: 'Share Ad' }));

    const dialog = await screen.findByRole('dialog', { name: 'Share this ad' });
    expect(dialog).toBeInTheDocument();
    const url = window.location.href;
    expect(screen.getByRole('link', { name: 'WhatsApp' })).toHaveAttribute('href', `https://wa.me/?text=${encodeURIComponent(`BMW M3 ${url}`)}`);
    expect(screen.getByRole('link', { name: 'Facebook' })).toHaveAttribute('target', '_blank');
    expect(screen.getByRole('link', { name: 'X' })).toHaveAttribute('rel', 'noopener noreferrer');
    expect(screen.getByRole('link', { name: 'Email' })).toHaveAttribute('href', expect.stringMatching(/^mailto:\?subject=BMW%20M3/));
    expect(screen.getByText(url.replace(/^https?:\/\//, ''))).toBeInTheDocument();
  });

  it('copies the link and says so', async () => {
    const user = userEvent.setup();
    render(<ShareAdButton title="BMW M3" />);

    await user.click(screen.getByRole('button', { name: 'Share Ad' }));
    await user.click(await screen.findByRole('button', { name: 'Copy' }));

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('Link copied to clipboard'));
    await expect(navigator.clipboard.readText()).resolves.toBe(window.location.href);
  });

  it('closes on Escape', async () => {
    render(<ShareAdButton title="BMW M3" />);

    await userEvent.click(screen.getByRole('button', { name: 'Share Ad' }));
    await screen.findByRole('dialog');
    await userEvent.keyboard('{Escape}');

    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });
});
