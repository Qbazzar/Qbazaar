/**
 * Tile for an ad summary — used by the home feed, search results, and the
 * "My Ads" page.
 *
 * Stateless + server-friendly (no hooks). Translated from the Bazzar mockup's
 * ListingCard with the warm coral palette and Instrument-Serif italic for the
 * price line. Saving / messaging affordances land in Sprint 7/8 — for now the
 * card is a plain link to `/ads/{id}`.
 */
import Link from 'next/link';
import { Card } from '@/components/ui/card';
import { BlurHashImage } from '@/components/upload/BlurHashImage';
import { PriceTag } from './PriceTag';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AdSummary } from '@/lib/api/types';

interface Props {
  ad: AdSummary;
  className?: string;
  /** When true (default), the whole card is a link. Useful in the MyAds rows. */
  asLink?: boolean;
  footer?: React.ReactNode;
}

export function AdCard({ ad, className, asLink = true, footer }: Props) {
  const image = ad.primary_image;
  const inner = (
    <Card
      size="sm"
      className={cn(
        'h-full overflow-hidden transition-all duration-200',
        asLink && 'group-hover:ring-coral group-hover:ring-2',
        className,
      )}
    >
      <div className="relative -mt-3 overflow-hidden">
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
            className="bg-cream-200 text-ink-500 flex w-full items-center justify-center text-xs"
            style={{ aspectRatio: '4 / 3' }}
          >
            {t('media.no_image', 'بدون صورة')}
          </div>
        )}
      </div>
      <div className="space-y-2 px-3">
        <h3 className="text-ink-900 line-clamp-2 text-sm font-medium leading-snug">
          {ad.title}
        </h3>
        <PriceTag price={ad.price} priceType={ad.price_type} size="md" />
        <p className="text-ink-500 text-xs">
          {ad.location_slug.replace(/-/g, ' ')}
        </p>
        {footer}
      </div>
    </Card>
  );

  if (!asLink) return inner;

  return (
    <Link
      href={`/ads/${ad.id}`}
      className="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-coral focus-visible:ring-offset-2"
    >
      {inner}
    </Link>
  );
}
