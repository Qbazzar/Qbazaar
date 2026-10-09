'use client';

import { Check } from 'lucide-react';

import type { PromotionOffer, PromotionType } from '@/lib/api/commerce-types';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { tPlural } from '@/lib/i18n/plural';
import { formatMoney } from '@/lib/orders/money';
import { usePromotionOffersQuery } from '@/lib/queries/promotions';
import { cn } from '@/lib/utils';
import { usePostAdStore } from '@/store/post-ad';

import { FormSection } from './FormParts';
import { focusWithinRow, promotionRow } from './promotion-row';

import '@/styles/design-tokens-sell.css';

/** The admin-priced catalogue with the seller's ticks; no offers while it loads or when it fails. */
export function usePromotionChoices() {
  const { data } = usePromotionOffersQuery();
  const chosen = usePostAdStore((state) => state.promotions);
  const toggle = usePostAdStore((state) => state.togglePromotion);
  return { offers: data ?? [], chosen, toggle };
}

export function promotionTitle(type: PromotionType): string {
  return t(`orders.promotion.types.${type}`);
}


/** The 22 px tick box of the promotion rows; its ring darkens under the pointer (CheckBox 367:15366). */
function TickBox({ checked, className }: { checked: boolean; className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'flex size-[22px] shrink-0 items-center justify-center rounded-qb-xs border-2 transition-colors',
        checked
          ? 'border-qb-brand bg-qb-brand text-qb-on-brand'
          : 'border-(--color-qb-check-ring) group-hover:border-(--color-qb-check-ring-hover)',
        className,
      )}
    >
      {checked ? <Check className="size-[13px]" strokeWidth={3} /> : null}
    </span>
  );
}

function PromotionCheckbox({ offer, checked, onToggle }: { offer: PromotionOffer; checked: boolean; onToggle: () => void }) {
  return <input type="checkbox" checked={checked} onChange={onToggle} className="sr-only" aria-describedby={`promotion-${offer.type}-body`} />;
}

const NOTE_ID = 'post-ad-promotions-note';

function PayLaterNote({ className }: { className?: string }) {
  return (
    <p id={NOTE_ID} className={cn('text-qb-label leading-[1.6] text-qb-ink-subtle', className)}>
      {t('post_ad.promote.pay_later')}
    </p>
  );
}

/**
 * "Increase Sales Opportunities: Highlight your ad" of add-ads.html: one row
 * per promotion, each ticked on its own. Promotions are bought for live ads,
 * so the ticks travel with the ad to the publish step and its confirmation.
 */
export function HighlightSection() {
  const { offers, chosen, toggle } = usePromotionChoices();
  if (offers.length === 0) return null;

  return (
    <FormSection id="post-ad-section-promote" title={t('post_ad.promote.title')}>
      <div className="flex flex-col gap-3.5" role="group" aria-labelledby="post-ad-section-promote" aria-describedby={NOTE_ID}>
        {offers.map((offer) => {
          const checked = chosen.includes(offer.type);
          return (
            <label key={offer.type} className={promotionRow.shell(checked)}>
              <PromotionCheckbox offer={offer} checked={checked} onToggle={() => toggle(offer.type)} />
              <TickBox checked={checked} />
              <span className="min-w-0 flex-1">
                <span className={promotionRow.title}>{promotionTitle(offer.type)}</span>
                <span id={`promotion-${offer.type}-body`} className={promotionRow.body}>
                  {t(`orders.promotion.types.${offer.type}_body`)}
                </span>
              </span>
              <span className={promotionRow.price}>{formatMoney(offer.price, offer.currency)}</span>
            </label>
          );
        })}
      </div>
      <PayLaterNote className="mt-3.5" />
    </FormSection>
  );
}

/**
 * The "Featured Ad / Duration / Price" table beside "Your Ad" on publish.html
 * (355:7297, 532:23641); on phones each promotion is a card with its
 * duration and price under it (638:36533).
 */
export function FeaturedAdTable({ offers, chosen, onToggle }: { offers: PromotionOffer[]; chosen: PromotionType[]; onToggle: (type: PromotionType) => void }) {
  const locale = getLocale();
  return (
    <fieldset className="min-w-0 flex-1 px-[26px] pb-[26px] qb-tablet:px-0 qb-tablet:pb-0" aria-describedby={NOTE_ID}>
      <legend className="sr-only">{t('post_ad.promote.featured')}</legend>
      <p aria-hidden="true" className="mb-4 text-qb-body-lg font-semibold text-qb-ink qb-tablet:hidden">
        {t('post_ad.promote.featured')}
      </p>
      <div
        aria-hidden="true"
        className="hidden grid-cols-[minmax(0,1fr)_130px_130px] border-b border-qb-line px-[26px] pt-[22px] pb-3.5 text-qb-body-sm text-qb-ink-faint qb-tablet:grid"
      >
        <span className="text-center">{t('post_ad.promote.featured')}</span>
        <span className="text-center">{t('post_ad.promote.duration')}</span>
        <span className="text-end">{t('post_ad.promote.price')}</span>
      </div>
      <div className="flex flex-col gap-3 qb-tablet:gap-0">
        {offers.map((offer) => {
          const checked = chosen.includes(offer.type);
          return (
            <label
              key={offer.type}
              className={cn(
                'group grid cursor-pointer grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 rounded-qb-lg border border-qb-line p-4',
                'qb-tablet:grid-cols-[minmax(0,1fr)_130px_130px] qb-tablet:gap-y-0 qb-tablet:rounded-none qb-tablet:border-0 qb-tablet:border-b qb-tablet:border-(--color-qb-row-divider) qb-tablet:px-[26px] qb-tablet:py-[18px]',
                focusWithinRow,
              )}
            >
              <PromotionCheckbox offer={offer} checked={checked} onToggle={() => onToggle(offer.type)} />
              <span className="col-span-2 flex items-start gap-3.5 qb-tablet:col-span-1">
                <TickBox checked={checked} className="mt-px" />
                <span className="min-w-0">
                  <span className="block text-qb-body font-medium text-qb-ink">{promotionTitle(offer.type)}</span>
                  <span id={`promotion-${offer.type}-body`} className="mt-0.5 block text-qb-label text-qb-ink-subtle">
                    {t(`orders.promotion.types.${offer.type}_body`)}
                  </span>
                </span>
              </span>
              <span className="text-qb-caption text-qb-ink-secondary qb-tablet:text-center qb-tablet:text-qb-body-sm">
                {tPlural('orders.promotion.duration', offer.duration_days, locale)}
              </span>
              <span className="text-end text-qb-body font-semibold whitespace-nowrap text-qb-ink">{formatMoney(offer.price, offer.currency)}</span>
            </label>
          );
        })}
      </div>
      <PayLaterNote className="mt-3.5 qb-tablet:mt-0 qb-tablet:px-[26px] qb-tablet:py-3.5" />
    </fieldset>
  );
}
