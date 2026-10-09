'use client';

import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { switchLocale } from '@/components/i18n/switch-locale';
import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** The site's languages; the country code stands in for the reference's flag emoji. */
const LANGUAGES: { locale: Locale; code: string; name: string; country: string }[] = [
  { locale: 'en', code: 'EN', name: 'English', country: 'GB' },
  { locale: 'ar', code: 'AR', name: 'العربية', country: 'QA' },
];

/**
 * Language pill of the auth header (`.qb-lang` + `.qb-langpanel`, auth.js):
 * flag, code and caret; a click opens the "Choose your language" list.
 * Arrow keys move through the list, Escape, a click outside or Tab close it.
 */
export function AuthLanguagePill() {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const optionRefs = useRef<(HTMLButtonElement | null)[]>([]);
  const listId = useId();
  const titleId = `${listId}-title`;
  const current = getLocale();
  const currentIndex = Math.max(
    0,
    LANGUAGES.findIndex((language) => language.locale === current),
  );
  const active = LANGUAGES[currentIndex];

  useEffect(() => {
    if (!open) return;
    optionRefs.current[currentIndex]?.focus();
    const closeOnOutsidePress = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    return () => document.removeEventListener('pointerdown', closeOnOutsidePress);
  }, [open, currentIndex]);

  const closeToTrigger = () => {
    setOpen(false);
    triggerRef.current?.focus();
  };

  const choose = (locale: Locale) => {
    if (locale === current) closeToTrigger();
    else switchLocale(locale);
  };

  const onListKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const focused = optionRefs.current.findIndex((option) => option === document.activeElement);
    const last = LANGUAGES.length - 1;
    const moveTo = (index: number) => {
      event.preventDefault();
      optionRefs.current[index]?.focus();
    };
    if (event.key === 'ArrowDown') moveTo(focused >= last ? 0 : focused + 1);
    else if (event.key === 'ArrowUp') moveTo(focused <= 0 ? last : focused - 1);
    else if (event.key === 'Home') moveTo(0);
    else if (event.key === 'End') moveTo(last);
    else if (event.key === 'Tab') setOpen(false);
  };

  return (
    // The panel hangs from the header (the nearest positioned ancestor), 40 px from its end, as auth.js places it.
    <div
      ref={rootRef}
      onKeyDown={(event) => {
        if (open && event.key === 'Escape') closeToTrigger();
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-label={`${t('layout.language.label')}: ${active.name}`}
        onClick={() => setOpen((value) => !value)}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown' && !open) {
            event.preventDefault();
            setOpen(true);
          }
        }}
        className={cn(
          'inline-flex cursor-pointer items-center gap-2 rounded-qb-md border border-qb-line bg-qb-surface px-3.5 py-[9px] font-qb text-qb-caption font-medium text-qb-auth-lang-ink',
          focusRing,
        )}
      >
        <span aria-hidden="true" className="text-qb-tiny font-semibold">
          {active.country}
        </span>
        <span lang="en">{active.code}</span>
        <span aria-hidden="true" className="text-qb-auth-caret">
          ▾
        </span>
      </button>
      {open ? (
        <div
          id={listId}
          role="listbox"
          aria-labelledby={titleId}
          onKeyDown={onListKeyDown}
          className="absolute end-10 top-[calc(100%+10px)] z-50 w-[210px] rounded-[14px] border border-qb-line bg-qb-surface p-2 font-qb shadow-qb-popover"
        >
          <p
            id={titleId}
            className="px-2.5 py-1.5 text-[11px] font-semibold tracking-[0.06em] text-qb-auth-lang-caption uppercase"
          >
            {t('layout.language.title')}
          </p>
          {LANGUAGES.map((language, index) => {
            const selected = language.locale === current;
            return (
              <button
                key={language.locale}
                ref={(node) => {
                  optionRefs.current[index] = node;
                }}
                type="button"
                role="option"
                lang={language.locale}
                aria-selected={selected}
                tabIndex={selected ? 0 : -1}
                onClick={() => choose(language.locale)}
                className={cn(
                  'flex w-full cursor-pointer items-center gap-2.5 rounded-[9px] px-2.5 py-[9px] text-start text-qb-caption font-medium',
                  selected ? 'bg-qb-brand-soft text-qb-brand' : 'text-qb-ink-title hover:bg-qb-hover',
                  focusRing,
                  'focus-visible:-outline-offset-2',
                )}
              >
                <span aria-hidden="true" className="text-qb-tiny font-semibold">
                  {language.country}
                </span>
                <span>{language.name}</span>
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
