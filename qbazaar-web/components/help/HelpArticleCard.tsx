/**
 * Compact help article row used by the category page + search results.
 *
 * Reuses the QBFront `.card` token for consistency with the rest of the
 * design system, then adds title + excerpt + a chevron pointing at detail.
 */
import Link from 'next/link';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { getLocale, localized } from '@/lib/i18n/locale';
import type { HelpArticleListItem } from '@/lib/api/types';

interface Props {
  article: HelpArticleListItem;
}

export function HelpArticleCard({ article }: Props) {
  const locale = getLocale();
  const Chevron = locale === 'ar' ? ChevronLeftIcon : ChevronRightIcon;
  const title = localized(article.title, locale);
  const excerpt = localized(article.excerpt, locale);

  return (
    <Link
      href={`/help/articles/${article.slug}`}
      className="card card--lg group flex items-start gap-4 transition-colors hover:border-coral"
    >
      <div className="min-w-0 flex-1">
        <h3 className="text-ink-900 text-base font-semibold leading-snug">
          {title}
        </h3>
        {excerpt ? (
          <p className="text-ink-700 mt-1.5 text-sm leading-relaxed">
            {excerpt}
          </p>
        ) : null}
      </div>
      <Chevron
        className="text-ink-500 group-hover:text-coral mt-1 size-4 shrink-0 transition-colors"
        aria-hidden
      />
    </Link>
  );
}
