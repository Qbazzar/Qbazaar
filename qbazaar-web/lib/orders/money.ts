import { intlLocale } from '@/lib/i18n/format';
import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';

/**
 * Money helpers for the order cycle. The API sends every amount as an exact
 * decimal string ("1999.99"); these helpers format and compare such strings
 * through BigInt cents, so no amount ever passes through a float.
 */

const DECIMAL_PATTERN = /^(-)?(\d+)(?:\.(\d{1,2}))?$/;

interface ParsedDecimal {
  negative: boolean;
  integer: string;
  fraction: string;
}

function parseDecimal(amount: string): ParsedDecimal | null {
  const match = DECIMAL_PATTERN.exec(amount.trim());
  if (!match) return null;
  return {
    negative: match[1] === '-',
    integer: match[2],
    fraction: (match[3] ?? '').padEnd(2, '0'),
  };
}

/** "1234.5" → "1,234.50", with Latin digits in both languages. Unparseable input is returned untouched. */
export function formatAmount(amount: string, locale: Locale = getLocale()): string {
  const parsed = parseDecimal(amount);
  if (!parsed) return amount;

  const lang = intlLocale(locale);
  const grouped = new Intl.NumberFormat(lang).format(BigInt(parsed.integer));
  const separator =
    new Intl.NumberFormat(lang, { minimumFractionDigits: 1 }).formatToParts(1.5).find((part) => part.type === 'decimal')
      ?.value ?? '.';
  const fraction = new Intl.NumberFormat(lang, { minimumIntegerDigits: 2, useGrouping: false }).format(
    Number(parsed.fraction),
  );
  const isZero = /^0+$/.test(parsed.integer) && parsed.fraction === '00';
  const sign =
    parsed.negative && !isZero
      ? (new Intl.NumberFormat(lang).formatToParts(-1).find((part) => part.type === 'minusSign')?.value ?? '-')
      : '';

  return `${sign}${grouped}${separator}${fraction}`;
}

/** Display label of a currency code ("ر.ق" in Arabic), falling back to the code itself. */
export function currencyLabel(currency: string): string {
  return t(`orders.money.${currency}`, currency);
}

/** "QAR 1,234.50" — the label first, as on the listing cards. */
export function formatMoney(amount: string, currency = 'QAR', locale: Locale = getLocale()): string {
  return `${currencyLabel(currency)} ${formatAmount(amount, locale)}`;
}

/** An ad's listed price, which the API sends as a JSON number: "QAR 1,550" (decimals only when present). */
export function formatListPrice(price: number, currency = 'QAR', locale: Locale = getLocale()): string {
  return `${currencyLabel(currency)} ${new Intl.NumberFormat(intlLocale(locale), { maximumFractionDigits: 2 }).format(price)}`;
}

/** A rate such as "5.00" as "5". Rates are not money, so a plain number is fine. */
export function formatRate(rate: string, locale: Locale = getLocale()): string {
  const value = Number(rate);
  if (!Number.isFinite(value)) return rate;
  return new Intl.NumberFormat(intlLocale(locale), { maximumFractionDigits: 2 }).format(value);
}

/** Exact cents of a decimal string, or null when it is not a 0–2 place decimal. */
export function toCents(amount: string): bigint | null {
  const parsed = parseDecimal(amount);
  if (!parsed) return null;
  const cents = BigInt(parsed.integer) * BigInt(100) + BigInt(parsed.fraction);
  return parsed.negative ? -cents : cents;
}

/** Cents back to the API's two-place decimal string. */
export function fromCents(cents: bigint): string {
  const negative = cents < BigInt(0);
  const absolute = negative ? -cents : cents;
  const integer = absolute / BigInt(100);
  const fraction = (absolute % BigInt(100)).toString().padStart(2, '0');
  return `${negative ? '-' : ''}${integer.toString()}.${fraction}`;
}

export function isPositiveAmount(amount: string): boolean {
  const cents = toCents(amount);
  return cents !== null && cents > BigInt(0);
}

/** -1, 0 or 1; unparseable amounts sort as zero. */
export function compareAmounts(a: string, b: string): number {
  const left = toCents(a) ?? BigInt(0);
  const right = toCents(b) ?? BigInt(0);
  if (left === right) return 0;
  return left < right ? -1 : 1;
}

export function minAmount(a: string, b: string): string {
  return compareAmounts(a, b) <= 0 ? a : b;
}

/** `amount` minus what is already `covered`, never below zero. */
export function remainingAmount(amount: string, covered: string): string {
  const left = (toCents(amount) ?? BigInt(0)) - (toCents(covered) ?? BigInt(0));
  return fromCents(left > BigInt(0) ? left : BigInt(0));
}

const DIGIT_OFFSETS: Array<[number, number]> = [
  [0x0660, 0x0669], // Arabic-Indic
  [0x06f0, 0x06f9], // Extended Arabic-Indic (Persian/Urdu keyboards)
];

/** Arabic-Indic digits and the Arabic decimal mark as their ASCII forms. */
export function toAsciiDigits(input: string): string {
  return Array.from(input)
    .map((char) => {
      const code = char.codePointAt(0) ?? 0;
      for (const [start, end] of DIGIT_OFFSETS) {
        if (code >= start && code <= end) return String(code - start);
      }
      return char === '٫' ? '.' : char;
    })
    .join('');
}

const GROUPED_INPUT = /^\d{1,3}(,\d{3})+(\.\d{1,2})?$/;
const DECIMAL_COMMA_INPUT = /^\d+,\d{1,2}$/;
const PLAIN_INPUT = /^\d+(\.\d{1,2})?$/;

/**
 * What the user typed into an amount field, as an API decimal string or null.
 * Accepts Arabic-Indic digits and the Arabic marks, commas that group
 * thousands ("1,500.50") and a single decimal comma ("1500,50"), which
 * comma-decimal keypads type. Anything ambiguous ("1.500,50") is refused
 * rather than guessed.
 */
export function normalizeAmountInput(input: string): string | null {
  let ascii = toAsciiDigits(input.trim()).replace(/[\s']/g, '').replace(/٬/g, ',');
  if (GROUPED_INPUT.test(ascii)) ascii = ascii.replace(/,/g, '');
  else if (DECIMAL_COMMA_INPUT.test(ascii)) ascii = ascii.replace(',', '.');

  if (!PLAIN_INPUT.test(ascii)) return null;
  const cents = toCents(ascii);
  return cents === null ? null : fromCents(cents);
}
