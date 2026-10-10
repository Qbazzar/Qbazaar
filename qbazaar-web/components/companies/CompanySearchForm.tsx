import Form from 'next/form';
import { Search } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { COMPANY_QUERY_MAX_LENGTH } from '@/lib/companies/directory';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const INPUT_ID = 'company-search';

/**
 * The directory's search bar (companies.html): a white r14 bar with 8 px
 * padding and a soft shadow, the search field, and the 125 x 43 orange
 * "Search" button (phones submit with the keyboard's search key). A plain
 * GET form, so it works before the page is interactive; once it is,
 * `next/form` turns the submit into a client-side navigation. A new search
 * starts again on page 1.
 */
export function CompanySearchForm({ query }: { query: string }) {
  return (
    <Form
      action="/companies"
      role="search"
      className={cn(
        'flex min-h-[67px] items-center gap-3 rounded-[14px] bg-qb-surface p-2 ps-4 shadow-qb-search',
        'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active has-[input:focus-visible]:outline-solid',
      )}
    >
      <label htmlFor={INPUT_ID} className="sr-only">
        {t('companies.search_label')}
      </label>
      <Icon icon={Search} className="size-5 text-qb-ink-subtle" />
      <input
        id={INPUT_ID}
        name="q"
        type="search"
        defaultValue={query}
        maxLength={COMPANY_QUERY_MAX_LENGTH}
        placeholder={t('companies.search_placeholder')}
        enterKeyHint="search"
        autoComplete="off"
        className="h-[51px] min-w-0 flex-1 bg-transparent font-qb text-qb-body text-qb-ink-title outline-none placeholder:text-qb-ink-subtle"
      />
      <button
        type="submit"
        className={cn(
          'hidden h-[43px] shrink-0 cursor-pointer items-center rounded-qb-md bg-qb-brand px-[34px] font-qb text-qb-body font-semibold text-qb-on-brand transition-colors hover:bg-qb-brand-hover qb-tablet:inline-flex',
          focusRing,
        )}
      >
        {t('companies.search')}
      </button>
    </Form>
  );
}
