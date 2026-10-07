import { LinkTile } from '@/components/design-system/LinkTile';
import { DynamicIcon } from '@/components/ui/dynamic-icon';
import { localized } from '@/lib/i18n/locale';
import { tPlural } from '@/lib/i18n/plural';
import type { HelpCategory } from '@/lib/api/types';

/** Help topic as an all-categories tile; admins pick the Lucide icon in the CMS. */
export function HelpCategoryCard({ category }: { category: HelpCategory }) {
  return (
    <LinkTile
      href={`/help/c/${category.slug}`}
      icon={<DynamicIcon name={category.icon ?? 'BookOpen'} strokeWidth={1.5} />}
      title={localized(category.name)}
      description={tPlural('help.article_count', category.articles_count ?? 0)}
    />
  );
}
