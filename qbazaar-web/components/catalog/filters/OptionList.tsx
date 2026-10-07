import { formatNumber } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';

export interface FilterOption {
  value: string;
  label: string;
  /** Matching ads, from the search facets; zero is not shown. */
  count?: number;
}

interface OptionListProps {
  /** Shared `name` of the radio inputs; unique per panel. */
  name: string;
  labelledBy: string;
  options: FilterOption[];
  value: string | null;
  onChange: (value: string | null) => void;
  /** Label of the first option, which clears the filter. */
  anyLabel: string;
}

const radio = [
  'size-[18px] shrink-0 cursor-pointer appearance-none rounded-full border border-qb-ink-disabled bg-qb-surface transition-[border-width,border-color]',
  'checked:border-[5px] checked:border-qb-brand motion-reduce:transition-none',
  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-qb-brand-active',
].join(' ');

/** Radio rows of a filter group ("Available (7,592,369)"), with an "any" row to clear it. */
export function OptionList({ name, labelledBy, options, value, onChange, anyLabel }: OptionListProps) {
  const locale = getLocale();
  const rows: FilterOption[] = [{ value: '', label: anyLabel }, ...options];

  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-col gap-3">
      {rows.map((option) => {
        const checked = (value ?? '') === option.value;
        return (
          <label key={option.value || 'any'} className="flex cursor-pointer items-center gap-2.5 font-qb text-qb-body text-qb-ink-subtle">
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={checked}
              onChange={() => onChange(option.value || null)}
              className={radio}
            />
            <span className={cn('min-w-0', checked && 'text-qb-ink-body')}>
              {option.label}
              {option.count ? <span className="tabular-nums"> ({formatNumber(option.count, locale)})</span> : null}
            </span>
          </label>
        );
      })}
    </div>
  );
}
