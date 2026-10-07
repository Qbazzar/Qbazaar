'use client';

/**
 * QBFront-styled listing card — wraps the prototype's `.listing-card`
 * structure around our `AdSummary` data. Used by the home feed, search
 * results, and similar-ads strips so they match QBFront pixel-for-pixel.
 *
 * The save heart wires into the FavoriteButton — but rendered as a
 * standalone `.save-btn` so the visual matches the prototype.
 */
import Link from 'next/link';
import { FavoriteButton } from '@/components/ads/FavoriteButton';
import { BlurHashImage } from '@/components/upload/BlurHashImage';
import { localized, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { AdSummary } from '@/lib/api/types';
import { formatAdAge, formatAdPrice, placeLabel } from '@/lib/ads/format';

interface Props {
  ad: AdSummary;
  showFavorite?: boolean;
  className?: string;
}

export function QbfListingCard({ ad, showFavorite = true, className }: Props) {
  const locale = getLocale();
  const image = ad.primary_image;
  return (
    <Link href={`/ads/${ad.id}`} className={`listing-card ${className ?? ''}`.trim()}>
      <div className="listing-card__media">
        {image ? (
          <BlurHashImage
            src={image.sizes.medium || image.url}
            alt={ad.title}
            blurhash={image.blurhash}
            aspect="4 / 3"
            className="w-full"
            sizes="(min-width: 1024px) 300px, (min-width: 640px) 50vw, 100vw"
          />
        ) : (
          <div
            className="flex h-full w-full items-center justify-center text-xs text-muted"
            aria-hidden="true"
          >
            {t('media.no_image', 'بدون صورة')}
          </div>
        )}
        <div className="price-pill">{formatAdPrice(ad, locale)}</div>
        {showFavorite ? (
          <FavoriteButton adId={ad.id} size="sm" className="save-btn" />
        ) : null}
      </div>
      <div className="listing-card__body">
        <div className="listing-card__title">{ad.title}</div>
        <div className="listing-card__meta">
          <span className="meta-item">
            <svg
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M12 22s7-7 7-13a7 7 0 1 0-14 0c0 6 7 13 7 13z" />
              <circle cx="12" cy="9" r="2.5" />
            </svg>
            {placeLabel(ad.location_slug, locale)}
          </span>
          {ad.published_at ? (
            <>
              <span>·</span>
              <span>{formatAdAge(ad.published_at, locale)}</span>
            </>
          ) : null}
        </div>
      </div>
    </Link>
  );
}
