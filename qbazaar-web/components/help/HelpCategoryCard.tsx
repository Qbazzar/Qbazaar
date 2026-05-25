/**
 * Help category card — DynamicIcon + name + articles count.
 *
 * Mirrors QBFront/help.html's `.topic-card` but reuses our DynamicIcon
 * registry so admins can pick any Lucide name in the CMS.
 */
import Link from 'next/link';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';

import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { t } from '@/lib/i18n/messages';
import { getLocale, localized } from '@/lib/i18n/locale';
import type { HelpCategory } from '@/lib/api/types';

interface Props {
  category: HelpCategory;
}

export function HelpCategoryCard({ category }: Props) {
  const locale = getLocale();
  const Chevron = locale === 'ar' ? ChevronLeftIcon : ChevronRightIcon;
  const name = localized(category.name, locale);
  const count = category.articles_count ?? 0;

  return (
    <Link href={`/help/c/${category.slug}`} className="topic-card">
      <span className="help-icon-wrap">
        <DynamicIcon name={category.icon ?? 'BookOpen'} className="size-5" />
      </span>
      <div className="min-w-0">
        <div className="topic-card__title">{name}</div>
        <div className="topic-card__count">
          {t(
            'help.articles_count_arrow',
            { count: String(count) },
            `${count} ${count === 1 ? 'article' : 'articles'} →`,
          )}
        </div>
      </div>
      <Chevron
        className="text-ink-500 ms-auto size-4 self-center"
        aria-hidden
      />
    </Link>
  );
}
