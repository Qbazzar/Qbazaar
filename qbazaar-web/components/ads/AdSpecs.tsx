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
          <dl className="mt-5 [display:grid] grid-cols-1 gap-5 qb-tablet:grid-cols-2 qb-desktop:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]">
            {specs.map((spec) => (
              <div key={spec.key} className="flex min-w-0 flex-col gap-1">
                <dt className="text-qb-caption text-qb-ink-subtle">{spec.label}</dt>
                {/* Free text is in the seller's script; isolating it keeps numbers aligned with the page. */}
                <dd className="text-qb-body-sm font-medium break-words text-qb-ink">
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
          <ul className="mt-5 [display:grid] grid-cols-1 gap-[18px] qb-desktop:grid-cols-[repeat(auto-fill,minmax(240px,1fr))]">
            {features.map((feature) => (
              <li key={feature} className="flex items-start gap-2.5 text-qb-caption text-qb-ink-feature">
                <span aria-hidden="true" className="flex size-5 shrink-0 items-center justify-center rounded-full bg-qb-tick text-qb-surface">
                  <Check className="size-3" strokeWidth={3} />
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
