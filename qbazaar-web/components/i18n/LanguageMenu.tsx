'use client';

import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { getLocale, type Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { switchLocale } from './switch-locale';

/** The site's languages, each named in itself; the country code stands in for the reference's flag. */
const LANGUAGES: { locale: Locale; name: string; country: string }[] = [
  { locale: 'en', name: 'English', country: 'GB' },
  { locale: 'ar', name: 'العربية', country: 'QA' },
];

interface LanguageMenuProps {
  /** Classes of the trigger button (the header's icon or icon square). */
  className: string;
  /** The trigger's content, usually the globe icon. */
  children: ReactNode;
  /**
   * `trigger` opens the panel under the button's end edge; `container` under
   * the nearest positioned ancestor's, for a button too close to the start
   * edge on narrow phones.
   */
  anchor?: 'trigger' | 'container';
}

/**
 * "Choose your language" panel of the header globe (741:39398, drawn as
 * Qbazaar-front's `.qb-langpop`). Picking the other language stores it and
 * reloads; Escape, a click outside or tabbing away closes it.
 */
export function LanguageMenu({ className, children, anchor = 'trigger' }: LanguageMenuProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();
  const titleId = `${panelId}-title`;
  const current = getLocale();

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

  return (
    <div
      ref={rootRef}
      className={cn('flex', anchor === 'trigger' && 'relative')}
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
        onClick={() => setOpen((value) => !value)}
        className={className}
      >
        {children}
      </button>
      {open ? (
        <div
          id={panelId}
          role="group"
          aria-labelledby={titleId}
          className={cn(
            'absolute end-0 top-full z-50 w-[262px] rounded-qb-xl border border-qb-line bg-qb-surface pt-1.5 pb-2 font-qb text-qb-ink shadow-qb-popover',
            anchor === 'container' ? 'mt-1.5' : 'mt-5',
          )}
        >
          <p id={titleId} className="border-b border-qb-line px-[18px] pt-3 pb-2.5 text-qb-body font-semibold">
            {t('layout.language.title', 'اختر لغتك')}
          </p>
          <ul>
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
                      'flex w-full cursor-pointer items-center gap-2.5 px-[18px] py-[11px] text-start text-qb-body-sm hover:bg-qb-hover',
                      focusRing,
                      'focus-visible:-outline-offset-2',
                    )}
                  >
                    <span aria-hidden="true" className="min-w-5 text-qb-tiny font-medium tracking-[0.04em] text-qb-ink-subtle">
                      {country}
                    </span>
                    <span className="flex-1">{name}</span>
                    <span
                      aria-hidden="true"
                      className={cn(
                        'size-[18px] shrink-0 rounded-full',
                        selected ? 'border-[5px] border-qb-brand' : 'border-[1.5px] border-qb-ink-disabled',
                      )}
                    />
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
