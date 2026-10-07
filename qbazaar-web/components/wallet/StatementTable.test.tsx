import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';
import { buildWalletEntry } from '@/lib/orders/test-fixtures';

import { signedAmount } from './StatementTable';

beforeEach(() => setClientLocale('en'));

describe('signedAmount', () => {
  it('signs a ledger line by its direction', () => {
    expect(signedAmount(buildWalletEntry({ direction: 'increase', amount: '10.00' }))).toBe('+QAR 10.00');
    expect(signedAmount(buildWalletEntry({ direction: 'decrease', amount: '277.50' }))).toBe('−QAR 277.50');
  });
});
