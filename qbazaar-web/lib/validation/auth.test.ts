import { describe, expect, it } from 'vitest';
import {
  loginSchema,
  registerSchema,
  verifyOtpSchema,
} from '@/lib/validation/auth';

describe('registerSchema', () => {
  const valid = {
    full_name: 'Ahmed Jaber',
    email: 'Ahmed@Example.com',
    phone: '+97412345678',
    password: 'Password1!',
    account_type: 'private' as const,
    accepted_terms: true as const,
  };

  it('accepts a well-formed registration and lowercases the email', () => {
    const parsed = registerSchema.parse(valid);
    expect(parsed.email).toBe('ahmed@example.com');
  });

  it('rejects a non-Qatar phone number', () => {
    const result = registerSchema.safeParse({ ...valid, phone: '+10000000000' });
    expect(result.success).toBe(false);
  });

  it.each([
    ['too short', 'Aa1!'],
    ['no uppercase', 'password1!'],
    ['no symbol', 'Password11'],
    ['no number', 'Password!!'],
  ])('rejects a weak password (%s)', (_label, password) => {
    expect(registerSchema.safeParse({ ...valid, password }).success).toBe(false);
  });

  it('requires accepted_terms to be true', () => {
    expect(
      registerSchema.safeParse({ ...valid, accepted_terms: false }).success,
    ).toBe(false);
  });
});

describe('loginSchema', () => {
  it('accepts an email identifier', () => {
    expect(
      loginSchema.safeParse({ identifier: 'a@b.com', password: 'x' }).success,
    ).toBe(true);
  });

  it('accepts a Qatar phone identifier', () => {
    expect(
      loginSchema.safeParse({ identifier: '+97412345678', password: 'x' })
        .success,
    ).toBe(true);
  });

  it('rejects a garbage identifier', () => {
    expect(
      loginSchema.safeParse({ identifier: 'not-an-identifier', password: 'x' })
        .success,
    ).toBe(false);
  });
});

describe('verifyOtpSchema', () => {
  it('accepts a 6-digit code', () => {
    expect(
      verifyOtpSchema.safeParse({ phone: '+97412345678', code: '123456' })
        .success,
    ).toBe(true);
  });

  it('rejects a non-6-digit code', () => {
    expect(
      verifyOtpSchema.safeParse({ phone: '+97412345678', code: '12ab' })
        .success,
    ).toBe(false);
  });
});
