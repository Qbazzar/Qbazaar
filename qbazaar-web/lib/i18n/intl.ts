import { formatNumber, intlLocale } from './format';
import { getLocale } from './locale';
import { t } from './messages';

/**
 * `t()` for a counted phrase. The key holds one entry per CLDR plural category
 * of the language (`one` and `other` in English; `zero`, `one`, `two`, `few`,
 * `many` and `other` in Arabic), and `{count}` receives the formatted number.
 * A missing category falls back to `other`.
 */
export function tPlural(key: string, count: number): string {
  const locale = getLocale();
  const vars = { count: formatNumber(count, locale) };
  const category = new Intl.PluralRules(intlLocale(locale)).select(count);

  return t(`${key}.${category}`, vars, '') || t(`${key}.other`, vars);
}
