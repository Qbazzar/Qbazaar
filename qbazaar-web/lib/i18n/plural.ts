import { formatNumber, intlLocale } from './format';
import { getLocale, type Locale } from './locale';
import { t } from './messages';

const rulesByLocale = new Map<string, Intl.PluralRules>();

function pluralRules(locale: Locale): Intl.PluralRules {
  const tag = intlLocale(locale);
  let rules = rulesByLocale.get(tag);
  if (!rules) {
    rules = new Intl.PluralRules(tag);
    rulesByLocale.set(tag, rules);
  }
  return rules;
}

/**
 * `t()` for a counted phrase. The key holds one entry per CLDR plural category
 * of the language (`one` and `other` in English; `zero`, `one`, `two`, `few`,
 * `many` and `other` in Arabic), and `{count}` receives the formatted number.
 * A missing category falls back to `other`.
 */
export function tPlural(key: string, count: number, locale: Locale = getLocale()): string {
  const vars = { count: formatNumber(count, locale) };
  const category = pluralRules(locale).select(count);
  return t(`${key}.${category}`, vars, '') || t(`${key}.other`, vars);
}
