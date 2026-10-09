'use client';

import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { menuEnter } from '@/components/design-system/menu-enter';
import { useHoverOpen } from '@/components/design-system/use-hover-open';
import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { switchLocale } from './switch-locale';

/** The site's languages, each named in itself; the country code stands in for the reference's flag. */
const LANGUAGES: { locale: Locale; name: string; country: string }[] = [
  { locale: 'en', name: 'English', country: 'GB' },
  { locale: 'ar', name: 'العربية', country: 'QA' },
];

/**
 * The desktop panel of 728:44663 (728:45875): a 303 px card with a 20 px
 * title, hanging from the header's lower edge past the globe's end. The phone
 * and tablet popup of 741:39398 / 736:54497 is Qbazaar-front's `.qb-langpop`.
 * The desktop card's `before` fills the gap under the globe, so the pointer
 * can reach the card without leaving the menu.
 */
const VARIANTS = {
  desktop: {
    root: 'relative',
    panel: cn(
      '-end-[51px] mt-7 w-[303px] rounded-qb-2xl pb-[7px] shadow-qb-panel before:absolute before:inset-x-0 before:-top-7 before:h-7',
      menuEnter,
    ),
    title: 'px-6 pt-6 pb-4 text-qb-h5 leading-[30px]',
    list: 'pt-2',
    row: 'gap-2 px-6 py-2 text-qb-body font-medium text-qb-ink-secondary',
    radio: (selected: boolean) => cn('size-[15px] border', selected ? 'border-qb-brand bg-qb-brand bg-clip-content p-[2px]' : 'border-qb-radio'),
  },
  phone: {
    root: '',
    panel: 'end-0 mt-1.5 w-[262px] rounded-qb-xl pt-1.5 pb-2 shadow-qb-langpop',
    title: 'px-[18px] pt-3 pb-2.5 text-qb-body',
    list: '',
    row: 'gap-2.5 px-[18px] py-[11px] text-qb-body-sm',
    radio: (selected: boolean) => cn('size-[18px]', selected ? 'border-[5px] border-qb-brand' : 'border-[1.5px] border-qb-ink-disabled'),
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
          <p id={titleId} className={cn('border-b border-qb-line font-semibold', style.title)}>
            {t('layout.language.title', 'اختر لغتك')}
          </p>
          <ul className={style.list}>
            {LANGUAGES.map(({ locale, name, country }) => {
              const selected = locale === current;
              return (
                <li key={locale}>
                  <button
                    type="button"
                    lang={locale}
                    aria-current={selected || undefined}
                    onClick={selected ? closeToTrigger : () => switchLocale(locale)}
                    className={cn(
                      'flex w-full cursor-pointer items-center text-start hover:bg-qb-hover',
                      style.row,
                      focusRing,
                      'focus-visible:-outline-offset-2',
                    )}
                  >
                    <span aria-hidden="true" className="min-w-5 text-qb-tiny font-medium tracking-[0.04em] text-qb-ink-subtle">
                      {country}
                    </span>
                    <span className="flex-1">{name}</span>
                    <span aria-hidden="true" className={cn('shrink-0 rounded-full', style.radio(selected))} />
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
