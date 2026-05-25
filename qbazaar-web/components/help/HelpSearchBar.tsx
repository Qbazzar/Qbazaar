'use client';

/**
 * Help center search input with debounced suggestions panel.
 *
 * - Local input state for snappy typing.
 * - 250ms debounce before pushing the value to `useHelpSearchQuery` so we
 *   don't blast the backend on every keystroke.
 * - "Search" button + Enter submit both route to `/help/search?q=…` so the
 *   results page becomes shareable.
 * - Suggestions panel only appears once the trimmed query reaches 2 chars
 *   and the input is focused; results link to the article detail page.
 */
import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Loader2Icon, SearchIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useHelpSearchQuery } from '@/lib/queries/help';
import { t } from '@/lib/i18n/messages';
import { localized } from '@/lib/i18n/locale';

interface Props {
  initialQuery?: string;
  /** When true the suggestions panel is suppressed (search results page). */
  hideSuggestions?: boolean;
}

export function HelpSearchBar({
  initialQuery = '',
  hideSuggestions = false,
}: Props) {
  const router = useRouter();
  const [value, setValue] = useState(initialQuery);
  const [debounced, setDebounced] = useState(initialQuery);
  const [focused, setFocused] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  // Debounce the value used to fetch suggestions.
  useEffect(() => {
    const handle = window.setTimeout(() => setDebounced(value), 250);
    return () => window.clearTimeout(handle);
  }, [value]);

  // Close the suggestions panel when clicking outside.
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (!wrapperRef.current) return;
      if (!wrapperRef.current.contains(e.target as Node)) setFocused(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  const trimmed = debounced.trim();
  const showSuggestions = !hideSuggestions && focused && trimmed.length >= 2;
  const { data: suggestions, isFetching } = useHelpSearchQuery(
    showSuggestions ? trimmed : '',
  );

  const submit = () => {
    const q = value.trim();
    if (q.length < 2) return;
    router.push(`/help/search?q=${encodeURIComponent(q)}`);
  };

  return (
    <div className="relative" ref={wrapperRef}>
      <div className="help-search">
        <div className="help-search__icon" aria-hidden>
          <SearchIcon className="size-[18px]" />
        </div>
        <input
          type="search"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          onFocus={() => setFocused(true)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              submit();
            }
          }}
          placeholder={t(
            'help.search_placeholder',
            'ابحث عن "كيفية النشر"، "الأمان"، …',
          )}
          aria-label={t('help.search_placeholder', 'بحث في مركز المساعدة')}
        />
        <Button
          type="button"
          onClick={submit}
          className="bg-coral hover:bg-coral/90 h-9 rounded-full px-4 text-white"
        >
          {t('help.search_submit', 'بحث')}
        </Button>
      </div>

      {showSuggestions ? (
        <div className="help-suggest" role="listbox">
          {isFetching && !suggestions ? (
            <div className="help-suggest__empty">
              <Loader2Icon
                className="mx-auto size-4 animate-spin"
                aria-hidden
              />
            </div>
          ) : suggestions && suggestions.length > 0 ? (
            suggestions.slice(0, 8).map((article) => {
              const excerpt = localized(article.excerpt);
              return (
                <Link
                  key={article.id}
                  href={`/help/articles/${article.slug}`}
                  className="help-suggest__item"
                  onClick={() => setFocused(false)}
                >
                  <span className="font-medium">{localized(article.title)}</span>
                  {excerpt ? (
                    <span className="help-suggest__excerpt">{excerpt}</span>
                  ) : null}
                </Link>
              );
            })
          ) : (
            <div className="help-suggest__empty">
              {t('help.no_results', 'لا توجد نتائج')}
            </div>
          )}
        </div>
      ) : null}
    </div>
  );
}
