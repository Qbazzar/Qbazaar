import { useId } from 'react';
import { Check } from 'lucide-react';

import { buildAdSpecSheet } from '@/lib/ads/display';
import type { Locale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Ad } from '@/lib/api/types';

import { detailCard, detailCardMain, detailCardTitle } from './detail-card';

interface AdSpecsProps {
  ad: Pick<Ad, 'condition' | 'custom_fields' | 'category'>;
  locale: Locale;
}

/**
 * "Technical Data" (the category fields and the condition) and "Features &
 * Extras" (the yes/no fields that are set), each in its own panel. A panel
 * with nothing to show is left out.
 */
export function AdSpecs({ ad, locale }: AdSpecsProps) {
  const { specs, features } = buildAdSpecSheet(ad, locale);
  const specsTitleId = useId();
  const featuresTitleId = useId();

  return (
    <>
      {specs.length ? (
        <section aria-labelledby={specsTitleId} className={cn(detailCard, detailCardMain)}>
          <h2 id={specsTitleId} className={detailCardTitle}>
            {t('ads.detail.technical_data')}
          </h2>
          <dl className="mt-8 [display:grid] grid-cols-1 gap-y-8 qb-tablet:mt-4 qb-tablet:grid-cols-2 qb-tablet:gap-x-8 qb-tablet:gap-y-6 qb-desktop:grid-cols-4 qb-desktop:gap-x-6 qb-desktop:gap-y-[21px]">
            {specs.map((spec) => (
              <div key={spec.key} className="flex items-start gap-2 qb-tablet:flex-col qb-tablet:gap-[7px] qb-desktop:gap-[9px]">
                <dt className="w-1/2 shrink-0 text-qb-micro text-qb-ink-subtle qb-tablet:w-auto qb-desktop:text-qb-caption">{spec.label}</dt>
                {/* Free text is in the seller's script; isolating it keeps numbers aligned with the page. */}
                <dd className="min-w-0 text-qb-caption font-medium break-words text-qb-ink">
                  <bdi>{spec.value}</bdi>
                </dd>
              </div>
            ))}
          </dl>
        </section>
      ) : null}

      {features.length ? (
        <section aria-labelledby={featuresTitleId} className={cn(detailCard, detailCardMain)}>
          <h2 id={featuresTitleId} className={detailCardTitle}>
            {t('ads.detail.features')}
          </h2>
          <ul className="mt-6 [display:grid] grid-cols-1 gap-y-4 qb-tablet:mt-4 qb-tablet:gap-y-8 qb-desktop:mt-6 qb-desktop:grid-cols-3 qb-desktop:gap-x-6">
            {features.map((feature) => (
              <li key={feature} className="flex items-center gap-2 text-qb-caption text-qb-ink-subtle">
                <span aria-hidden="true" className="flex size-5 shrink-0 items-center justify-center rounded-full bg-qb-success text-qb-surface">
                  <Check className="size-3.5" strokeWidth={3} />
                </span>
                {feature}
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </>
  );
}
