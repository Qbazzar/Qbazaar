import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import {
  compareAmounts,
  formatAmount,
  formatMoney,
  fromCents,
  isPositiveAmount,
  minAmount,
  normalizeAmountInput,
  remainingAmount,
  toCents,
} from './money';

describe('formatAmount', () => {
  it('groups the integer part and keeps two decimals', () => {
    expect(formatAmount('1234567.5', 'en')).toBe('1,234,567.50');
    expect(formatAmount('0.05', 'en')).toBe('0.05');
    expect(formatAmount('30400.00', 'en')).toBe('30,400.00');
  });

  it('keeps amounts beyond float precision exact', () => {
    expect(formatAmount('9007199254740993.99', 'en')).toBe('9,007,199,254,740,993.99');
  });

  it('writes Latin digits in Arabic too, as the rest of the site does', () => {
    expect(formatAmount('1550.00', 'ar')).toBe('1,550.00');
  });

  it('signs negative amounts but never a zero', () => {
    expect(formatAmount('-12.30', 'en')).toBe('-12.30');
    expect(formatAmount('-0.00', 'en')).toBe('0.00');
  });

  it('returns unparseable input untouched', () => {
    expect(formatAmount('abc', 'en')).toBe('abc');
  });
});

describe('formatMoney', () => {
  beforeEach(() => setClientLocale('en'));

  it('puts the currency label first', () => {
    expect(formatMoney('70', 'QAR', 'en')).toBe('QAR 70.00');
  });

  it('labels the riyal in Arabic', () => {
    setClientLocale('ar');
    expect(formatMoney('70', 'QAR', 'ar')).toBe('ر.ق 70.00');
  });
});

describe('cents helpers', () => {
  it('round-trips decimal strings through cents', () => {
    expect(toCents('1500.5')).toBe(BigInt(150050));
    expect(toCents('-2.01')).toBe(BigInt(-201));
    expect(toCents('1.234')).toBeNull();
    expect(fromCents(BigInt(150050))).toBe('1500.50');
    expect(fromCents(BigInt(-5))).toBe('-0.05');
  });

  it('compares exactly', () => {
    expect(compareAmounts('0.10', '0.1')).toBe(0);
    expect(compareAmounts('99.99', '100')).toBe(-1);
    expect(compareAmounts('277.50', '0.00')).toBe(1);
    expect(minAmount('277.50', '100.00')).toBe('100.00');
    expect(isPositiveAmount('0.00')).toBe(false);
    expect(isPositiveAmount('0.01')).toBe(true);
  });

  it('subtracts what is covered without going below zero', () => {
    expect(remainingAmount('277.50', '100.00')).toBe('177.50');
    expect(remainingAmount('100.00', '277.50')).toBe('0.00');
    expect(remainingAmount('0.30', '0.10')).toBe('0.20');
  });
});

describe('normalizeAmountInput', () => {
  it('accepts plain and grouped input', () => {
    expect(normalizeAmountInput('1500')).toBe('1500.00');
    expect(normalizeAmountInput(' 1,500.5 ')).toBe('1500.50');
    expect(normalizeAmountInput('1,500')).toBe('1500.00');
    expect(normalizeAmountInput('1 500.50')).toBe('1500.50');
  });

  it('reads a single comma before one or two digits as the decimal mark', () => {
    expect(normalizeAmountInput('1500,50')).toBe('1500.50');
    expect(normalizeAmountInput('1,5')).toBe('1.50');
    expect(normalizeAmountInput('1 500,5')).toBe('1500.50');
  });

  it('accepts Arabic digits and separators', () => {
    expect(normalizeAmountInput('١٬٥٠٠٫٥')).toBe('1500.50');
    expect(normalizeAmountInput('۲۵۰')).toBe('250.00');
  });

  it('refuses ambiguous or malformed grouping instead of guessing', () => {
    expect(normalizeAmountInput('1.500,50')).toBeNull();
    expect(normalizeAmountInput('1,50,000')).toBeNull();
    expect(normalizeAmountInput('15,00,0')).toBeNull();
  });

  it('rejects anything that is not a two-place decimal', () => {
    expect(normalizeAmountInput('')).toBeNull();
    expect(normalizeAmountInput('12.345')).toBeNull();
    expect(normalizeAmountInput('-5')).toBeNull();
    expect(normalizeAmountInput('1e3')).toBeNull();
  });
});
