import { formatRelativeTime } from '@/lib/utils';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { Ad, AdSummary, CategoryField } from '@/lib/api/types';

/** Arabic pages show Arabic-Indic digits, as the rest of the app does. */
export function numberLocale(locale: Locale): string {
  return locale === 'ar' ? 'ar-EG' : 'en-US';
}

export function formatCount(value: number, locale: Locale): string {
  return new Intl.NumberFormat(numberLocale(locale), { maximumFractionDigits: 2 }).format(value);
}

/** `t()` for a count: English needs the singular `{key}_one` for 1; other languages share one form. */
export function tCount(key: string, count: number, locale: Locale, vars: Record<string, string | number> = {}): string {
  const singular = count === 1 && locale === 'en';
  return t(singular ? `${key}_one` : key, { ...vars, count: formatCount(count, locale) });
}

/** Spec numbers: grouped from five digits, so a year stays "2020" while mileage reads "126,000". */
function formatSpecNumber(value: number, locale: Locale): string {
  return new Intl.NumberFormat(numberLocale(locale), {
    maximumFractionDigits: 2,
    useGrouping: Math.abs(value) >= 10_000,
  }).format(value);
}

/** "QAR 285,000", "Free" or "Contact for price". */
export function formatAdPriceLabel(ad: Pick<AdSummary, 'price' | 'price_type'>, locale: Locale): string {
  if (ad.price_type === 'free') return t('ads.price.free');
  if (ad.price_type === 'contact' || ad.price == null) return t('ads.price.contact');
  return t('ads.price.amount', { amount: formatCount(ad.price, locale) });
}

/**
 * "12 Apr 2026" in the page language; empty for a missing or broken date.
 * Always on Qatar time, so the server and the browser print the same day.
 */
export function formatAdDate(iso: string | null | undefined, locale: Locale): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat(numberLocale(locale), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Qatar',
  }).format(date);
}

/** "30 minutes ago"; older than a month falls back to the date. */
export function formatAdAge(iso: string | null | undefined, locale: Locale): string {
  return iso ? formatRelativeTime(iso, numberLocale(locale)) : '';
}

/** Readable name from a slug ("al-wakra-center" -> "Al Wakra Center"); list cards only carry the slug. */
export function labelFromSlug(slug: string | null | undefined): string {
  if (!slug) return '';
  return slug
    .split(/[-_]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/** "semi_furnished" -> "Semi furnished", for keys and for options without a label. */
function humanizeOption(value: string): string {
  const text = value.replace(/[_-]+/g, ' ').trim();
  return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Ads store the raw option value; the category gives its label in the request language. */
function optionLabel(field: CategoryField, value: string): string {
  return field.options_labeled?.find((option) => option.value === value)?.label || humanizeOption(value);
}

export interface AdSpec {
  key: string;
  label: string;
  value: string;
}

export interface AdSpecSheet {
  /** "Technical Data": condition first, then the category fields in their defined order. */
  specs: AdSpec[];
  /** "Features & Extras": labels of the yes/no fields that are set. */
  features: string[];
}

function specValue(field: CategoryField | undefined, value: unknown, locale: Locale): string | null {
  if (value == null || value === '') return null;
  if (Array.isArray(value)) {
    const parts = value.map((item) => specValue(field, item, locale)).filter(Boolean);
    return parts.length ? parts.join(locale === 'ar' ? '، ' : ', ') : null;
  }
  if (typeof value === 'number') return formatSpecNumber(value, locale);
  const text = String(value);
  if (field?.type === 'date') return formatAdDate(text, locale) || text;
  if (field?.type === 'select') return optionLabel(field, text);
  return text;
}

/**
 * Splits an ad's custom fields into the spec table and the feature list,
 * labelled from the category's field definitions. Values the category no
 * longer defines still show, under a readable form of their key.
 */
export function buildAdSpecSheet(ad: Pick<Ad, 'condition' | 'custom_fields' | 'category'>, locale: Locale): AdSpecSheet {
  const specs: AdSpec[] = [];
  const features: string[] = [];

  if (ad.condition) {
    specs.push({ key: 'condition', label: t('ads.detail.condition'), value: t(`ads.condition.${ad.condition}`) });
  }

  const values = ad.custom_fields ?? {};
  const definitions = ad.category?.custom_fields ?? [];
  const definedKeys = new Set(definitions.map((field) => field.key));
  const orderedKeys = [
    ...definitions.map((field) => field.key).filter((key) => key in values),
    ...Object.keys(values).filter((key) => !definedKeys.has(key)),
  ];

  for (const key of orderedKeys) {
    const field = definitions.find((definition) => definition.key === key);
    const label = field ? localized(field.label, locale) || humanizeOption(key) : humanizeOption(key);
    const value = values[key];

    if (field?.type === 'boolean' || typeof value === 'boolean') {
      if (value === true) features.push(label);
      continue;
    }

    const text = specValue(field, value, locale);
    if (text) specs.push({ key, label, value: text });
  }

  return { specs, features };
}
