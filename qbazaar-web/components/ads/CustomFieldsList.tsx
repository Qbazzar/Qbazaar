/**
 * Renders an ad's `custom_fields` as a labelled key/value grid.
 *
 * The labels are looked up from the parent category's `custom_fields`
 * definition (bilingual). Values that aren't defined on the category fall
 * through to their raw key — better than dropping them on the floor.
 */
import { localized, getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';
import type { Category } from '@/lib/api/types';

interface Props {
  values: Record<string, unknown>;
  category?: Category | null;
  className?: string;
}

function formatValue(value: unknown): string {
  if (value == null || value === '') return '—';
  if (typeof value === 'boolean') return value ? '✓' : '—';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'string') return value;
  return JSON.stringify(value);
}

export function CustomFieldsList({ values, category, className }: Props) {
  const entries = Object.entries(values ?? {});
  if (entries.length === 0) return null;

  const locale = getLocale();
  const definitions = category?.custom_fields ?? [];

  return (
    <dl
      className={cn(
        'grid grid-cols-1 gap-x-6 gap-y-3 rounded-xl border border-ink-200 bg-card p-4 sm:grid-cols-2',
        className,
      )}
    >
      {entries.map(([key, value]) => {
        const def = definitions.find((d) => d.key === key);
        const label = def ? localized(def.label, locale) : key;
        return (
          <div key={key} className="flex items-baseline justify-between gap-3">
            <dt className="text-ink-500 text-[11px] font-bold uppercase tracking-wider">
              {label}
            </dt>
            <dd className="text-ink-900 text-sm font-medium">{formatValue(value)}</dd>
          </div>
        );
      })}
    </dl>
  );
}
