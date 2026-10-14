'use client';

/**
 * Floating suggestions list rendered under the search input.
 *
 * Kept presentational so the parent `SearchBar` owns all keyboard + selection
 * state (highlighted index, Enter handling). The list itself just renders the
 * rows + emits hover/click events.
 */
import { SearchIcon } from '@/components/design-system/icons';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { SearchSuggestion } from '@/lib/api/types';

interface Props {
  suggestions: SearchSuggestion[];
  highlightedIndex: number;
  onHover: (index: number) => void;
  onPick: (text: string) => void;
  isLoading?: boolean;
  isEmpty?: boolean;
  className?: string;
}

export function SuggestionsList({
  suggestions,
  highlightedIndex,
  onHover,
  onPick,
  isLoading,
  isEmpty,
  className,
}: Props) {
  return (
    <div
      role="listbox"
      className={cn(
        'border-ink-200 bg-card absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border shadow-lg',
        className,
      )}
    >
      {isLoading ? (
        <p className="text-ink-500 px-3 py-2 text-sm">
          {t('search.results_loading', 'جاري البحث…')}
        </p>
      ) : isEmpty ? (
        <p className="text-ink-500 px-3 py-2 text-sm">
          {t('search.suggestions_empty', 'لا توجد اقتراحات')}
        </p>
      ) : (
        <ul className="py-1">
          {suggestions.map((s, index) => {
            const active = index === highlightedIndex;
            return (
              <li key={`${s.text}-${index}`} role="option" aria-selected={active}>
                <button
                  type="button"
                  onMouseEnter={() => onHover(index)}
                  onMouseDown={(e) => {
                    // Prevent the input from losing focus before the click handler runs.
                    e.preventDefault();
                  }}
                  onClick={() => onPick(s.text)}
                  className={cn(
                    'text-ink-900 flex w-full items-center gap-2 px-3 py-2 text-start text-sm transition-colors',
                    active ? 'bg-coral/10 text-terracotta' : 'hover:bg-cream-200',
                  )}
                >
                  <SearchIcon className="text-ink-500 size-3.5 shrink-0" aria-hidden />
                  <span className="truncate">{s.text}</span>
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
