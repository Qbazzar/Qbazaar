'use client';

import { CircleAlert, LifeBuoy } from 'lucide-react';

import { Button } from '@/components/design-system/Button';
import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import { useHelpCategoriesQuery } from '@/lib/queries/help';
import { tPlural } from '@/lib/i18n/intl';
import { t } from '@/lib/i18n/messages';

import { HelpCategoryCard } from './HelpCategoryCard';
import { HELP_TOPIC_GRID, HelpTopicsSkeleton } from './HelpSkeletons';

export function HelpTopics() {
  const { data: categories, isError, refetch, isFetching } = useHelpCategoriesQuery();

  if (!categories && isError) {
    return (
      <StatePanel
        icon={<StateIcon icon={CircleAlert} tone="muted" />}
        title={t('common.error')}
        action={
          <Button size="sm" onClick={() => refetch()} disabled={isFetching}>
            {t('common.retry')}
          </Button>
        }
      />
    );
  }

  if (!categories) return <HelpTopicsSkeleton />;

  if (categories.length === 0) {
    return <StatePanel icon={<StateIcon icon={LifeBuoy} />} title={t('help.no_categories')} />;
  }

  return (
    <ul aria-label={t('help.categories_title')} className={HELP_TOPIC_GRID}>
      {categories.map((category) => (
        <li key={category.id}>
          <HelpCategoryCard category={category} />
        </li>
      ))}
    </ul>
  );
}

/** "5 topics · 15 articles" under the help center title; a blank line keeps its height while loading. */
export function HelpTopicsSummary() {
  const { data: categories } = useHelpCategoriesQuery();
  if (!categories) return '\u00a0';

  const articles = categories.reduce((sum, category) => sum + (category.articles_count ?? 0), 0);
  return `${tPlural('help.topic_count', categories.length)} · ${tPlural('help.article_count', articles)}`;
}
