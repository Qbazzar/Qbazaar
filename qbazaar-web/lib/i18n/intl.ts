import { getLocale, type Locale } from './locale';
import { t } from './messages';

/** Tag for `Intl` formatters: Arabic-Indic digits in Arabic, as the price and count labels use. */
export function intlLocale(locale: Locale = getLocale()): string {
  return locale === 'ar' ? 'ar-EG' : 'en-US';
}

/**
 * `t()` for a counted phrase. The key holds one entry per CLDR plural category
 * of the language (`one` and `other` in English; `zero`, `one`, `two`, `few`,
 * `many` and `other` in Arabic), and `{count}` receives the localized number.
 * A missing category falls back to `other`.
 */
export function tPlural(key: string, count: number): string {
  const locale = intlLocale();
  const vars = { count: new Intl.NumberFormat(locale).format(count) };
  const category = new Intl.PluralRules(locale).select(count);

  return t(`${key}.${category}`, vars, '') || t(`${key}.other`, vars);
}
