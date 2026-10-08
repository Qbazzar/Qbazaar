'use client';

/**
 * Ad detail photo slider (88:776 / 547:39923 / 623:29490): one photo at a
 * time with round arrows, a "1/7" counter and the favourite button on top.
 * Tapping the photo opens it full screen.
 */
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import Image from 'next/image';
import useEmblaCarousel from 'embla-carousel-react';
import { Dialog } from '@base-ui/react/dialog';
import { ArrowLeft, ArrowRight, Image as ImageIcon, X, type LucideIcon } from 'lucide-react';

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

const frame =
  'relative overflow-hidden bg-qb-line h-80 qb-tablet:h-[322px] qb-tablet:rounded-qb-2xl qb-tablet:shadow-qb-card qb-desktop:h-[502px]';

const slideSizes = '(min-width: 1001px) 907px, (min-width: 601px) 696px, 100vw';

function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function slideLabel(current: number, total: number): string {
  const locale = getLocale();
  return t('ads.detail.slide', { current: formatNumber(current, locale), total: formatNumber(total, locale) });
}

export function AdGallery({ images, alt, favorite, className }: AdGalleryProps) {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: false, direction: dirFor(getLocale()) });
  const [selected, setSelected] = useState(0);
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
  const slideButtons = useRef<(HTMLButtonElement | null)[]>([]);
  const returnIndex = useRef(0);
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

  const closeLightbox = useCallback(() => {
    // Keep the slider on the photo the visitor ended up on, and give focus back to it.
    if (lightboxIndex !== null) {
      returnIndex.current = lightboxIndex;
      emblaApi?.scrollTo(lightboxIndex, true);
      setSelected(lightboxIndex);
    }
    setLightboxIndex(null);
  }, [emblaApi, lightboxIndex]);

  if (count === 0) {
    return (
      <div className={cn(frame, 'flex items-center justify-center text-qb-caption text-qb-ink-secondary', className)}>
        {t('media.no_image')}
      </div>
    );
  }

  return (
    <section
      aria-roledescription={t('media.carousel')}
      aria-label={t('ads.detail.gallery', { title: alt })}
      className={cn(frame, className)}
    >
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
              <button
                ref={(element) => {
                  slideButtons.current[index] = element;
                }}
                type="button"
                onClick={() => setLightboxIndex(index)}
                aria-label={t('media.open_fullscreen')}
                className={cn('absolute inset-0 cursor-zoom-in', focusRing, 'focus-visible:-outline-offset-4')}
              >
                <Image
                  src={media.sizes.large || media.url}
                  alt={`${alt} — ${index + 1}`}
                  fill
                  sizes={slideSizes}
                  preload={index === 0}
                  fetchPriority={index === 0 ? 'high' : undefined}
                  className="object-cover"
                />
                {/* The design lays a light shade over every photo. */}
                <span aria-hidden="true" className="absolute inset-0 bg-qb-overlay/50" />
              </button>
            </div>
          ))}
        </div>
      </div>

      {count > 1 ? (
        <>
          <ArrowButton
            icon={ArrowLeft}
            label={t('media.prev')}
            atEnd={selected === 0}
            onClick={() => emblaApi?.scrollPrev(prefersReducedMotion())}
            className="start-3 qb-tablet:start-6"
          />
          <ArrowButton
            icon={ArrowRight}
            label={t('media.next')}
            atEnd={selected === count - 1}
            onClick={() => emblaApi?.scrollNext(prefersReducedMotion())}
            className="end-3 qb-tablet:end-6"
          />
          <Counter current={selected + 1} total={count} />
          <p aria-live="polite" className="sr-only">
            {slideLabel(selected + 1, count)}
          </p>
        </>
      ) : null}

      {favorite ? <div className="absolute end-4 top-4 z-10 qb-tablet:end-6 qb-tablet:top-6">{favorite}</div> : null}

      <Lightbox
        images={images}
        alt={alt}
        index={lightboxIndex}
        onIndexChange={setLightboxIndex}
        onClose={closeLightbox}
        returnFocus={() => slideButtons.current[returnIndex.current] ?? true}
      />
    </section>
  );
}

interface ArrowButtonProps {
  icon: LucideIcon;
  label: string;
  /** No photo further this way: the arrow fades but keeps its focus. */
  atEnd: boolean;
  onClick: () => void;
  className?: string;
}

function ArrowButton({ icon, label, atEnd, onClick, className }: ArrowButtonProps) {
  return (
    <button
      type="button"
      onClick={atEnd ? undefined : onClick}
      aria-disabled={atEnd || undefined}
      aria-label={label}
      className={cn(
        'absolute top-1/2 z-10 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-qb-surface text-qb-brand transition-opacity motion-reduce:transition-none',
        'qb-tablet:size-10 qb-desktop:size-12 aria-disabled:cursor-default aria-disabled:opacity-60',
        focusRing,
        className,
      )}
    >
      <Icon icon={icon} size="md" flipInRtl className="qb-desktop:size-6" />
    </button>
  );
}

function Counter({ current, total }: { current: number; total: number }) {
  const locale = getLocale();
  return (
    <p
      aria-hidden="true"
      className={cn(
        'absolute inset-x-0 bottom-4 mx-auto flex w-fit items-center gap-1.5 rounded-qb-md bg-qb-icon/45 px-3 py-1 text-qb-micro text-qb-surface',
        'qb-tablet:bottom-[13px] qb-tablet:px-4 qb-tablet:py-[7px] qb-tablet:text-qb-caption qb-desktop:bottom-5 qb-desktop:gap-2 qb-desktop:px-[18px] qb-desktop:py-[7px] qb-desktop:text-qb-h5',
      )}
    >
      <ImageIcon className="size-4 qb-desktop:size-5" strokeWidth={1.75} />
      <span dir="ltr">
        {formatNumber(current, locale)}/{formatNumber(total, locale)}
      </span>
    </p>
  );
}

interface LightboxProps {
  images: Media[];
  alt: string;
  index: number | null;
  onIndexChange: (index: number) => void;
  onClose: () => void;
  /** The slide to focus once closed: the one the slider now shows. */
  returnFocus: () => HTMLElement | true;
}

function Lightbox({ images, alt, index, onIndexChange, onClose, returnFocus }: LightboxProps) {
  const open = index !== null;
  const current = index ?? 0;
  const media = images[current];
  const step = (delta: number) => onIndexChange(Math.min(images.length - 1, Math.max(0, current + delta)));
  const rtl = dirFor(getLocale()) === 'rtl';
  const label = slideLabel(current + 1, images.length);

  return (
    <Dialog.Root open={open} onOpenChange={(next) => (next ? null : onClose())}>
      <Dialog.Portal>
        <Dialog.Backdrop className="fixed inset-0 z-50 bg-qb-icon/90" />
        <Dialog.Popup
          finalFocus={returnFocus}
          onKeyDown={(event) => {
            if (event.key === 'ArrowLeft') step(rtl ? 1 : -1);
            if (event.key === 'ArrowRight') step(rtl ? -1 : 1);
          }}
          className="fixed inset-0 z-50 flex items-center justify-center p-4 outline-none"
        >
          <Dialog.Title className="sr-only">
            {alt} — {label}
          </Dialog.Title>
          <p aria-live="polite" className="sr-only">
            {label}
          </p>
          {media ? (
            <div className="relative h-[85dvh] w-full max-w-[1200px]">
              <Image
                src={media.sizes.original_webp || media.url}
                alt={`${alt} — ${current + 1}`}
                fill
                sizes="(min-width: 1232px) 1200px, 100vw"
                className="object-contain"
              />
            </div>
          ) : null}
          <Dialog.Close
            aria-label={t('media.close_fullscreen')}
            className={cn('absolute end-4 top-4 flex size-11 items-center justify-center rounded-full bg-qb-surface text-qb-ink', focusRing)}
          >
            <Icon icon={X} size="lg" />
          </Dialog.Close>
          {images.length > 1 ? (
            <>
              <ArrowButton icon={ArrowLeft} label={t('media.prev')} atEnd={current === 0} onClick={() => step(-1)} className="start-4" />
              <ArrowButton
                icon={ArrowRight}
                label={t('media.next')}
                atEnd={current === images.length - 1}
                onClick={() => step(1)}
                className="end-4"
              />
            </>
          ) : null}
        </Dialog.Popup>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
