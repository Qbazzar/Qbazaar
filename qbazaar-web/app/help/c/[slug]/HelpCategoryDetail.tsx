/**
 * Help topic page. No Figma frame: parent-category (69:467) with article rows
 * in place of the sub-category tiles.
 */
import { FileText } from 'lucide-react';

import { PageShell } from '@/components/design-system/PageShell';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { BrowseTopicsLink } from '@/components/help/BrowseTopicsLink';
import { HelpArticleCard } from '@/components/help/HelpArticleCard';
import { HelpContactCard } from '@/components/help/HelpContactCard';
import type { HelpCategoryWithArticles } from '@/lib/api/help';
import { localized } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

export function HelpCategoryDetail({ category }: { category: HelpCategoryWithArticles }) {
  const name = localized(category.name);
  const description = localized(category.description);
  const articles = category.articles ?? [];

  return (
    <PageShell
      breadcrumb={[
        { label: t('home.breadcrumb'), href: '/' },
        { label: t('help.title'), href: '/help' },
        { label: name },
      ]}
      title={name}
      meta={tPlural('help.article_count', articles.length)}
    >
      {description ? (
        <p className="mb-8 max-w-[806px] text-qb-body text-qb-ink-muted qb-desktop:text-qb-body-lg">{description}</p>
      ) : null}
      {articles.length === 0 ? (
        <StatePanel icon={<StateIcon icon={FileText} />} title={t('help.no_articles')} action={<BrowseTopicsLink />} />
      ) : (
        <ul aria-label={t('help.articles_in_category')} className="flex flex-col gap-4">
          {articles.map((article) => (
            <li key={article.id}>
              <HelpArticleCard article={article} />
            </li>
          ))}
        </ul>
      )}
      <HelpContactCard className="mt-10 qb-desktop:mt-12" />
    </PageShell>
  );
}
