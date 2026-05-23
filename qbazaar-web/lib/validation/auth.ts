/**
 * Zod schemas mirroring the contract in qbazaar-contracts/openapi/v1.yaml.
 *
 * Keep these in lock-step with the backend validation rules — the backend is
 * the source of truth, but the client validates first to avoid round-trips.
 *
 * Reference: openapi/v1.yaml → components.schemas.{RegisterRequest,LoginRequest,RefreshRequest}
 */
import { z } from 'zod';

// Qatar mobile numbers always carry the +974 prefix followed by 8 digits.
export const qatarPhoneRegex = /^\+974[0-9]{8}$/;

// Backend rule: ≥ 8 chars, at least one uppercase, one lowercase, one number, one symbol.
const passwordRules = z
  .string()
  .min(8, 'auth.errors.password_min')
  .regex(/[A-Z]/, 'auth.errors.password_uppercase')
  .regex(/[a-z]/, 'auth.errors.password_lowercase')
  .regex(/[0-9]/, 'auth.errors.password_number')
  .regex(/[^A-Za-z0-9]/, 'auth.errors.password_symbol');

export const registerSchema = z.object({
  full_name: z
    .string()
    .trim()
    .min(3, 'auth.errors.full_name_min')
    .max(80, 'auth.errors.full_name_max'),
  email: z.string().trim().toLowerCase().email('auth.errors.email_invalid'),
  phone: z
    .string()
    .trim()
    .regex(qatarPhoneRegex, 'auth.errors.phone_invalid'),
  password: passwordRules,
  account_type: z.enum(['private', 'business']),
  language: z.enum(['ar', 'en']).optional(),
  accepted_terms: z
    .literal(true, { message: 'auth.errors.terms_required' }),
});

export type RegisterInput = z.infer<typeof registerSchema>;

export const loginSchema = z.object({
  // The backend accepts either email or Qatari phone — we accept either shape.
  identifier: z
    .string()
    .trim()
    .min(1, 'auth.errors.identifier_required')
    .refine(
      (value) => qatarPhoneRegex.test(value) || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value),
      'auth.errors.identifier_invalid',
    ),
  password: z.string().min(1, 'auth.errors.password_required'),
});

export type LoginInput = z.infer<typeof loginSchema>;

export const refreshSchema = z.object({
  refresh_token: z.string().min(1),
});

export type RefreshInput = z.infer<typeof refreshSchema>;

// ── OTP ────────────────────────────────────────────────────────────────────
// Contract: OtpVerifyRequest → code matches `^[0-9]{6}$`.
export const otpCodeRegex = /^[0-9]{6}$/;

export const verifyOtpSchema = z.object({
  phone: z
    .string()
    .trim()
    .regex(qatarPhoneRegex, 'auth.errors.phone_invalid'),
  code: z
    .string()
    .trim()
    .regex(otpCodeRegex, 'auth.errors.otp_invalid_format'),
});

export type VerifyOtpInput = z.infer<typeof verifyOtpSchema>;

// ── Forgot password ────────────────────────────────────────────────────────
export const forgotPasswordSchema = z.object({
  email: z.string().trim().toLowerCase().email('auth.errors.email_invalid'),
});

export type ForgotPasswordInput = z.infer<typeof forgotPasswordSchema>;

// ── Reset password ─────────────────────────────────────────────────────────
// Contract requires email, token, password, password_confirmation and the
// password must satisfy the same rules as registration.
export const resetPasswordSchema = z
  .object({
    email: z.string().trim().toLowerCase().email('auth.errors.email_invalid'),
    token: z.string().min(1, 'auth.errors.reset_token_required'),
    password: passwordRules,
    password_confirmation: z
      .string()
      .min(1, 'auth.errors.password_confirmation_required'),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'auth.errors.password_mismatch',
  });

export type ResetPasswordInput = z.infer<typeof resetPasswordSchema>;

/**
 * Lightweight password-strength scorer used by `PasswordStrengthIndicator`.
 *
 * Returns a tuple of:
 *   - score: 0..4 (0 = empty, 4 = meets every rule)
 *   - labelKey: i18n key for a human-readable strength label
 *   - matched: which individual rules pass (drives the checklist UI)
 */
export type PasswordStrength = {
  score: 0 | 1 | 2 | 3 | 4;
  labelKey: string;
  matched: {
    length: boolean;
    uppercase: boolean;
    lowercase: boolean;
    number: boolean;
    symbol: boolean;
  };
};

export function scorePassword(password: string): PasswordStrength {
  const matched = {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    number: /[0-9]/.test(password),
    symbol: /[^A-Za-z0-9]/.test(password),
  };
  // length is the gate — without it the password is automatically weak
  const positives = Object.values(matched).filter(Boolean).length;
  const rawScore = matched.length ? positives - 1 : 0; // 0..4
  const score = Math.max(0, Math.min(4, rawScore)) as 0 | 1 | 2 | 3 | 4;
  const labelKey =
    score === 0
      ? 'auth.password_strength.empty'
      : score === 1
        ? 'auth.password_strength.weak'
        : score === 2
          ? 'auth.password_strength.fair'
          : score === 3
            ? 'auth.password_strength.good'
            : 'auth.password_strength.strong';
  return { score, labelKey, matched };
}
