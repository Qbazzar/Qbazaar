'use client';

/**
 * Ad detail photo slider, as product.html draws it: one photo at a time in a
 * r16 frame min(56vw, 420px) tall, two round arrows that cycle endlessly
 * (photos.js shows index modulo the count), a "1/7" counter and the
 * favourite heart. Only the arrows change the photo: the reference has no
 * drag, swipe or full-screen view.
 */
import { useEffect, useState, type ReactNode } from 'react';
import Image from 'next/image';
import useEmblaCarousel from 'embla-carousel-react';
import { ArrowLeft, ArrowRight, Camera, type LucideIcon } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { formatNumber } from '@/lib/i18n/format';
import { dirFor, getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Media } from '@/lib/api/types';

interface AdGalleryProps {
  images: Media[];
  /** The ad title: the base of every photo's alt text. */
  alt: string;
  /** Favourite toggle on the photo; omitted for the ad's owner. */
  favorite?: ReactNode;
  className?: string;
}

const frame = 'relative h-[min(56vw,420px)] overflow-hidden rounded-qb-xl bg-qb-line';

const slideSizes = '(min-width: 1001px) 877px, (min-width: 601px) 401px, 100vw';

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function slideLabel(current: number, total: number): string {
  const locale = getLocale();
  return t('ads.detail.slide', { current: formatNumber(current, locale), total: formatNumber(total, locale) });
}

export function AdGallery({ images, alt, favorite, className }: AdGalleryProps) {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: true, watchDrag: false, direction: dirFor(getLocale()) });
  const [selected, setSelected] = useState(0);
  const count = images.length;

  useEffect(() => {
    if (!emblaApi) return;
    const onSelect = () => setSelected(emblaApi.selectedScrollSnap());
    onSelect();
    emblaApi.on('select', onSelect).on('reInit', onSelect);
    return () => {
      emblaApi.off('select', onSelect).off('reInit', onSelect);
    };
  }, [emblaApi]);

  return (
    <section
      aria-roledescription={t('media.carousel')}
      aria-label={t('ads.detail.gallery', { title: alt })}
      className={cn(frame, className)}
    >
      {count === 0 ? (
        <p className="flex h-full items-center justify-center text-qb-caption text-qb-ink-subtle">{t('media.no_image')}</p>
      ) : (
        <div ref={emblaRef} className="h-full overflow-hidden">
          <div className="flex h-full">
            {images.map((media, index) => (
              <div
                key={media.id}
                role="group"
                aria-roledescription={t('media.slide')}
                aria-label={slideLabel(index + 1, count)}
                inert={index !== selected}
                className="relative h-full min-w-0 flex-[0_0_100%]"
              >
                <Image
                  src={media.sizes.large || media.url}
                  alt={`${alt} — ${index + 1}`}
                  fill
                  sizes={slideSizes}
                  preload={index === 0}
                  fetchPriority={index === 0 ? 'high' : undefined}
                  loading={index === 1 ? 'eager' : undefined}
                  className="object-cover"
                />
              </div>
            ))}
          </div>
        </div>
      )}

      {count > 1 ? (
        <>
          <ArrowButton
            icon={ArrowLeft}
            label={t('media.prev')}
            onClick={() => emblaApi?.scrollPrev(prefersReducedMotion())}
            className="start-4"
          />
          <ArrowButton
            icon={ArrowRight}
            label={t('media.next')}
            onClick={() => emblaApi?.scrollNext(prefersReducedMotion())}
            className="end-4"
          />
          <Counter current={selected + 1} total={count} />
          <p aria-live="polite" className="sr-only">
            {slideLabel(selected + 1, count)}
          </p>
        </>
      ) : null}

      {favorite ? <div className="absolute end-4 top-4 z-10">{favorite}</div> : null}
    </section>
  );
}

interface ArrowButtonProps {
  icon: LucideIcon;
  label: string;
  onClick: () => void;
  className?: string;
}

/** 44 px white circle with a dark 2 px arrow; the slider wraps, so both always work. */
function ArrowButton({ icon, label, onClick, className }: ArrowButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      className={cn(
        'absolute top-1/2 z-10 flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-qb-surface text-qb-ink-body shadow-qb-raised',
        focusRing,
        className,
      )}
    >
      <Icon icon={icon} flipInRtl strokeWidth={2} className="size-5" />
    </button>
  );
}

function Counter({ current, total }: { current: number; total: number }) {
  const locale = getLocale();
  return (
    <p
      aria-hidden="true"
      className="absolute inset-x-0 bottom-4 mx-auto flex w-fit items-center gap-1 rounded-qb-sm bg-qb-icon/75 px-3.5 py-1.5 text-qb-label text-qb-surface"
    >
      <Camera className="size-3.5" strokeWidth={1.8} />
      <span dir="ltr">
        {formatNumber(current, locale)}/{formatNumber(total, locale)}
      </span>
    </p>
  );
}
