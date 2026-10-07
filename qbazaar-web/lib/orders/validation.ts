import { t } from '@/lib/i18n/messages';

import { compareAmounts, formatMoney, normalizeAmountInput, toAsciiDigits } from './money';

/** Bounds the API applies (qbazaar.php): notes, reasons and amounts. */
export const LIMITS = {
  noteMax: 280,
  cancelReasonMax: 500,
  disputeReasonMin: 10,
  disputeReasonMax: 1000,
  offerMin: '1.00',
  offerMax: '9999999.00',
  quantityMax: 999,
  bankReferenceMax: 100,
  transferReferenceMax: 64,
  holderMin: 2,
  holderMax: 120,
  bankNameMax: 120,
  proofMaxBytes: 5 * 1024 * 1024,
} as const;

const MARKUP = /[<>]/;

/** Free text the API stores for the other side to read: limited length, no markup. */
export function validateText(text: string, { max, min = 0, required = false }: { max: number; min?: number; required?: boolean }): string | null {
  const value = text.trim();
  if (!value) return required ? t('orders.validation.required') : null;
  if (MARKUP.test(value)) return t('orders.validation.no_markup');
  if (value.length < min) return t('orders.validation.min_chars', { min });
  if (value.length > max) return t('orders.validation.max_chars', { max });
  return null;
}

export type AmountResult = { amount: string; error: null } | { amount: null; error: string };

/** A typed amount as an API decimal string within [min, max], or the message to show. */
export function validateAmount(input: string, { min, max, currency = 'QAR' }: { min: string; max: string; currency?: string }): AmountResult {
  if (!input.trim()) return { amount: null, error: t('orders.validation.amount_required') };
  const amount = normalizeAmountInput(input);
  if (!amount) return { amount: null, error: t('orders.validation.amount_format') };
  if (compareAmounts(amount, min) < 0) return { amount: null, error: t('orders.validation.amount_min', { min: formatMoney(min, currency) }) };
  if (compareAmounts(amount, max) > 0) return { amount: null, error: t('orders.validation.amount_max', { max: formatMoney(max, currency) }) };
  return { amount, error: null };
}

export function validateQuantity(input: string, max: number): { quantity: number; error: null } | { quantity: null; error: string } {
  const digits = toAsciiDigits(input.trim());
  const quantity = /^\d+$/.test(digits) ? Number(digits) : Number.NaN;
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > max) {
    return { quantity: null, error: t('orders.validation.quantity', { max }) };
  }
  return { quantity, error: null };
}
