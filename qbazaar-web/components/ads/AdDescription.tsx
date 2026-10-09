import { useId } from 'react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { detailCard, detailCardMain, detailCardTitle } from './detail-card';

interface AdDescriptionProps {
  text: string;
  className?: string;
}

/** "Description" panel of the ad detail: the whole text, 15 px on a 1.7 line height (product.html). */
export function AdDescription({ text, className }: AdDescriptionProps) {
  const titleId = useId();

  return (
    <section aria-labelledby={titleId} className={cn(detailCard, detailCardMain, className)}>
      <h2 id={titleId} className={detailCardTitle}>
        {t('ads.detail.description')}
      </h2>
      <p dir="auto" className="mt-4 text-qb-body-sm leading-[1.7] break-words whitespace-pre-line text-qb-ink-faint">
        {text}
      </p>
    </section>
  );
}
