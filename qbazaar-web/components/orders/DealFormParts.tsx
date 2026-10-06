import type { ReactNode } from 'react';
import Link from 'next/link';
import { Info, Loader2 } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { cn } from '@/lib/utils';

/** Shape of the 44 px form buttons of the deal pages (659:58417). */
export const dealButton = 'h-11 rounded-qb-sm text-qb-caption';

/** White r24 panel with the form title, as on the Buy Now and offer pages. */
export function DealPanel({ title, titleId, children, className }: { title: string; titleId: string; children: ReactNode; className?: string }) {
  return (
    <section
      aria-labelledby={titleId}
      className={cn(
        'rounded-qb-2xl border border-qb-line bg-qb-surface p-4 font-qb shadow-qb-card qb-tablet:p-6 qb-desktop:p-8',
        className,
      )}
    >
      <h1 id={titleId} className="text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h4">
        {title}
      </h1>
      <div className="mt-6 flex flex-col gap-4 qb-desktop:mt-8">{children}</div>
    </section>
  );
}

/** Brand-tinted hint line with an info icon, set in the label face (659:58417). */
export function InfoHint({ children }: { children: ReactNode }) {
  return (
    <p className="flex items-start gap-2 font-qb-label text-qb-micro text-qb-brand">
      <Icon icon={Info} size="sm" className="mt-px size-3.5" />
      <span>{children}</span>
    </p>
  );
}

/** "How it works" list with brand bullets: a plain section on phones (709:33450), a bordered box from the tablet up. */
export function HowItWorks({ title, steps }: { title: string; steps: string[] }) {
  return (
    <div className="mt-2 qb-tablet:mt-0 qb-tablet:rounded-qb-2xl qb-tablet:border qb-tablet:border-qb-line qb-tablet:bg-qb-surface qb-tablet:p-4">
      <h2 className="text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-body qb-tablet:font-normal qb-tablet:text-qb-ink-body">
        {title}
      </h2>
      <ul className="mt-4 flex flex-col gap-3">
        {steps.map((step) => (
          <li key={step} className="flex items-start gap-2 text-qb-caption text-qb-ink-secondary">
            <span aria-hidden="true" className="mt-1 size-[13px] shrink-0 rounded-full bg-qb-brand" />
            <span>{step}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Submit plus a way back, side by side from the tablet up. */
export function DealActions({
  submitLabel,
  busy,
  cancelLabel,
  cancelHref,
}: {
  submitLabel: string;
  busy: boolean;
  cancelLabel: string;
  cancelHref: string;
}) {
  return (
    <div className="mt-4 flex gap-3 qb-tablet:gap-6">
      <Button type="submit" className={cn(dealButton, 'flex-1')} disabled={busy} aria-busy={busy}>
        {busy ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
        {submitLabel}
      </Button>
      <Link href={cancelHref} className={cn(buttonVariants({ variant: 'muted' }), dealButton, 'flex-1 bg-transparent qb-desktop:bg-qb-fill')}>
        {cancelLabel}
      </Link>
    </div>
  );
}
