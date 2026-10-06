import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';
import { Icon } from './Icon';

export interface SectionHeaderProps {
  title: string;
  subtitle?: string;
  /** "View all" style link on the end side. */
  action?: { href: string; label: string };
  headingLevel?: 'h2' | 'h3';
  id?: string;
  className?: string;
}

/** Home and listing section heading: "Browse Categories" + subtitle + "View All →". */
export function SectionHeader({ title, subtitle, action, headingLevel: Heading = 'h2', id, className }: SectionHeaderProps) {
  return (
    <div className={cn('flex items-end justify-between gap-4 font-qb', className)}>
      <div className="min-w-0">
        <Heading id={id} className="text-qb-h4 font-semibold tracking-normal text-qb-ink qb-desktop:text-qb-h2">
          {title}
        </Heading>
        {subtitle ? <p className="mt-1 text-qb-body-sm text-qb-ink-subtle">{subtitle}</p> : null}
      </div>
      {action ? (
        <Link
          href={action.href}
          className={cn(
            'inline-flex shrink-0 items-center gap-2 rounded-qb-sm text-qb-body text-qb-brand hover:text-qb-brand-active',
            focusRing,
          )}
        >
          {action.label}
          <Icon icon={ArrowRight} size="sm" flipInRtl />
        </Link>
      ) : null}
    </div>
  );
}
