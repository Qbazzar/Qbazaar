import { beforeEach, describe, expect, it } from 'vitest';

import { ApiClientError } from '@/lib/api/auth';
import { UserErrorCode } from '@/lib/api/types';
import { setClientLocale } from '@/lib/i18n/locale';

import { apiErrorLink, apiErrorMessage } from './api-error-message';

const error = (code: string, details?: Record<string, unknown>) =>
  new ApiClientError({ status: 422, code, messageKey: code, message: 'raw', details: details as never });

beforeEach(() => setClientLocale('en'));

describe('apiErrorMessage', () => {
  it('fills the amount and currency the API sent', () => {
    expect(apiErrorMessage(error('ACCOUNT_003', { amount: '12.50', currency: 'QAR' }))).toContain('12.50 QAR');
  });

  it('falls back to the API message for a code it does not know', () => {
    expect(apiErrorMessage(error('SOMETHING_ELSE'))).toBe('raw');
  });
});

describe('apiErrorLink', () => {
  it('sends an open order to that order and money matters to the wallet', () => {
    expect(apiErrorLink(error('ACCOUNT_004', { order_id: 'o1' }))?.href).toBe('/account/orders/o1');
    expect(apiErrorLink(error('ACCOUNT_004'))?.href).toBe('/account/orders');
    expect(apiErrorLink(error('ACCOUNT_005'))?.href).toBe('/account/wallet');
    expect(apiErrorLink(error('USER_005'))).toBeNull();
  });
});

describe('UserErrorCode', () => {
  it('matches the API: USER_004 wrong current password, USER_005 wrong password on delete and deactivate', () => {
    expect(UserErrorCode.PasswordCurrentRequired).toBe('USER_004');
    expect(UserErrorCode.DeactivationPasswordRequired).toBe('USER_005');
    expect(UserErrorCode.BlockAdminForbidden).toBe('USER_002');
  });
});
