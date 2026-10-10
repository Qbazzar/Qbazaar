import Link from 'next/link';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import type { BreadcrumbProps } from './Breadcrumb';
import { focusRing } from './focus-ring';

/**
 * "Home > Car & Vehicles > Cars > BMW M3 Competition.." as product.html,
 * seller-individual.html and users.html draw it (typo.css): 20 px #a4adba
 * links that turn orange under the pointer, the current page in dark 500,
 * and a ">" glyph 8 px either side, turned to point the other way in
 * Arabic. The trail stays on one line and a long one scrolls sideways, like
 * the reference's .qb-crumbs. Each label is isolated, so a Latin title keeps
 * its order in an Arabic trail.
 */
export function TextBreadcrumb({ items, className }: BreadcrumbProps) {
  return (
    <nav aria-label={t('ui.breadcrumb')} className={cn('font-qb text-qb-h5', className)}>
      {/* The 4 px padding, taken back by the margin, keeps the links' focus ring clear of the scroll clip. */}
      <ol className="-my-1 flex items-center gap-2 overflow-x-auto py-1 whitespace-nowrap [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
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
