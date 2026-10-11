import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), warning: vi.fn(), info: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/reports', () => ({ submitReport: vi.fn() }));
vi.mock('@/lib/api/users', () => ({ blockUser: vi.fn() }));

import { renderWithClient } from '@/components/orders/test-utils';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import { ChatSettingsMenu } from './ChatSettingsMenu';

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('ChatSettingsMenu focus', () => {
  it.each([
    ['messaging.menu.report', 'Report'],
    ['messaging.menu.block', 'Block User?'],
  ])('moves into the dialog of %s and back to the menu button when it closes', async (itemKey, dialogName) => {
    renderWithClient(<ChatSettingsMenu peer={{ id: 'u2', full_name: 'Sara Ali' }} />);
    const trigger = screen.getByRole('button', { name: t('messaging.menu.label') });

    await userEvent.click(trigger);
    await userEvent.click(await screen.findByRole('menuitem', { name: new RegExp(t(itemKey)) }));

    const dialog = await screen.findByRole('dialog', { name: dialogName });
    await waitFor(() => expect(dialog.contains(document.activeElement)).toBe(true));

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(trigger).toHaveFocus());
  });
});
