'use client';

import { useState } from 'react';
import Image from 'next/image';
import { ArrowLeft, ArrowRight, ImageIcon, ImageOff } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const arrowButton = cn(
  'absolute top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-qb-surface text-qb-ink shadow-qb-raised transition-colors hover:bg-qb-hover',
  focusRing,
);

/** Product-page gallery of preview.html: one photo, arrows and the "1/4" counter. */
export function PreviewGallery({ title, urls }: { title: string; urls: readonly string[] }) {
  const [index, setIndex] = useState(0);
  const total = urls.length;
  const current = Math.min(index, Math.max(total - 1, 0));

  if (total === 0) {
    return (
      <div className="flex h-[clamp(190px,40vw,380px)] flex-col items-center justify-center gap-2 rounded-qb-xl bg-qb-line text-qb-ink-subtle">
        <Icon icon={ImageOff} size="lg" />
        <span className="text-qb-caption">{t('post_ad.preview.no_photos')}</span>
      </div>
    );
  }

  const go = (step: number) => setIndex((current + step + total) % total);

  return (
    <div
      role="group"
      aria-roledescription={t('post_ad.preview.gallery')}
      aria-label={title}
      className="relative h-[clamp(190px,40vw,380px)] overflow-hidden rounded-qb-xl bg-qb-line"
    >
      <Image
        key={urls[current]}
        src={urls[current]}
        alt={t('post_ad.preview.photo_alt', { title, n: current + 1, total })}
        fill
        sizes="(min-width: 1001px) 877px, 100vw"
        className="object-cover"
      />
      {total > 1 ? (
        <>
          <button type="button" onClick={() => go(-1)} aria-label={t('post_ad.preview.previous_photo')} className={cn(arrowButton, 'start-4')}>
            <Icon icon={ArrowLeft} size="md" flipInRtl />
          </button>
          <button type="button" onClick={() => go(1)} aria-label={t('post_ad.preview.next_photo')} className={cn(arrowButton, 'end-4')}>
            <Icon icon={ArrowRight} size="md" flipInRtl />
          </button>
        </>
      ) : null}
      <span
        aria-live="polite"
        className="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-[7px] rounded-qb-sm bg-qb-icon/75 px-3.5 py-1.5 text-qb-label text-white"
      >
        <Icon icon={ImageIcon} size="sm" className="size-[15px]" />
        <span aria-hidden="true" dir="ltr">
          {current + 1}/{total}
        </span>
        <span className="sr-only">{t('post_ad.preview.photo_count', { n: current + 1, total })}</span>
      </span>
    </div>
  );
}
