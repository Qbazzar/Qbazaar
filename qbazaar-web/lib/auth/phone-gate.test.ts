import { AxiosError, AxiosHeaders } from 'axios';
import { describe, expect, it } from 'vitest';
import { ApiClientError } from '@/lib/api/auth';
import {
  hasVerifiablePhone,
  isPhoneNotVerifiedError,
  isPhoneVerificationPath,
  phoneGateRedirect,
  resolvePhoneGateStatus,
  verifyOtpHref,
} from './phone-gate';

function axiosErrorWithCode(code: string): AxiosError {
  const config = { headers: new AxiosHeaders() };
  return new AxiosError('Forbidden', 'ERR_BAD_REQUEST', config, null, {
    status: 403,
    statusText: 'Forbidden',
    headers: {},
    config,
    data: { success: false, error: { code, message: 'x' } },
  });
}

describe('resolvePhoneGateStatus', () => {
  it('waits for the session bootstrap', () => {
    expect(
      resolvePhoneGateStatus({ isHydrated: false, isAuthenticated: false, user: null }),
    ).toBe('loading');
  });

  it('treats a signed-out visitor as a guest', () => {
    expect(
      resolvePhoneGateStatus({ isHydrated: true, isAuthenticated: false, user: null }),
    ).toBe('guest');
  });

  it('distinguishes verified and unverified users', () => {
    expect(
      resolvePhoneGateStatus({
        isHydrated: true,
        isAuthenticated: true,
        user: { phone_verified: false },
      }),
    ).toBe('unverified');
    expect(
      resolvePhoneGateStatus({
        isHydrated: true,
        isAuthenticated: true,
        user: { phone_verified: true },
      }),
    ).toBe('verified');
  });
});

describe('phoneGateRedirect', () => {
  it('sends guests to login with the return path', () => {
    expect(phoneGateRedirect('guest', '/ads/7')).toBe('/login?from=%2Fads%2F7');
  });

  it('sends unverified users to the verification page with the return path', () => {
    expect(phoneGateRedirect('unverified', '/post-ad')).toBe(
      '/account/verification?continue=%2Fpost-ad',
    );
  });

  it('lets verified users and pending sessions through', () => {
    expect(phoneGateRedirect('verified', '/post-ad')).toBeNull();
    expect(phoneGateRedirect('loading', '/post-ad')).toBeNull();
  });

  it('never carries an external return path', () => {
    expect(phoneGateRedirect('unverified', '//evil.example')).toBe(
      '/account/verification?continue=%2F',
    );
  });
});

describe('verifyOtpHref', () => {
  it('encodes the phone and the continue target', () => {
    expect(verifyOtpHref('+97455123456', '/account/messages?c=1')).toBe(
      '/verify-otp?phone=%2B97455123456&continue=%2Faccount%2Fmessages%3Fc%3D1',
    );
  });

  it('falls back to the verification page for unsafe targets', () => {
    expect(verifyOtpHref('+97455123456', 'https://evil.example')).toBe(
      '/verify-otp?phone=%2B97455123456&continue=%2Faccount%2Fverification',
    );
  });
});

describe('hasVerifiablePhone', () => {
  it('accepts only a Qatari number', () => {
    expect(hasVerifiablePhone('+97455123456')).toBe(true);
    expect(hasVerifiablePhone('')).toBe(false);
    expect(hasVerifiablePhone(null)).toBe(false);
    expect(hasVerifiablePhone('+15551234567')).toBe(false);
  });
});

describe('isPhoneVerificationPath', () => {
  it('matches the verification flow pages only', () => {
    expect(isPhoneVerificationPath('/account/verification')).toBe(true);
    expect(isPhoneVerificationPath('/verify-otp')).toBe(true);
    expect(isPhoneVerificationPath('/post-ad')).toBe(false);
  });
});

describe('isPhoneNotVerifiedError', () => {
  it('recognises AUTH_003 on typed and raw errors', () => {
    const typed = new ApiClientError({
      status: 403,
      code: 'AUTH_003',
      messageKey: 'errors.auth.phone.not_verified',
      message: 'Phone not verified',
    });
    expect(isPhoneNotVerifiedError(typed)).toBe(true);
    expect(isPhoneNotVerifiedError(axiosErrorWithCode('AUTH_003'))).toBe(true);
  });

  it('ignores every other failure', () => {
    expect(isPhoneNotVerifiedError(axiosErrorWithCode('AD_005'))).toBe(false);
    expect(isPhoneNotVerifiedError(new Error('boom'))).toBe(false);
    expect(isPhoneNotVerifiedError(null)).toBe(false);
  });
});
