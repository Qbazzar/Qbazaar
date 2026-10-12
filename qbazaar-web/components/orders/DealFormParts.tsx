import type { ReactNode } from 'react';
import Link from 'next/link';

import { Button, buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { InfoCircleIcon, LoaderCircleIcon } from '@/components/design-system/icons';
import { cn } from '@/lib/utils';

/**
 * The two buttons of the deal forms (buy-now.html, offer.html): 52 px tall, side by side, r10 with
 * 15 px semibold text and the `.qb-btn` 1.15 line height.
 */
export const dealButton = 'h-auto min-h-[52px] flex-1 rounded-qb-md px-3.5 text-qb-body-sm leading-[1.15] font-semibold';

/** The 15 px #4B4B4B label above a deal-form control, 10 px over it. */
export const dealLabelClass = 'mb-2.5 block text-qb-body-sm text-qb-ink-body';

/**
 * The white form card of the Buy Now and Make an Offer pages: r16 with a 1 px border and 28 px
 * padding, the 26 px semibold title 22 px above the form. It takes two thirds of the row and wraps
 * under 740 px, as the reference's flex row does.
 */
export function DealPanel({ title, titleId, children, className }: { title: string; titleId: string; children: ReactNode; className?: string }) {
  return (
    <section
      aria-labelledby={titleId}
      className={cn(
        'min-w-[min(300px,100%)] flex-[2_1_440px] rounded-qb-xl border border-qb-line bg-qb-surface p-7 font-qb text-qb-ink',
        className,
      )}
    >
      <h1 id={titleId} className="mb-[22px] text-[26px] font-semibold tracking-normal">
        {title}
      </h1>
      {children}
    </section>
  );
}

/** Brand hint line with the 16 px info icon (offer.html), 22 px above "How it works". */
export function InfoHint({ children }: { children: ReactNode }) {
  return (
    <p className="mb-[22px] flex items-start gap-2 text-qb-label text-qb-brand">
      <Icon icon={InfoCircleIcon} size={16} className="mt-px" />
      <span>{children}</span>
    </p>
  );
}

/** "How it works": a #F9F9F9 r12 box with 7 px brand bullets and #616161 steps. */
export function HowItWorks({ title, steps }: { title: string; steps: string[] }) {
  return (
    <div className="mb-6 rounded-qb-lg bg-qb-page px-5 py-[18px]">
      <h2 className="mb-3 text-qb-body-sm font-semibold tracking-normal">{title}</h2>
      <ul className="flex flex-col gap-2.5">
        {steps.map((step) => (
          <li key={step} className="flex items-start gap-2.5 text-qb-caption text-qb-ink-secondary">
            <span aria-hidden="true" className="mt-1.5 size-[7px] shrink-0 rounded-full bg-qb-brand" />
            <span>{step}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Submit plus a way back to the ad: the orange button and the white outlined Cancel, both 52 px. */
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
    <div className="flex gap-3">
      <Button type="submit" className={dealButton} disabled={busy} aria-busy={busy}>
        {busy ? <Icon icon={LoaderCircleIcon} size={16} className="animate-spin motion-reduce:animate-none" /> : null}
        <span>{submitLabel}</span>
      </Button>
      <Link href={cancelHref} className={cn(buttonVariants({ variant: 'outline' }), dealButton, 'text-qb-ink-body')}>
        {cancelLabel}
      </Link>
    </div>
  );
}
