'use client';

/**
 * Help center search: the search bar of the all-categories screen (185:6576)
 * with debounced article suggestions under it. Submitting opens
 * `/help/search?q=…` so results stay shareable.
 */
import { useEffect, useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Search } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { focusRing } from '@/components/design-system/focus-ring';
import { MIN_HELP_QUERY_LENGTH, useHelpSearchQuery } from '@/lib/queries/help';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { cn } from '@/lib/utils';

const SUGGESTION_LIMIT = 8;
const DEBOUNCE_MS = 250;

interface HelpSearchBarProps {
  initialQuery?: string;
  /** The results page lists every match itself, so it turns the suggestions off. */
  hideSuggestions?: boolean;
  className?: string;
}

export function HelpSearchBar({ initialQuery = '', hideSuggestions = false, className }: HelpSearchBarProps) {
  const router = useRouter();
  const inputId = useId();
  const suggestionsId = useId();
  const wrapperRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [value, setValue] = useState(initialQuery);
  const [debounced, setDebounced] = useState(initialQuery);
  const [open, setOpen] = useState(false);
  const [syncedQuery, setSyncedQuery] = useState(initialQuery);

  // Back/forward on the results page changes the query under a mounted field.
  if (initialQuery !== syncedQuery) {
    setSyncedQuery(initialQuery);
    setValue(initialQuery);
  }

  useEffect(() => {
    const handle = window.setTimeout(() => setDebounced(value), DEBOUNCE_MS);
    return () => window.clearTimeout(handle);
  }, [value]);

  // Closing on mousedown rather than blur keeps a clicked suggestion mounted
  // until its click lands (Safari does not focus links on click).
  useEffect(() => {
    if (!open) return;
    const closeOutside = (event: MouseEvent) => {
      if (!wrapperRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', closeOutside);
    return () => document.removeEventListener('mousedown', closeOutside);
  }, [open]);

  const query = debounced.trim();
  const showSuggestions = !hideSuggestions && open && query.length >= MIN_HELP_QUERY_LENGTH;
  const { data: suggestions, isFetching } = useHelpSearchQuery(showSuggestions ? query : '');
  const visibleSuggestions = (suggestions ?? []).slice(0, SUGGESTION_LIMIT);
  const loadingSuggestions = isFetching && !suggestions;

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const term = value.trim();
    if (term.length < MIN_HELP_QUERY_LENGTH) return;
    setOpen(false);
    router.push(`/help/search?q=${encodeURIComponent(term)}`);
  };

  // While the list shows, Escape only closes it (the browser would also clear a search field), and
  // focus returns to the field rather than falling to the page when it sat on a suggestion.
  const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key !== 'Escape' || !showSuggestions) return;
    event.preventDefault();
    event.stopPropagation();
    inputRef.current?.focus();
    setOpen(false);
  };

  return (
    <div
      ref={wrapperRef}
      className={cn('relative w-full font-qb', className)}
      onKeyDown={closeOnEscape}
      onBlur={(event) => {
        // Tabbing out of the bar and its suggestions closes them.
        if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
      }}
    >
      <form
        role="search"
        aria-label={t('help.search_label')}
        onSubmit={submit}
        className={cn(
          'flex h-11 items-center gap-2.5 rounded-qb-xl border border-qb-line bg-qb-surface ps-4 pe-1.5 shadow-qb-card transition-colors',
          'focus-within:border-qb-brand focus-within:ring-2 focus-within:ring-qb-brand/20',
          'qb-tablet:h-14 qb-tablet:gap-3 qb-tablet:pe-2 qb-desktop:ps-[22px]',
        )}
      >
        <Icon icon={Search} className="size-5 text-qb-ink-muted qb-tablet:size-6" />
        <label htmlFor={inputId} className="sr-only">
          {t('help.search_label')}
        </label>
        <input
          ref={inputRef}
          id={inputId}
          type="search"
          value={value}
          onChange={(event) => {
            setValue(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          placeholder={t('help.search_placeholder')}
          autoComplete="off"
          enterKeyHint="search"
          aria-controls={showSuggestions ? suggestionsId : undefined}
          className="h-full min-w-0 flex-1 bg-transparent text-qb-body text-qb-ink outline-none placeholder:text-qb-caption placeholder:text-qb-placeholder qb-tablet:placeholder:text-qb-body qb-desktop:text-qb-h5 qb-desktop:placeholder:text-qb-h5"
        />
        <button
          type="submit"
          className={cn(buttonVariants({ size: 'sm' }), 'hidden px-6 text-qb-body qb-tablet:inline-flex')}
        >
          {t('help.search_submit')}
        </button>
      </form>

      <p role="status" className="sr-only">
        {showSuggestions && !loadingSuggestions ? tPlural('help.result_count', visibleSuggestions.length) : ''}
      </p>

      {showSuggestions ? (
        <div
          id={suggestionsId}
          className="absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-qb-xl border border-qb-line bg-qb-surface py-2 text-start shadow-qb-popover"
        >
          {loadingSuggestions ? (
            <p aria-hidden="true" className="px-5 py-3 text-qb-caption text-qb-ink-subtle">
              {t('common.loading')}
            </p>
          ) : visibleSuggestions.length > 0 ? (
            <ul aria-label={t('help.suggestions_label')}>
              {visibleSuggestions.map((article) => {
                const excerpt = localized(article.excerpt);
                return (
                  <li key={article.id}>
                    <Link
                      href={`/help/articles/${article.slug}`}
                      onClick={() => setOpen(false)}
                      className={cn('block px-5 py-3 hover:bg-qb-hover', focusRing, 'focus-visible:-outline-offset-2')}
                    >
                      <span className="block text-qb-body font-medium text-qb-ink-body">{localized(article.title)}</span>
                      {excerpt ? (
                        <span className="mt-0.5 line-clamp-1 block text-qb-caption text-qb-ink-muted">{excerpt}</span>
                      ) : null}
                    </Link>
                  </li>
                );
              })}
            </ul>
          ) : (
            <p aria-hidden="true" className="px-5 py-3 text-qb-caption text-qb-ink-subtle">
              {t('help.no_results')}
            </p>
          )}
        </div>
      ) : null}
    </div>
  );
}
