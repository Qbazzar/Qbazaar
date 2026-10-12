'use client';

/**
 * Global header search bar.
 *
 * - The text input is debounced (250ms) before firing the suggestions query.
 * - Submitting routes to `/search?q=...` (Enter or the submit button).
 * - Arrow keys move through the suggestions panel; Enter on a highlighted
 *   suggestion picks it instead of submitting the raw value.
 *
 * The panel closes on outside-click, blur, or Escape. We deliberately don't
 * remount the panel on every keystroke — TanStack Query handles the cache.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { CloseIcon, SearchIcon } from '@/components/design-system/icons';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import { useSearchSuggestionsQuery } from '@/lib/queries/search';
import { SuggestionsList } from './SuggestionsList';

interface Props {
  /** Optional className for the outer wrapper (e.g. layout width inside the header). */
  className?: string;
  /** Compact look — used inside the mobile overlay. Defaults to false. */
  compact?: boolean;
  /** Auto-focus the input on mount (mobile overlay). */
  autoFocus?: boolean;
  /** Called after a successful submit / suggestion pick — lets parents close the overlay. */
  onAfterSubmit?: () => void;
}

const DEBOUNCE_MS = 250;

export function SearchBar({
  className,
  compact = false,
  autoFocus = false,
  onAfterSubmit,
}: Props) {
  const router = useRouter();
  const searchParams = useSearchParams();

  // Seed the input from the current URL when the user is already on /search.
  const initialQuery = searchParams?.get('q') ?? '';
  const [value, setValue] = useState(initialQuery);
  const [debouncedValue, setDebouncedValue] = useState(initialQuery);
  const [open, setOpen] = useState(false);
  const [highlighted, setHighlighted] = useState(-1);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setDebouncedValue(value);
    }, DEBOUNCE_MS);
    return () => window.clearTimeout(handle);
  }, [value]);

  const suggestionsQuery = useSearchSuggestionsQuery(debouncedValue);
  const suggestions = useMemo(
    () => suggestionsQuery.data ?? [],
    [suggestionsQuery.data],
  );
  const showPanel = open && debouncedValue.trim().length >= 2;

  // Close on outside click.
  useEffect(() => {
    if (!open) return;
    const onClick = (event: MouseEvent) => {
      if (!containerRef.current) return;
      if (!containerRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  // Reset highlight whenever the suggestions list changes.
  useEffect(() => {
    setHighlighted(-1);
  }, [suggestions]);

  const goToSearch = useCallback(
    (q: string) => {
      const trimmed = q.trim();
      if (!trimmed) return;
      const params = new URLSearchParams();
      params.set('q', trimmed);
      router.push(`/search?${params.toString()}`);
      setOpen(false);
      onAfterSubmit?.();
    },
    [router, onAfterSubmit],
  );

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Escape') {
      setOpen(false);
      return;
    }
    if (event.key === 'ArrowDown') {
      if (!showPanel || suggestions.length === 0) return;
      event.preventDefault();
      setHighlighted((idx) => (idx + 1) % suggestions.length);
      return;
    }
    if (event.key === 'ArrowUp') {
      if (!showPanel || suggestions.length === 0) return;
      event.preventDefault();
      setHighlighted((idx) =>
        idx <= 0 ? suggestions.length - 1 : idx - 1,
      );
      return;
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      if (
        showPanel &&
        highlighted >= 0 &&
        highlighted < suggestions.length
      ) {
        const pick = suggestions[highlighted];
        setValue(pick.text);
        goToSearch(pick.text);
        return;
      }
      goToSearch(value);
    }
  };

  return (
    <form
      ref={containerRef as unknown as React.RefObject<HTMLFormElement>}
      role="search"
      onSubmit={(event) => {
        event.preventDefault();
        goToSearch(value);
      }}
      className={cn('relative w-full', className)}
    >
      <div
        className={cn(
          'border-ink-200 bg-card flex items-center gap-1 rounded-full border ps-3 pe-1',
          compact ? 'h-10' : 'h-11',
          'focus-within:border-coral focus-within:ring-coral/30 focus-within:ring-3 transition-colors',
        )}
      >
        <SearchIcon className="text-ink-500 size-4 shrink-0" aria-hidden />
        <input
          ref={inputRef}
          type="text"
          autoFocus={autoFocus}
          value={value}
          onChange={(event) => {
            setValue(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={t('search.placeholder', 'ابحث عن سيارة، شقة، هاتف…')}
          aria-label={t('search.placeholder', 'ابحث عن سيارة، شقة، هاتف…')}
          aria-autocomplete="list"
          aria-expanded={showPanel}
          className="text-ink-900 placeholder:text-ink-500 h-full min-w-0 flex-1 bg-transparent text-sm outline-none"
        />
        {value ? (
          <button
            type="button"
            aria-label={t('search.close', 'إغلاق')}
            onClick={() => {
              setValue('');
              setDebouncedValue('');
              inputRef.current?.focus();
            }}
            className="text-ink-500 hover:bg-cream-200 grid size-7 place-items-center rounded-full transition-colors"
          >
            <CloseIcon className="size-3.5" aria-hidden />
          </button>
        ) : null}
        <Button
          type="submit"
          size="sm"
          className="bg-coral hover:bg-coral/90 ms-0.5 rounded-full px-3 text-white"
        >
          {t('search.submit', 'بحث')}
        </Button>
      </div>

      {showPanel ? (
        <SuggestionsList
          suggestions={suggestions}
          highlightedIndex={highlighted}
          onHover={(index) => setHighlighted(index)}
          onPick={(text) => {
            setValue(text);
            goToSearch(text);
          }}
          isLoading={suggestionsQuery.isFetching && suggestions.length === 0}
          isEmpty={!suggestionsQuery.isFetching && suggestions.length === 0}
        />
      ) : null}
    </form>
  );
}
