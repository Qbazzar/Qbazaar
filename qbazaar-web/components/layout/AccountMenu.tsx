'use client';

import { useEffect, useId, useRef, useState } from 'react';
import Link from 'next/link';
import { CirclePercent, LogOut, MapPin, Search, Settings, type LucideIcon } from 'lucide-react';

import { useSignOut } from '@/components/account/useSignOut';
import { Avatar, initialsOf } from '@/components/design-system/Avatar';
import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { useHoverOpen } from '@/components/design-system/use-hover-open';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

/** The reference closes the menu 160 ms after the mouse leaves, so the pointer can cross the gap to it. */
const CLOSE_DELAY_MS = 160;

/** The reference's rows dim slightly under the pointer (`.qb-btn:hover`). */
const row = cn(
  'flex w-full cursor-pointer items-center gap-3 rounded-qb-md bg-qb-surface px-3 py-[11px] text-start text-qb-body-sm transition-[filter] duration-150 hover:brightness-[0.96] motion-reduce:transition-none',
  focusRing,
  'focus-visible:-outline-offset-2',
);

interface AccountMenuProps {
  name: string;
  email?: string | null;
}

/**
 * The header avatar (728:44663) and its account menu: the avatar opens the
 * account, and the mouse resting on it (or the keyboard focus reaching it)
 * opens the menu under it. Escape, a click outside or the focus leaving
 * closes it.
 */
export function AccountMenu({ name, email }: AccountMenuProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const avatarRef = useRef<HTMLAnchorElement>(null);
  const panelId = useId();
  const { hoverHandlers } = useHoverOpen(setOpen, CLOSE_DELAY_MS);
  const { signOut, pending } = useSignOut();
  const initials = initialsOf(name);
  const close = () => setOpen(false);

  useEffect(() => {
    if (!open) return;
    const closeOnOutsidePress = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener('pointerdown', closeOnOutsidePress);
    return () => document.removeEventListener('pointerdown', closeOnOutsidePress);
  }, [open]);

  const items: { href: string; label: string; icon: LucideIcon }[] = [
    { href: '/account/saved-searches', label: t('layout.menu.save_search', 'البحث المحفوظ'), icon: Search },
    { href: '/account/ads', label: t('layout.menu.my_ads', 'إعلاناتي'), icon: MapPin },
    { href: '/account/orders', label: t('layout.menu.sales_overview', 'نظرة على المبيعات'), icon: CirclePercent },
    { href: '/account', label: t('layout.menu.account', 'إعدادات الحساب'), icon: Settings },
  ];

  return (
    <div
      ref={rootRef}
      className="relative flex"
      {...hoverHandlers}
      onFocus={(event) => {
        // Only focus arriving from outside opens it; Escape hands the focus back to the avatar inside.
        if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setOpen(true);
      }}
      onBlur={(event) => {
        if (!rootRef.current?.contains(event.relatedTarget as Node | null)) setOpen(false);
      }}
      onKeyDown={(event) => {
        if (!open || event.key !== 'Escape') return;
        setOpen(false);
        avatarRef.current?.focus();
      }}
    >
      <Link
        ref={avatarRef}
        href="/account"
        aria-label={t('layout.header.account_named', { initials }, `${initials}، حسابي`)}
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        className={cn(
          'relative rounded-full after:absolute after:inset-0 after:rounded-full after:bg-qb-overlay after:opacity-0 after:transition-opacity hover:after:opacity-100 motion-reduce:after:transition-none',
          focusRing,
        )}
      >
        <Avatar name={name} decorative />
      </Link>
      {open ? (
        <nav
          id={panelId}
          aria-label={t('layout.menu.account_menu', 'قائمة الحساب')}
          className="absolute end-0 top-full z-50 mt-3 w-[270px] rounded-qb-xl border border-qb-line bg-qb-surface p-2 font-qb shadow-qb-menu before:absolute before:inset-x-0 before:-top-3 before:h-3"
        >
          <Link href="/account" onClick={close} className={cn(row, 'mb-1.5 rounded-none border-b border-qb-line p-3')}>
            <Avatar name={name} decorative className="size-[42px] text-qb-body-sm" />
            <span className="min-w-0">
              <span className="block truncate text-qb-body-sm font-semibold text-qb-ink">{name}</span>
              {email ? <span className="block truncate text-qb-label text-qb-ink-subtle">{email}</span> : null}
            </span>
          </Link>
          <ul>
            {items.map((item) => (
              <li key={item.href}>
                <Link href={item.href} onClick={close} className={cn(row, 'text-qb-ink-body')}>
                  <Icon icon={item.icon} strokeWidth={1.7} />
                  {item.label}
                </Link>
              </li>
            ))}
            <li>
              <button type="button" onClick={signOut} disabled={pending} className={cn(row, 'text-qb-brand')}>
                <Icon icon={LogOut} strokeWidth={1.7} flipInRtl />
                {t('layout.menu.log_out', 'تسجيل الخروج')}
              </button>
            </li>
          </ul>
        </nav>
      ) : null}
    </div>
  );
}
