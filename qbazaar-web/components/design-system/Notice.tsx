import type { HTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

const TONES = {
  brand: 'border-qb-brand bg-qb-brand-soft [&>svg]:text-qb-brand',
  info: 'border-qb-info bg-qb-info-soft [&>svg]:text-qb-info',
  success: 'border-qb-success bg-qb-success-soft [&>svg]:text-qb-success',
  danger: 'border-qb-danger bg-qb-danger-soft [&>svg]:text-qb-danger',
  neutral: 'border-qb-line bg-qb-fill [&>svg]:text-qb-ink-muted',
} as const;

export interface NoticeProps extends Omit<HTMLAttributes<HTMLDivElement>, 'title'> {
  tone?: keyof typeof TONES;
  /** Decorative lucide icon, tinted with the tone. */
  icon?: ReactNode;
  title?: ReactNode;
}

/**
 * Bordered message box, as the "Safe Pay" note of the chat (667:30685).
 * The tone shows in the border, fill and icon; the text stays ink. Pass `role="alert"` or
 * `role="status"` when it reports the result of an action.
 */
export function Notice({ tone = 'brand', icon, title, children, className, ...props }: NoticeProps) {
  return (
    <div
      className={cn(
        'flex items-start gap-3 rounded-qb-md border px-4 py-3 font-qb text-qb-caption text-qb-ink-body [&>svg]:mt-0.5 [&>svg]:size-5 [&>svg]:shrink-0',
        TONES[tone],
        className,
      )}
      {...props}
    >
      {icon}
      <div className="min-w-0 flex-1">
        {title ? <p className="font-semibold text-qb-ink-title">{title}</p> : null}
        {children ? <div className={title ? 'mt-0.5' : undefined}>{children}</div> : null}
      </div>
    </div>
  );
}
