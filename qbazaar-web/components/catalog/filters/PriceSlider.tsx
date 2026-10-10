'use client';

import type { CSSProperties } from 'react';
import { DirectionProvider } from '@base-ui/react/direction-provider';
import { Slider } from '@base-ui/react/slider';

import '../catalog-tokens.css';

import { formatAdPrice } from '@/lib/ads/format';
import { dirFor, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { LAST_STOP, PRICE_STOPS, maxStopIndex, minStopIndex, rangeFromStops } from './price-scale';

interface PriceSliderProps {
  min: number | null;
  max: number | null;
  onChange: (next: { priceMin: number | null; priceMax: number | null }) => void;
}

/** The reference's polish.css replaces the handle's inline shadow with the soft token. */
const thumb = cn(
  'size-4 rounded-full border-[3px] border-qb-brand bg-qb-surface shadow-qb-soft',
  'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active has-[:focus-visible]:outline-solid',
);

/**
 * The price range slider of the filter card (250:4405, the category.html
 * aside): a peach track, the chosen range in orange between two handles, and
 * each handle's price under it. Each handle is a native range input, so the
 * arrow, Page and Home/End keys work and the price is announced.
 */
export function PriceSlider({ min, max, onChange }: PriceSliderProps) {
  const locale = getLocale();
  const indices = [minStopIndex(min), maxStopIndex(max)];
  const priceLabel = (price: number) => formatAdPrice({ price, price_type: 'fixed' }, locale);
  const labels = [priceLabel(min ?? 0), max === null ? `${priceLabel(PRICE_STOPS[LAST_STOP])}+` : priceLabel(max)];
  const ariaLabels = [t('catalog.filters.price_min_label', 'أقل سعر'), t('catalog.filters.price_max_label', 'أعلى سعر')];

  const change = (next: number | readonly number[]) => {
    if (typeof next === 'number') return;
    const [nextMin, nextMax] = next;
    const range = rangeFromStops(nextMin, nextMax);
    // Only the moved handle changes, so a typed price from the URL survives a move of the other one.
    onChange({
      priceMin: nextMin === indices[0] ? min : range.priceMin,
      priceMax: nextMax === indices[1] ? max : range.priceMax,
    });
  };

  return (
    <DirectionProvider direction={dirFor(locale)}>
      <Slider.Root value={indices} min={0} max={LAST_STOP} step={1} onValueChange={change} className="pt-1.5">
        <Slider.Control className="flex h-4 cursor-pointer touch-none items-center select-none">
          <Slider.Track className="h-[5px] w-full rounded-qb-sm bg-(--color-qb-range-track)">
            <Slider.Indicator className="h-full bg-qb-brand" />
            {indices.map((_, index) => (
              <Slider.Thumb
                key={index}
                index={index}
                className={thumb}
                getAriaLabel={() => ariaLabels[index]}
                getAriaValueText={() => labels[index]}
              />
            ))}
          </Slider.Track>
        </Slider.Control>
        <div aria-hidden="true" className="relative mt-2.5 h-5 font-qb text-qb-caption font-medium whitespace-nowrap text-qb-ink">
          {indices.map((stop, index) => (
            <span
              key={index}
              style={{ '--stop': (stop / LAST_STOP) * 100 } as CSSProperties}
              className="absolute start-[calc(var(--stop)*1%)] -translate-x-[calc(var(--stop)*1%)] rtl:translate-x-[calc(var(--stop)*1%)]"
            >
              {labels[index]}
            </span>
          ))}
        </div>
      </Slider.Root>
    </DirectionProvider>
  );
}
