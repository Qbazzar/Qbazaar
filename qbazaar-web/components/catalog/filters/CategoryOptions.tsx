import { getLocale, localized } from '@/lib/i18n/locale';
import { findCategoryBySlug } from '@/store/categories';
import type { CategoryNode } from '@/lib/api/types';

import { subtreeCount } from './facet-counts';
import { OptionList, type FilterOption } from './OptionList';

interface CategoryOptionsProps {
  name: string;
  labelledBy: string;
  categories: CategoryNode[] | undefined;
  value: string | null;
  onChange: (slug: string) => void;
  /** Search facet counts by category slug. */
  counts?: Record<string, number> | null;
}

/**
 * Main categories as radio rows. A selected sub-category stays listed first,
 * so the current filter is always visible.
 */
export function CategoryOptions({ name, labelledBy, categories, value, onChange, counts }: CategoryOptionsProps) {
  const locale = getLocale();
  const roots = categories ?? [];
  const toOption = (node: CategoryNode): FilterOption => ({
    value: node.slug,
    label: localized(node.name, locale),
    count: subtreeCount(node, counts),
  });

  const selected = value ? findCategoryBySlug(roots, value) : null;
  const options = roots.map(toOption);
  if (selected && !roots.includes(selected)) options.unshift(toOption(selected));

  return <OptionList name={name} labelledBy={labelledBy} options={options} value={value} onChange={onChange} />;
}
