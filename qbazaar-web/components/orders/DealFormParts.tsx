import type { ReactNode } from 'react';
import Link from 'next/link';
import { Info, Loader2 } from 'lucide-react';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { cn } from '@/lib/utils';

import { panelClass, panelPadding } from './CheckoutPanel';

import '@/styles/design-tokens-sell.css';

/**
 * Shape of the form buttons of the deal pages: 44 px with 14 px text on
 * phones and desktop (659:58417), 48 px with 16 px text on tablets (709:32483).
 */
export const dealButton =
  'h-11 rounded-qb-sm text-qb-caption qb-tablet:h-12 qb-tablet:rounded-qb-lg qb-tablet:text-qb-body qb-desktop:h-11 qb-desktop:rounded-qb-sm qb-desktop:text-qb-caption';

/** The two deal forms, whose tablet frames differ in type (709:32483, 709:32645). */
export type DealPage = 'buy' | 'offer';

/**
 * Labels of the deal forms, for a Field's `className`: 14/500 on phones
 * (709:33450), 16/400 from the tablet up (659:58417, 709:32645); Buy Now's
 * are semibold on tablets (709:32483).
 */
export const dealLabel: Record<DealPage, string> = {
  buy: '[&>label]:text-qb-caption [&>label]:font-medium qb-tablet:[&>label]:text-qb-body qb-tablet:[&>label]:font-semibold qb-desktop:[&>label]:font-normal',
  offer: '[&>label]:text-qb-caption [&>label]:font-medium qb-tablet:[&>label]:text-qb-body qb-tablet:[&>label]:font-normal',
};

/** White r24 panel with the form title, as on the Buy Now and offer pages. */
export function DealPanel({ title, titleId, children, className }: { title: string; titleId: string; children: ReactNode; className?: string }) {
  return (
    <section aria-labelledby={titleId} className={cn(panelClass, panelPadding, className)}>
      <h1 id={titleId} className="font-qb text-qb-body-lg font-medium tracking-normal text-qb-ink-title qb-tablet:text-qb-h4">
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
      <h2 className="font-qb text-qb-body-lg font-medium tracking-normal text-(--color-qb-ink-heading) qb-tablet:text-qb-body qb-tablet:font-semibold qb-desktop:font-normal">
        {title}
      </h2>
      <ul className="mt-4 flex flex-col gap-3">
        {steps.map((step) => (
          <li key={step} className="flex items-start gap-2 text-qb-caption text-(--color-qb-ink-step)">
            <span aria-hidden="true" className="mt-1 size-[13px] shrink-0 rounded-full bg-qb-brand" />
            <span>{step}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/**
 * Tablet look of the form buttons: Buy Now outlines a 16/600 #4B4B4B Cancel
 * (709:32483); the offer page sets both at 15 px with a black 500 Cancel
 * (709:32645).
 */
const TABLET_ACTIONS: Record<DealPage, { submit?: string; cancel: string }> = {
  buy: { cancel: 'qb-tablet:border qb-tablet:border-qb-line qb-tablet:bg-qb-surface qb-tablet:font-semibold qb-tablet:text-qb-ink-body' },
  offer: { submit: 'qb-tablet:text-qb-body-sm', cancel: 'qb-tablet:text-qb-body-sm qb-tablet:text-qb-black' },
};

/** Submit plus a way back, side by side from the tablet up; Cancel is 14/500 text on phones and a grey 14/400 button on desktop. */
export function DealActions({
  page,
  submitLabel,
  busy,
  cancelLabel,
  cancelHref,
}: {
  page: DealPage;
  submitLabel: string;
  busy: boolean;
  cancelLabel: string;
  cancelHref: string;
}) {
  const tablet = TABLET_ACTIONS[page];
  return (
    <div className="mt-4 flex gap-3 qb-tablet:gap-6">
      <Button type="submit" className={cn(dealButton, tablet.submit, 'flex-1')} disabled={busy} aria-busy={busy}>
        {busy ? <Loader2 className="animate-spin motion-reduce:animate-none" aria-hidden="true" /> : null}
        {submitLabel}
      </Button>
      <Link
        href={cancelHref}
        className={cn(
          buttonVariants({ variant: 'muted' }),
          dealButton,
          'flex-1 bg-transparent font-medium',
          tablet.cancel,
          'qb-desktop:border-0 qb-desktop:bg-qb-fill qb-desktop:font-normal qb-desktop:text-qb-ink-muted',
        )}
      >
        {cancelLabel}
      </Link>
    </div>
  );
}
