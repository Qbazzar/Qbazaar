import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), warning: vi.fn(), info: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/reports', () => ({ submitReport: vi.fn() }));

import { showDesignToast } from '@/components/design-system/design-toast';
import { renderWithClient } from '@/components/orders/test-utils';
import { submitReport } from '@/lib/api/reports';
import { setClientLocale } from '@/lib/i18n/locale';

import { ChatReportSheet } from './ChatReportSheet';

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('ChatReportSheet', () => {
  it('lists the nine problems of the design with the first one picked, and no free text', () => {
    renderWithClient(<ChatReportSheet open onOpenChange={vi.fn()} peerId="user-2" />);

    expect(screen.getByRole('dialog', { name: 'Report' })).toBeInTheDocument();
    expect(screen.getByText('Select a problem to report')).toBeInTheDocument();
    const radios = screen.getAllByRole('radio');
    expect(radios).toHaveLength(9);
    expect(radios[0]).toBeChecked();
    expect(screen.getByRole('radio', { name: "The Problem Isn't Listed Here" })).not.toBeChecked();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /close/i })).not.toBeInTheDocument();
  });

  it('files the picked problem under the closest API category and confirms with the mint toast', async () => {
    vi.mocked(submitReport).mockResolvedValue({ id: 'r1' } as never);
    const onOpenChange = vi.fn();
    renderWithClient(<ChatReportSheet open onOpenChange={onOpenChange} peerId="user-2" />);

    await userEvent.click(screen.getByRole('radio', { name: 'Harassment or Bullying' }));
    await userEvent.click(screen.getByRole('button', { name: 'Submit Report' }));

    await waitFor(() =>
      expect(submitReport).toHaveBeenCalledWith({
        target_type: 'user',
        target_id: 'user-2',
        category: 'offensive',
        description: 'Harassment or Bullying',
      }),
    );
    expect(showDesignToast).toHaveBeenCalledWith('Your report has been sent successfully');
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });
});
