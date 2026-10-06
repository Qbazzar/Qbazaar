import Link from 'next/link';
import { ChevronRight } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';
import { Icon } from './Icon';

export interface BreadcrumbItem {
  label: string;
  /** Omit on the current page (the last item). */
  href?: string;
}

export interface BreadcrumbProps {
  items: BreadcrumbItem[];
  className?: string;
}

/** "Home > Car & Vehicles > Cars" trail; the last item is the current page. */
export function Breadcrumb({ items, className }: BreadcrumbProps) {
  return (
    <nav aria-label={t('ui.breadcrumb')} className={cn('font-qb text-qb-h5', className)}>
      <ol className="flex flex-wrap items-center gap-2">
        {items.map((item, index) => {
          const isCurrent = index === items.length - 1;
          return (
            <li key={`${index}-${item.label}`} className="inline-flex items-center gap-2">
              {index > 0 ? <Icon icon={ChevronRight} size="sm" flipInRtl className="text-qb-breadcrumb-separator" /> : null}
              {isCurrent || !item.href ? (
                <span aria-current={isCurrent ? 'page' : undefined} className={cn(isCurrent ? 'font-medium text-qb-ink' : 'text-qb-breadcrumb')}>
                  {item.label}
                </span>
              ) : (
                <Link href={item.href} className={cn('rounded-qb-xs text-qb-breadcrumb hover:text-qb-ink', focusRing)}>
                  {item.label}
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
