import Link from 'next/link';
import { ChevronRight } from 'lucide-react';

import { Icon } from '@/components/design-system/Icon';
import { focusRing } from '@/components/design-system/focus-ring';
import { localized } from '@/lib/i18n/locale';
import { cn } from '@/lib/utils';
import type { HelpArticleListItem } from '@/lib/api/types';

/** Help article as a text row in the notification-row style (455:14636), used by topic pages and search results. */
export function HelpArticleCard({ article }: { article: HelpArticleListItem }) {
  const excerpt = localized(article.excerpt);

  return (
    <Link
      href={`/help/articles/${article.slug}`}
      className={cn(
        'group flex items-center gap-4 rounded-qb-xl border border-qb-line bg-qb-surface px-5 py-4 font-qb shadow-qb-card transition-colors hover:bg-qb-hover',
        'qb-tablet:rounded-qb-2xl qb-tablet:px-6 qb-tablet:py-5 qb-desktop:py-6',
        focusRing,
      )}
    >
      <span className="min-w-0 flex-1">
        <span className="block text-qb-body font-medium break-words text-qb-ink-body qb-desktop:text-qb-h5">
          {localized(article.title)}
        </span>
        {excerpt ? (
          <>
            {' '}
            <span className="mt-1 line-clamp-2 block text-qb-caption text-qb-ink-muted qb-desktop:mt-2 qb-desktop:text-qb-body">
              {excerpt}
            </span>
          </>
        ) : null}
      </span>
      <Icon
        icon={ChevronRight}
        flipInRtl
        className="text-qb-ink-subtle transition-colors group-hover:text-qb-brand"
      />
    </Link>
  );
}
