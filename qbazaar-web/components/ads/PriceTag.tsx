/**
 * Price renderer with `price_type` awareness.
 *
 * The marketplace supports four flavours of price (`fixed`, `negotiable`,
 * `free`, `contact`) and the UI string differs for each — keeping the
 * formatter in one component means every surface (card, detail, MyAds row)
 * shows exactly the same wording.
 *
 * Pure presentational + locale-aware via `Intl.NumberFormat`. Server-friendly
 * (no hooks, no client-only APIs).
 */
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { PriceType } from '@/lib/api/types';

interface Props {
  price: number | null;
  priceType: PriceType;
  currency?: 'QAR';
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const SIZE_CLS: Record<NonNullable<Props['size']>, string> = {
  sm: 'text-base',
  md: 'text-lg',
  lg: 'font-display text-4xl md:text-5xl italic',
};

const CURRENCY_LABEL_AR = 'ر.ق';
const CURRENCY_LABEL_EN = 'QAR';

function formatAmount(price: number, locale: 'ar' | 'en'): string {
  const lang = locale === 'ar' ? 'ar-EG' : 'en-US';
  return new Intl.NumberFormat(lang, { maximumFractionDigits: 0 }).format(price);
}

export function PriceTag({
  price,
  priceType,
  currency = 'QAR',
  size = 'md',
  className,
}: Props) {
  const locale = getLocale();
  const cls = cn('text-coral inline-flex items-baseline gap-1.5', SIZE_CLS[size], className);

  if (priceType === 'free') {
    return (
      <span className={cls}>{t('ads.price.free', 'مجاناً')}</span>
    );
  }
  if (priceType === 'contact' || price == null) {
    return (
      <span className={cn(cls, 'text-ink-700')}>
        {t('ads.price.contact', 'تواصل للسعر')}
      </span>
    );
  }

  const amount = formatAmount(price, locale);
  const unit = locale === 'ar' ? CURRENCY_LABEL_AR : CURRENCY_LABEL_EN;
  return (
    <span className={cls}>
      <span>{amount}</span>
      <span
        className={cn(
          'text-ink-500 font-normal',
          size === 'lg' ? 'text-base' : 'text-xs',
        )}
      >
        {currency === 'QAR' ? unit : currency}
      </span>
      {priceType === 'negotiable' ? (
        <span className="text-ink-500 ms-1 text-xs font-normal">
          {t('ads.price.negotiable', 'قابل للتفاوض')}
        </span>
      ) : null}
    </span>
  );
}
