'use client';

import { useId, useState, type ReactNode } from 'react';
import { Minus, Plus } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { cn } from '@/lib/utils';

interface FilterGroupProps {
  title: string;
  /** Receives the heading id so a radio group or a select can be labelled by it. */
  children: (headingId: string) => ReactNode;
  /** The price group has no toggle in the design (250:4405). */
  collapsible?: boolean;
  /** A labelled field of the bottom sheet (618:26974): a small label, no divider and no toggle. */
  field?: boolean;
}

/**
 * One section of the filter panel: a heading with a −/+ toggle over its
 * options (250:4405, and 244:3508 in the tablet sheet), or a plain labelled
 * field of the sheet.
 */
export function FilterGroup({ title, children, collapsible = true, field = false }: FilterGroupProps) {
  const id = useId();
  const [open, setOpen] = useState(true);
  const headingId = `${id}-heading`;
  const bodyId = `${id}-body`;
  const toggles = collapsible && !field;

  return (
    <section className={field ? 'py-2.5 first:pt-0' : 'border-t border-qb-line py-[18px] first:border-t-0 first:pt-0'}>
      <h3
        className={cn(
          'font-qb leading-normal font-medium tracking-normal',
          field ? 'text-qb-caption text-qb-ink-body' : 'text-qb-body-lg text-qb-ink-secondary',
        )}
      >
        {toggles ? (
          <button
            type="button"
            id={headingId}
            aria-expanded={open}
            aria-controls={bodyId}
            onClick={() => setOpen((value) => !value)}
            className={cn('flex w-full cursor-pointer items-center justify-between gap-3 rounded-qb-xs text-start', focusRing)}
          >
            {title}
            <Icon icon={open ? Minus : Plus} className="text-qb-ink-secondary" />
          </button>
        ) : (
          <span id={headingId}>{title}</span>
        )}
      </h3>
      <div id={bodyId} hidden={!open} className={field ? 'mt-3' : 'mt-4'}>
        {children(headingId)}
      </div>
    </section>
  );
}
