import type { ReactNode } from 'react';
import Image from 'next/image';
import { ImageIcon, ImageOff } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Media } from '@/lib/api/types';

export interface AdPhotoProps {
  image: Media | null;
  /** `sizes` of the rendered box, so the browser picks the right file. */
  sizes: string;
  /** Badges laid over the photo (price, favourite). */
  children?: ReactNode;
  /** Small boxes (the chat header) show the icon without the "Without Photo" text. */
  compact?: boolean;
  /** My Ads draws an ad without photos as the dashed "Without Photo" tile. */
  dashedWhenEmpty?: boolean;
  className?: string;
}

/**
 * Listing photo of the account rows, or the "Without Photo" tile of a draft
 * (518:20536). Decorative: the row's title names the ad. The box is sized by
 * the caller, so nothing shifts while the image loads.
 */
export function AdPhoto({ image, sizes, children, compact = false, dashedWhenEmpty = false, className }: AdPhotoProps) {
  const src = image?.sizes.medium || image?.sizes.thumbnail || image?.url;
  if (!src && dashedWhenEmpty) {
    return (
      <div
        className={cn(
          'flex shrink-0 flex-col items-center justify-center gap-1.5 border-2 border-dashed border-qb-acct-check-ring bg-qb-acct-tile text-qb-acct-placeholder',
          className,
        )}
      >
        <ImageIcon aria-hidden="true" className="size-6" strokeWidth={1.6} />
        <span className="text-qb-label">{t('account.my_ads.without_photo')}</span>
      </div>
    );
  }
  return (
    <div className={cn('relative shrink-0 overflow-hidden bg-qb-fill', !src && 'border border-qb-line', className)}>
      {src ? (
        <Image src={src} alt="" fill sizes={sizes} className="object-cover" />
      ) : (
        <span className="flex size-full flex-col items-center justify-center gap-2 text-qb-ink-disabled">
          <ImageOff className={compact ? 'size-5' : 'size-8'} aria-hidden="true" />
          {compact ? null : <span className="text-qb-h5 text-qb-ink-disabled">{t('account.my_ads.without_photo')}</span>}
        </span>
      )}
      {children}
    </div>
  );
}
