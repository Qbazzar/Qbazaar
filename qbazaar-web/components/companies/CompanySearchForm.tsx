import Form from 'next/form';
import { Search } from 'lucide-react';

import { buttonVariants } from '@/components/design-system/Button';
import { Icon } from '@/components/design-system/Icon';
import { COMPANY_QUERY_MAX_LENGTH } from '@/lib/companies/directory';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const INPUT_ID = 'company-search';

/**
 * The directory's search bar. A plain GET form, so it works before the page
 * is interactive; once it is, `next/form` turns the submit into a client-side
 * navigation. A new search starts again on page 1.
 */
export function CompanySearchForm({ query }: { query: string }) {
  return (
    <Form
      action="/companies"
      role="search"
      className={cn(
        'flex h-11 items-center gap-2 rounded-[14px] border border-qb-line bg-qb-surface ps-[15px] pe-1.5 shadow-qb-card',
        'focus-within:border-qb-brand focus-within:ring-2 focus-within:ring-qb-brand/20',
        'qb-tablet:h-14 qb-tablet:gap-3 qb-tablet:rounded-qb-xl qb-tablet:ps-6 qb-desktop:pe-2.5',
      )}
    >
      <label htmlFor={INPUT_ID} className="sr-only">
        {t('companies.search_label')}
      </label>
      <Icon icon={Search} size="md" className="text-qb-ink-subtle qb-tablet:size-6" />
      <input
        id={INPUT_ID}
        name="q"
        type="search"
        defaultValue={query}
        maxLength={COMPANY_QUERY_MAX_LENGTH}
        placeholder={t('companies.search_placeholder')}
        enterKeyHint="search"
        autoComplete="off"
        className="h-full min-w-0 flex-1 bg-transparent font-qb text-qb-caption text-qb-ink outline-none placeholder:text-qb-placeholder qb-tablet:text-qb-body qb-desktop:text-qb-h5"
      />
      {/* The phone frame has no button: the keyboard's search key submits. */}
      <button
        type="submit"
        className={cn(buttonVariants({ size: 'sm' }), 'hidden h-10 rounded-qb-lg px-[22px] text-qb-body qb-tablet:inline-flex qb-desktop:rounded-qb-md qb-desktop:px-6')}
      >
        {t('companies.search')}
      </button>
    </Form>
  );
}
