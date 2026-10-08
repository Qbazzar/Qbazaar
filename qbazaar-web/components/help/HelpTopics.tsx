import { LifeBuoy } from 'lucide-react';

import { StateIcon, StatePanel } from '@/components/design-system/StatePanel';
import type { HelpCategory } from '@/lib/api/types';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';

import { HelpCategoryCard } from './HelpCategoryCard';

/** Help topics as all-categories tiles: 2 / 3 / 4 columns (185:6576, 536:32620, 621:27193). */
export function HelpTopics({ categories }: { categories: HelpCategory[] }) {
  if (categories.length === 0) {
    return <StatePanel icon={<StateIcon icon={LifeBuoy} />} title={t('help.no_categories')} />;
  }

  return (
    <ul
      aria-label={t('help.categories_title')}
      className="grid grid-cols-2 gap-2 qb-tablet:grid-cols-3 qb-tablet:gap-3 qb-desktop:grid-cols-4 qb-desktop:gap-4"
    >
      {categories.map((category) => (
        <li key={category.id}>
          <HelpCategoryCard category={category} />
        </li>
      ))}
    </ul>
  );
}

/** "5 topics · 15 articles" under the help center title. */
export function helpTopicsSummary(categories: HelpCategory[]): string {
  const articles = categories.reduce((sum, category) => sum + (category.articles_count ?? 0), 0);
  return `${tPlural('help.topic_count', categories.length)} · ${tPlural('help.article_count', articles)}`;
}
