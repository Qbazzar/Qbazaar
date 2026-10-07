'use client';

import { useId, useState, type ReactNode } from 'react';
import { Minus, Plus } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { cn } from '@/lib/utils';

interface FilterGroupProps {
  title: string;
  /** Receives the heading id so a radio group can be labelled by it. */
  children: (headingId: string) => ReactNode;
  /** The price group has no toggle in the design (250:4405). */
  collapsible?: boolean;
  /** The bottom sheet lists plain labelled fields, without dividers or toggles (618:26974). */
  compact?: boolean;
}

/** One section of the filter panel: heading with a −/+ toggle, then its controls. */
export function FilterGroup({ title, children, collapsible = true, compact = false }: FilterGroupProps) {
  const id = useId();
  const [open, setOpen] = useState(true);
  const headingId = `${id}-heading`;
  const bodyId = `${id}-body`;
  const toggles = collapsible && !compact;

  return (
    <section className={compact ? 'py-2.5 first:pt-0' : 'border-t border-qb-line py-[18px] first:border-t-0 first:pt-0'}>
      <h3
        className={cn(
          'font-qb leading-normal font-medium tracking-normal',
          compact ? 'text-qb-caption text-qb-ink-body' : 'text-qb-body-lg text-qb-ink-secondary',
        )}
      >
        {toggles ? (
          <button
            type="button"
            id={headingId}
            aria-expanded={open}
            aria-controls={bodyId}
            onClick={() => setOpen((value) => !value)}
            className={cn('flex w-full items-center justify-between gap-3 rounded-qb-xs text-start', focusRing)}
          >
            {title}
            <Icon icon={open ? Minus : Plus} className="text-qb-ink-secondary" />
          </button>
        ) : (
          <span id={headingId}>{title}</span>
        )}
      </h3>
      <div id={bodyId} hidden={!open} className={compact ? 'mt-3' : 'mt-4'}>
        {children(headingId)}
      </div>
    </section>
  );
}
