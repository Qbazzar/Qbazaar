/**
 * Zod schemas mirroring the contract in qbazaar-contracts/openapi/v1.yaml.
 *
 * Keep these in lock-step with the backend validation rules — the backend is
 * the source of truth, but the client validates first to avoid round-trips.
 *
 * Reference: openapi/v1.yaml → components.schemas.{RegisterRequest,LoginRequest,RefreshRequest}
 */
import { z } from 'zod';
import { qatarPhoneRegex } from './phone';

// Re-exported so existing imports keep working; the regex itself lives in a
// zod-free module so the app-wide bundle does not pull zod in.
export { qatarPhoneRegex };

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
