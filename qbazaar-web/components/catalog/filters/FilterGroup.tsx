'use client';

import { useId, useState, type ReactNode } from 'react';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

interface FilterGroupProps {
  title: string;
  /** Receives the heading id so a radio group or a select can be labelled by it. */
  children: (headingId: string) => ReactNode;
  /** The price group has no toggle in the design. */
  collapsible?: boolean;
  /** Classes of the heading, e.g. the price title's own 12 px bottom margin. */
  headingClassName?: string;
  /** Classes of the body under the heading. */
  bodyClassName?: string;
}

/**
 * One section of the filter card (category.html aside): a 16 px / 500 heading
 * at Poppins' normal line height, with the grey "−" (or "+" while closed) at
 * its end, 14 px over its options, between hairlines.
 */
export function FilterGroup({ title, children, collapsible = true, headingClassName, bodyClassName }: FilterGroupProps) {
  const id = useId();
  const [open, setOpen] = useState(true);
  const headingId = `${id}-heading`;
  const bodyId = `${id}-body`;

  return (
    <section className="border-t border-qb-line py-[18px] first:border-t-0 first:pt-0">
      {/* Without a toggle the title is the heading's own text, as the reference's "Price" with its own bottom margin. */}
      <h3 id={collapsible ? undefined : headingId} className={cn('font-qb text-qb-body font-medium tracking-normal text-qb-ink', headingClassName)}>
        {collapsible ? (
          <button
            type="button"
            id={headingId}
            aria-expanded={open}
            aria-controls={bodyId}
            onClick={() => setOpen((value) => !value)}
            className={cn('flex w-full cursor-pointer items-center justify-between gap-3 text-start', focusRing)}
          >
            {title}
            <span aria-hidden="true" className="text-qb-h5 font-normal text-qb-ink-subtle">
              {open ? '−' : '+'}
            </span>
          </button>
        ) : (
          title
        )}
      </h3>
      <div id={bodyId} hidden={!open} className={cn('mt-3.5', bodyClassName)}>
        {children(headingId)}
      </div>
    </section>
  );
}
