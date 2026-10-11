import type { ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

export interface SettingsPanelProps {
  title: string;
  description?: ReactNode;
  /** Buttons on the end side of the title (e.g. the wallet's "Payment Method"). */
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}

/** A settings panel of account.html: 24 px title, muted subtitle, then its rows 20 px apart. */
export function SettingsPanel({ title, description, actions, children, className }: SettingsPanelProps) {
  return (
    <section className={cn('flex flex-col gap-5 font-qb', className)}>
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h2 className="text-qb-h3 leading-normal font-semibold tracking-normal text-qb-ink">{title}</h2>
          {description ? <p className="mt-2 text-qb-body-sm text-qb-ink-subtle">{description}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-3">{actions}</div> : null}
      </header>
      {children}
    </section>
  );
}

/** The rows of a panel: one white r16 card each, 20 px apart. */
export function SettingsList({ children, className }: { children: ReactNode; className?: string }) {
  return <ul className={cn('flex flex-col gap-5', className)}>{children}</ul>;
}

export interface SettingsRowProps {
  /** Small grey line above the value ("Email", "Full Name"). */
  label?: ReactNode;
  value: ReactNode;
  /** Muted line under the value. */
  description?: ReactNode;
  /** "Edit" link, switch or button on the end side. */
  action?: ReactNode;
  /** Leading icon tile. */
  icon?: ReactNode;
  className?: string;
}

/** White row card: 14 px grey label, 17 px semibold value, the action on the end side. */
export function SettingsRow({ label, value, description, action, icon, className }: SettingsRowProps) {
  return (
    <li
      className={cn(
        'flex flex-wrap items-center justify-between gap-3.5 rounded-qb-xl border border-qb-line bg-qb-surface px-6 py-[22px]',
        className,
      )}
    >
      {icon ? (
        <span
          aria-hidden="true"
          className="flex size-10 shrink-0 items-center justify-center rounded-qb-md bg-qb-fill text-qb-ink-body [&_svg]:size-5"
        >
          {icon}
        </span>
      ) : null}
      {/* The basis lets a wide action drop under the text on phones instead of squeezing it. */}
      <div className="min-w-0 flex-1 basis-[200px]">
        {label ? <p className="mb-1.5 text-qb-caption text-qb-ink-subtle">{label}</p> : null}
        <div className="text-[17px] leading-normal font-semibold text-qb-ink">{value}</div>
        {description ? <p className="mt-1 text-qb-caption text-qb-ink-subtle">{description}</p> : null}
      </div>
      {action ? <div className="flex shrink-0 items-center gap-2">{action}</div> : null}
    </li>
  );
}

/** Brand text action of a settings row ("Edit", 15 px medium). */
export const settingsActionClass = cn(
  'inline-flex cursor-pointer items-center rounded-qb-xs text-qb-body-sm font-medium text-qb-brand hover:text-qb-brand-hover',
  'disabled:pointer-events-none disabled:opacity-50',
  focusRing,
);
