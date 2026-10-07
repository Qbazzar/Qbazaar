'use client';

import { tPlural } from '@/lib/i18n/plural';
import { t } from '@/lib/i18n/messages';

import { useResultsFocusTarget } from './results-focus';

interface ResultsHeadingProps {
  /** The request behind the results is running; the count and the focus wait for it. */
  loading: boolean;
  /** Matching ads, announced once loaded; undefined when there is no count to give. */
  total?: number;
}

/**
 * The hidden "Results" heading that takes the focus after a filter change,
 * and a status line announcing how many ads matched: the URL changes only in
 * its query, so the page title and the route announcer stay silent.
 */
export function ResultsHeading({ loading, total }: ResultsHeadingProps) {
  const ref = useResultsFocusTarget<HTMLHeadingElement>({ ready: !loading });
  return (
    <>
      <h2 ref={ref} tabIndex={-1} className="sr-only">
        {t('catalog.results_heading', 'النتائج')}
      </h2>
      <p role="status" className="sr-only">
        {loading || total === undefined ? '' : tPlural('catalog.results_count', total)}
      </p>
    </>
  );
}
