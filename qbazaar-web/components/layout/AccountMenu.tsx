'use client';

import { useEffect, useId, useRef, useState, type MouseEvent } from 'react';
import Link from 'next/link';

import { useSignOut } from '@/components/account/useSignOut';
import { Avatar, initialsOf } from '@/components/design-system/Avatar';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { GearIcon, LogOutIcon, MyAdsDrawerIcon, PercentCircleIcon, SearchDrawerIcon, type IconGlyph } from '@/components/design-system/icons';
import { menuEnter } from '@/components/design-system/menu-enter';
import { useHoverOpen } from '@/components/design-system/use-hover-open';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** The reference closes the menu 160 ms after the mouse leaves, so the pointer can cross the gap to it. */
const CLOSE_DELAY_MS = 160;

/** The reference's rows (`.qb-btn`): 1.15 line height, and they dim slightly under the pointer. */
const row = cn(
  'flex w-full cursor-pointer items-center gap-3 rounded-qb-md bg-qb-surface px-3 py-[11px] text-start text-qb-body-sm leading-[1.15] qb-motion hover:brightness-[.96]',
  focusRing,
  'focus-visible:-outline-offset-2',
);

interface AccountMenuProps {
  name: string;
  email?: string | null;
}

/**
 * closed; peek: opened by the focus arriving, shown but out of the tab order so it
 * adds no tab stops; full: opened by hover, a tap or the keyboard, its rows reachable.
 */
type Mode = 'closed' | 'peek' | 'full';
type InputKind = 'mouse' | 'touch' | 'key';

/**
 * The header avatar (728:44663) and its account menu: the avatar opens the
 * account, and the mouse resting on it (or the keyboard focus reaching it)
 * opens the menu under it. A tap or Enter opens it first and a second one
 * goes to the account. Focus alone shows the menu without adding tab stops.
 * Escape, a click outside or the focus leaving closes it.
 */
export function AccountMenu({ name, email }: AccountMenuProps) {
  const [mode, setMode] = useState<Mode>('closed');
  const rootRef = useRef<HTMLDivElement>(null);
  const avatarRef = useRef<HTMLAnchorElement>(null);
  const panelRef = useRef<HTMLElement>(null);
  const inputKind = useRef<InputKind>('mouse');
  const focusFirstRow = useRef(false);
  const panelId = useId();
  const open = mode !== 'closed';
  const rowTab = mode === 'full' ? undefined : -1;
  const { hoverHandlers } = useHoverOpen((hovered) => setMode(hovered ? 'full' : 'closed'), CLOSE_DELAY_MS);
  const { signOut, pending } = useSignOut();
  const initials = initialsOf(name);
  const close = () => setMode('closed');

  useEffect(() => {
    if (!open) return;
    const closeOnOutsidePress = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setMode('closed');
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    return () => document.removeEventListener('pointerdown', closeOnOutsidePress);
  }, [open]);

  useEffect(() => {
    if (mode !== 'full' || !focusFirstRow.current) return;
    focusFirstRow.current = false;
    panelRef.current?.querySelector<HTMLElement>('a, button')?.focus();
  }, [mode]);

  /** Makes the rows reachable; the keyboard also moves into them. */
  const enterMenu = (withFocus: boolean) => {
    focusFirstRow.current = withFocus;
    if (mode === 'full' && withFocus) panelRef.current?.querySelector<HTMLElement>('a, button')?.focus();
    setMode('full');
  };

  const onAvatarClick = (event: MouseEvent) => {
    if (inputKind.current === 'mouse') return;
    if (mode === 'full') return close();
    // The first tap or Enter opens the menu; the next one, or its "account" row, goes to the account.
    event.preventDefault();
    enterMenu(inputKind.current === 'key');
  };

  const items: { href: string; label: string; icon: IconGlyph }[] = [
    { href: '/account/saved-searches', label: t('layout.menu.save_search', 'البحث المحفوظ'), icon: SearchDrawerIcon },
    { href: '/account/ads', label: t('layout.menu.my_ads', 'إعلاناتي'), icon: MyAdsDrawerIcon },
    { href: '/account/orders', label: t('layout.menu.sales_overview', 'نظرة على المبيعات'), icon: PercentCircleIcon },
    { href: '/account', label: t('layout.menu.account', 'إعدادات الحساب'), icon: GearIcon },
  ];

  return (
    <div
      ref={rootRef}
      className="relative flex"
      {...hoverHandlers}
      onPointerDown={(event) => {
        inputKind.current = event.pointerType === 'mouse' ? 'mouse' : 'touch';
      }}
      onFocus={(event) => {
        // Only focus arriving from outside opens it; Escape hands the focus back to the avatar inside.
        if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setMode((current) => (current === 'closed' ? 'peek' : current));
      }}
      onBlur={(event) => {
        if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setMode('closed');
      }}
      onKeyDown={(event) => {
        inputKind.current = 'key';
        if (event.key === 'ArrowDown' && event.target === avatarRef.current) {
          event.preventDefault();
          enterMenu(true);
        } else if (open && event.key === 'Escape') {
          close();
          avatarRef.current?.focus();
        }
      }}
    >
      <Link
        ref={avatarRef}
        href="/account"
        aria-label={t('layout.header.account_named', { initials }, `${initials}، حسابي`)}
        aria-expanded={open}
        onClick={onAvatarClick}
        aria-controls={open ? panelId : undefined}
        className={cn(
          'qb-motion relative rounded-full hover:brightness-[.96]',
          focusRing,
        )}
      >
        <Avatar name={name} decorative />
      </Link>
      {open ? (
        <nav
          ref={panelRef}
          id={panelId}
          aria-label={t('layout.menu.account_menu', 'قائمة الحساب')}
          className={cn(
            'absolute end-0 top-full z-50 mt-3 w-[270px] rounded-qb-xl border border-qb-line bg-qb-surface p-2 font-qb shadow-qb-menu before:absolute before:inset-x-0 before:-top-3 before:h-3',
            menuEnter,
          )}
        >
          <Link href="/account" tabIndex={rowTab} onClick={close} className={cn(row, 'mb-1.5 rounded-none border-b border-qb-line p-3')}>
            <Avatar name={name} decorative className="size-[42px] text-qb-body-sm" />
            <span className="min-w-0">
              <span className="block truncate text-qb-body-sm leading-[1.15] font-semibold text-qb-ink">{name}</span>
              {email ? <span className="block truncate text-qb-label leading-[1.15] text-qb-ink-subtle">{email}</span> : null}
            </span>
          </Link>
          <ul>
            {items.map((item) => (
              <li key={item.href}>
                <Link href={item.href} tabIndex={rowTab} onClick={close} className={cn(row, 'text-qb-ink-body')}>
                  <Icon icon={item.icon} strokeWidth={1.7} />
                  {item.label}
                </Link>
              </li>
            ))}
            <li>
              <button type="button" tabIndex={rowTab} onClick={signOut} disabled={pending} className={cn(row, 'text-qb-brand')}>
                <Icon icon={LogOutIcon} strokeWidth={1.7} flipInRtl />
                {t('layout.menu.log_out', 'تسجيل الخروج')}
              </button>
            </li>
          </ul>
        </nav>
      ) : null}
    </div>
  );
}
