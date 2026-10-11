import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/components/design-system/design-toast', () => ({ showDesignToast: vi.fn() }));
vi.mock('@/lib/api/account', () => ({ createAddress: vi.fn(), updateAddress: vi.fn(), deleteAddress: vi.fn() }));

import { showDesignToast } from '@/components/design-system/design-toast';
import { renderWithClient } from '@/components/orders/test-utils';
import { deleteAddress } from '@/lib/api/account';
import type { SavedAddress } from '@/lib/api/commerce-types';
import { setClientLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

import { AddressDialog, addressSummary } from './AddressDialog';

const address = {
  id: 'a1',
  full_name: 'Farah Alzinati',
  street: 'Al Sadd Street',
  house_number: '12',
  supplement: null,
  postal_code: '1234',
  city: 'Doha',
} as SavedAddress;

function renderDialog() {
  return renderWithClient(<AddressDialog open onOpenChange={vi.fn()} addresses={[address]} defaultName="Farah" />);
}

beforeEach(() => {
  setClientLocale('en');
  vi.clearAllMocks();
});

describe('deleting an address', () => {
  it('asks first and deletes nothing until confirmed', async () => {
    renderDialog();

    await userEvent.click(screen.getByRole('button', { name: t('account.address.delete_one', { address: addressSummary(address) }) }));

    expect(screen.getByRole('dialog', { name: t('account.address.delete_confirm_title') })).toBeInTheDocument();
    expect(deleteAddress).not.toHaveBeenCalled();
  });

  it('keeps the address when the question is cancelled', async () => {
    renderDialog();

    await userEvent.click(screen.getByRole('button', { name: t('account.address.delete_one', { address: addressSummary(address) }) }));
    const confirm = screen.getByRole('dialog', { name: t('account.address.delete_confirm_title') });
    await userEvent.click(within(confirm).getByRole('button', { name: t('common.cancel') }));

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: t('account.address.delete_confirm_title') })).not.toBeInTheDocument(),
    );
    expect(deleteAddress).not.toHaveBeenCalled();
  });

  it('deletes after the confirmation and says so', async () => {
    vi.mocked(deleteAddress).mockResolvedValue(undefined as never);
    renderDialog();

    await userEvent.click(screen.getByRole('button', { name: t('account.address.delete_one', { address: addressSummary(address) }) }));
    const confirm = screen.getByRole('dialog', { name: t('account.address.delete_confirm_title') });
    await userEvent.click(within(confirm).getByRole('button', { name: t('common.delete') }));

    await waitFor(() => expect(deleteAddress).toHaveBeenCalled());
    expect(vi.mocked(deleteAddress).mock.calls[0][0]).toBe('a1');
    await waitFor(() => expect(showDesignToast).toHaveBeenCalledWith(t('account.address.deleted')));
  });
});
