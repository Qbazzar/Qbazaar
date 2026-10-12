'use client';

import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { menuEnter } from '@/components/design-system/menu-enter';
import { useHoverOpen } from '@/components/design-system/use-hover-open';
import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { switchLocale } from './switch-locale';

/**
 * The site's languages, each named in itself. The desktop panel marks them
 * with the reference's flags, the phone popup with its country codes.
 */
const LANGUAGES: { locale: Locale; name: string; flag: string; country: string }[] = [
  { locale: 'en', name: 'English', flag: '🇬🇧', country: 'GB' },
  { locale: 'ar', name: 'العربية', flag: '🇸🇦', country: 'QA' },
];

/**
 * The desktop panel is the reference header's (app.js): a 250 px card, 10 px
 * under the globe, its rows `.qb-btn`s with the chosen one tinted. Its
 * `before` fills that gap, so the pointer can reach the card without leaving
 * the menu. The phone and tablet popup of 741:39398 / 736:54497 is
 * Qbazaar-front's `.qb-langpop` (mobilemenu.js), 6 px under the icon, its
 * rows a code, the bare name and the radio pushed to the end.
 */
const VARIANTS = {
  desktop: {
    root: 'relative',
    panel: cn(
      'end-0 mt-2.5 w-[250px] rounded-[14px] p-2 shadow-qb-menu before:absolute before:inset-x-0 before:-top-2.5 before:h-2.5',
      menuEnter,
    ),
    title: 'px-3 pt-2.5 pb-3 text-qb-body',
    row: (selected: boolean) =>
      cn('qb-motion gap-3 rounded-[9px] px-3 py-2.5 leading-[1.15] hover:brightness-[.96]', selected && 'bg-qb-brand-soft'),
    label: (language: (typeof LANGUAGES)[number]) => <span className="flex-1 text-[15px] text-qb-ink-title">{language.name}</span>,
    marker: (language: (typeof LANGUAGES)[number]) => (
      <span aria-hidden="true" className="w-[22px] text-center text-[18px] leading-none">
        {language.flag}
      </span>
    ),
    radio: (selected: boolean) =>
      cn(
        'flex size-[18px] shrink-0 items-center justify-center rounded-full border-2',
        selected ? "border-qb-brand after:size-[9px] after:rounded-full after:bg-qb-brand after:content-['']" : 'border-qb-check-ring',
      ),
  },
  phone: {
    root: '',
    panel: 'end-0 top-[calc(100%+6px)] w-[262px] rounded-qb-xl pt-1.5 pb-2 shadow-qb-langpop',
    title: 'border-b border-qb-menu-divider px-[18px] pt-3 pb-2.5 text-qb-body',
    row: () => 'gap-2.5 px-[18px] py-[11px] text-qb-body-sm hover:bg-qb-hover',
    label: (language: (typeof LANGUAGES)[number]) => language.name,
    // The code is Latin in the Arabic row too, so it keeps its tracking.
    marker: (language: (typeof LANGUAGES)[number]) => (
      <span aria-hidden="true" lang="en" className="min-w-5 text-qb-tiny font-medium tracking-[0.04em] text-qb-ink-subtle">
        {language.country}
      </span>
    ),
    radio: (selected: boolean) =>
      cn('ms-auto size-[18px] shrink-0 rounded-full', selected ? 'border-[5px] border-qb-brand' : 'border-[1.5px] border-qb-check-ring'),
  },
} as const;

interface LanguageMenuProps {
  /** Classes of the trigger button (the header's icon or icon square). */
  className: string;
  /** The trigger's content, usually the globe icon. */
  children: ReactNode;
  /**
   * `desktop` opens the desktop card on hover or click, under the button;
   * `phone` opens the phone popup on click, under the nearest positioned
   * ancestor's end edge, for a button too close to the start edge on narrow
   * phones.
   */
  variant?: keyof typeof VARIANTS;
}

/**
 * "Choose your language" panel of the header globe. Picking the other
 * language stores it and reloads; Escape, a click outside or tabbing away
 * closes it, and so does the mouse leaving the desktop menu.
 */
export function LanguageMenu({ className, children, variant = 'desktop' }: LanguageMenuProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const titleId = `${panelId}-title`;
  const current = getLocale();
  const style = VARIANTS[variant];
  const { takeHoverOpen, hoverHandlers } = useHoverOpen(setOpen);

  useEffect(() => {
    if (!open) return;
    const closeOnOutsidePress = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    return () => document.removeEventListener('pointerdown', closeOnOutsidePress);
  }, [open]);

  const closeToTrigger = () => {
    setOpen(false);
    triggerRef.current?.focus();
  };

  const toggle = () => {
    // A click on the globe the mouse already opened keeps the panel open.
    if (takeHoverOpen() && open) return;
    setOpen((value) => !value);
  };

  return (
    <div
      ref={rootRef}
      className={cn('flex', style.root)}
      {...(variant === 'desktop' ? hoverHandlers : {})}
      onKeyDown={(event) => {
        if (open && event.key === 'Escape') closeToTrigger();
      }}
      onBlur={(event) => {
        const next = event.relatedTarget as Node | null;
        if (next && !rootRef.current?.contains(next)) setOpen(false);
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-label={t('layout.language.label', 'اللغة')}
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        onClick={toggle}
        className={className}
      >
        {children}
      </button>
      {open ? (
        <div
          id={panelId}
          role="group"
          aria-labelledby={titleId}
          className={cn('absolute top-full z-50 border border-qb-line bg-qb-surface font-qb text-qb-ink', style.panel)}
        >
          <p id={titleId} className={cn('font-semibold', style.title)}>
            {t('layout.language.title', 'اختر لغتك')}
          </p>
          <ul>
            {LANGUAGES.map((language) => {
              const selected = language.locale === current;
              return (
                <li key={language.locale}>
                  <button
                    type="button"
                    lang={language.locale}
                    aria-current={selected || undefined}
                    onClick={selected ? closeToTrigger : () => switchLocale(language.locale)}
                    className={cn(
                      'flex w-full cursor-pointer items-center text-start',
                      style.row(selected),
                      focusRing,
                      'focus-visible:-outline-offset-2',
                    )}
                  >
                    {style.marker(language)}
                    {style.label(language)}
                    <span aria-hidden="true" className={style.radio(selected)} />
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
