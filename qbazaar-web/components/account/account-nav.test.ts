import { describe, expect, it } from 'vitest';

import { isNavItemActive, isSettingsPath, settingsSectionFor } from './account-nav';

describe('account navigation', () => {
  it('puts the hub and the settings sections in the sidebar shell', () => {
    for (const path of ['/account', '/account/profile', '/account/security', '/account/data', '/account/blocked-users']) {
      expect(isSettingsPath(path)).toBe(true);
    }
  });

  it('renders the activity pages full width', () => {
    for (const path of ['/account/ads', '/account/ads/1/edit', '/account/orders/9', '/account/promotions', '/account/messages', '/account/support/7']) {
      expect(isSettingsPath(path)).toBe(false);
    }
  });

  it('matches nested routes but keeps the hub exact', () => {
    expect(isNavItemActive('/account/sessions', '/account/sessions')).toBe(true);
    expect(isNavItemActive('/account/sessions/9', '/account/sessions')).toBe(true);
    expect(isNavItemActive('/account/profile', '/account')).toBe(false);
    expect(isNavItemActive('/account/profile-extra', '/account/profile')).toBe(false);
  });

  it('puts the wallet in the shell but opens its sub-pages full width', () => {
    expect(isSettingsPath('/account/wallet')).toBe(true);
    expect(settingsSectionFor('/account/wallet').labelKey).toBe('account.nav.wallet');
    for (const path of ['/account/wallet/settlements', '/account/wallet/withdrawals', '/account/wallet/bank-accounts']) {
      expect(isSettingsPath(path)).toBe(false);
    }
  });

  it('names the section a path belongs to, the hub otherwise', () => {
    expect(settingsSectionFor('/account/privacy').labelKey).toBe('account.nav.data_protection');
    expect(settingsSectionFor('/account').labelKey).toBe('account.nav.overview');
  });
});
