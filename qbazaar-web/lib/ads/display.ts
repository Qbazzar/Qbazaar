import { formatNumber, intlLocale } from '@/lib/i18n/format';
import { localized, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { formatRelativeTime } from '@/lib/utils';
import type { Ad, CategoryField } from '@/lib/api/types';

/** A 0-5 average with one decimal at most: "4.5", "4". */
export function formatRating(value: number, locale: Locale): string {
  return formatNumber(Math.round(value * 10) / 10, locale);
}

/** Spec numbers: grouped from five digits, so a year stays "2020" while mileage reads "126,000". */
function formatSpecNumber(value: number, locale: Locale): string {
  return new Intl.NumberFormat(intlLocale(locale), {
    maximumFractionDigits: 2,
    useGrouping: Math.abs(value) >= 10_000,
  }).format(value);
}

/**
 * "Apr 12, 2026" in the page language; empty for a missing or broken date.
 * Always on Qatar time, so the server and the browser print the same day.
 */
export function formatAdDate(iso: string | null | undefined, locale: Locale): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat(intlLocale(locale), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Qatar',
  }).format(date);
}

/** "30 minutes ago"; older than a month falls back to the date, year included. */
export function formatTimeAgo(iso: string | null | undefined, locale: Locale): string {
  return iso ? formatRelativeTime(iso, intlLocale(locale)) : '';
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
