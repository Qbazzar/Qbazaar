import type { ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

export interface SettingsPanelProps {
  title: string;
  description?: ReactNode;
  /** Buttons on the end side of the title (e.g. "Mark all as read"). */
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}

/** Section title + muted subtitle at the top of a settings panel ("Account Settings", 394:9270). */
export function SettingsPanel({ title, description, actions, children, className }: SettingsPanelProps) {
  return (
    <section className={cn('font-qb', className)}>
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="text-qb-h5 leading-none font-semibold tracking-normal text-qb-ink-title">
            {title}
          </h1>
          {description ? (
            <p className="mt-[18px] text-qb-caption font-medium text-qb-ink-subtle">{description}</p>
          ) : null}
        </div>
        {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
      </header>
      <div className="mt-[38px] qb-tablet:mt-[47px]">{children}</div>
    </section>
  );
}

/**
 * Rows of a settings panel: one bordered group with dividers on phones
 * (613:32391), separate r16 boxes from the tablet layout up (561:30374).
 */
export function SettingsList({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <ul
      className={cn(
        'flex flex-col divide-y divide-qb-fill rounded-qb-xl border border-qb-line',
        'qb-tablet:gap-3.5 qb-tablet:divide-y-0 qb-tablet:rounded-none qb-tablet:border-0 qb-desktop:gap-4',
        className,
      )}
    >
      {children}
    </ul>
  );
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

export function SettingsRow({ label, value, description, action, icon, className }: SettingsRowProps) {
  return (
    <li
      className={cn(
        'flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-4',
        'qb-tablet:min-h-[73px] qb-tablet:rounded-qb-xl qb-tablet:border qb-tablet:border-qb-line qb-tablet:px-6',
        label ? 'qb-desktop:min-h-[100px]' : 'qb-desktop:min-h-[72px]',
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
        {label ? <p className="text-qb-micro text-qb-ink-muted qb-desktop:text-qb-body">{label}</p> : null}
        <div
          className={cn(
            'text-qb-caption text-qb-ink qb-desktop:text-qb-body',
            label ? 'mt-1.5 font-medium qb-desktop:mt-3 qb-desktop:font-semibold' : 'font-semibold',
          )}
        >
          {value}
        </div>
        {description ? (
          <p className="mt-1 text-qb-label text-qb-ink-subtle qb-desktop:text-qb-caption">{description}</p>
        ) : null}
      </div>
      {action ? <div className="flex shrink-0 items-center gap-2">{action}</div> : null}
    </li>
  );
}

/** Brand text action of a settings row ("Edit"). */
export const settingsActionClass = cn(
  'inline-flex min-h-10 items-center rounded-qb-xs px-1 text-qb-caption font-semibold text-qb-brand hover:text-qb-brand-hover hover:underline qb-tablet:font-medium qb-desktop:text-qb-body',
  'disabled:pointer-events-none disabled:opacity-50',
  focusRing,
);
