import Link from 'next/link';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

export interface BreadcrumbItem {
  label: string;
  /** Omit on the current page (the last item). */
  href?: string;
}

export interface BreadcrumbProps {
  items: BreadcrumbItem[];
  className?: string;
}

/**
 * Phones keep the trail on one line and scroll it sideways (the reference's
 * .qb-crumbs). The 4 px padding, taken back by the margin, keeps the links'
 * focus ring clear of the scroll clip.
 */
const scrollOnPhones =
  'max-qb-tablet:-my-1 max-qb-tablet:flex-nowrap max-qb-tablet:overflow-x-auto max-qb-tablet:py-1 max-qb-tablet:whitespace-nowrap max-qb-tablet:[scrollbar-width:none] max-qb-tablet:[&::-webkit-scrollbar]:hidden';

/**
 * "Home > Car & Vehicles > Cars" as the reference draws it (typo.css): 20 px
 * #a4adba links that turn orange under the pointer (.qb-nav:hover), the
 * current page in dark 500 and a ">" glyph 8 px either side. Each label is
 * isolated, so a Latin label keeps its order in an Arabic trail and the
 * reverse.
 */
export function Breadcrumb({ items, className }: BreadcrumbProps) {
  return (
    <nav aria-label={t('ui.breadcrumb')} className={cn('font-qb text-qb-h5', className)}>
      <ol className={cn('flex flex-wrap items-center gap-2', scrollOnPhones)}>
        {items.map((item, index) => {
          const isCurrent = index === items.length - 1;
          return (
            <li key={`${index}-${item.label}`} className="flex shrink-0 items-center gap-2">
              {/* Turned by CSS in Arabic: bidi mirroring of a lone ">" is not applied by every browser. */}
              {index > 0 ? (
                <span aria-hidden="true" dir="ltr" className="text-qb-breadcrumb rtl:-scale-x-100">
                  &gt;
                </span>
              ) : null}
              {isCurrent || !item.href ? (
                <span aria-current={isCurrent ? 'page' : undefined} className={isCurrent ? 'font-medium text-qb-ink' : 'text-qb-breadcrumb'}>
                  <bdi>{item.label}</bdi>
                </span>
              ) : (
                <Link href={item.href} className={cn('rounded-qb-xs text-qb-breadcrumb transition-colors hover:text-qb-brand', focusRing)}>
                  <bdi>{item.label}</bdi>
                </Link>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
