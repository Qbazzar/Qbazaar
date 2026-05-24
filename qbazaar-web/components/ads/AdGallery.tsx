'use client';

/**
 * Embla-powered gallery for the ad detail page.
 *
 * Renders the active slide as a BlurHash-backed image and a horizontal strip
 * of thumbnails underneath. Tapping a thumb scrolls the carousel to it; the
 * arrow buttons paginate one slide at a time.
 *
 * Fullscreen overlay opens on slide click and reuses the same carousel
 * instance so the user lands on whichever slide they tapped.
 */
import { useCallback, useEffect, useState } from 'react';
import useEmblaCarousel from 'embla-carousel-react';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { BlurHashImage } from '@/components/upload/BlurHashImage';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';
import type { Media } from '@/lib/api/types';

interface Props {
  images: Media[];
  alt: string;
  className?: string;
}

export function AdGallery({ images, alt, className }: Props) {
  const [emblaRef, emblaApi] = useEmblaCarousel({ loop: false, direction: 'rtl' });
  const [selected, setSelected] = useState(0);
  const [overlayOpen, setOverlayOpen] = useState(false);

  useEffect(() => {
    if (!emblaApi) return;
    const onSelect = () => setSelected(emblaApi.selectedScrollSnap());
    onSelect();
    emblaApi.on('select', onSelect);
    return () => {
      emblaApi.off('select', onSelect);
    };
  }, [emblaApi]);

  const scrollTo = useCallback(
    (idx: number) => emblaApi?.scrollTo(idx),
    [emblaApi],
  );

  if (images.length === 0) {
    return (
      <div
        className={cn(
          'bg-cream-200 text-ink-500 flex w-full items-center justify-center rounded-2xl text-sm',
          className,
        )}
        style={{ aspectRatio: '4 / 3' }}
      >
        {t('media.no_image', 'لا توجد صور')}
      </div>
    );
  }

  return (
    <div className={cn('space-y-3', className)}>
      <div className="relative overflow-hidden rounded-2xl ring-1 ring-foreground/10">
        <div ref={emblaRef} className="overflow-hidden">
          <div className="flex">
            {images.map((media, idx) => (
              <button
                key={media.id}
                type="button"
                onClick={() => setOverlayOpen(true)}
                className="relative min-w-0 flex-[0_0_100%] cursor-zoom-in"
                aria-label={t('media.open_fullscreen', 'فتح بحجم كامل')}
              >
                <BlurHashImage
                  src={media.sizes.large || media.url}
                  alt={`${alt} — ${idx + 1}`}
                  blurhash={media.blurhash}
                  aspect="4 / 3"
                  className="w-full"
                  loading={idx === 0 ? 'eager' : 'lazy'}
                />
              </button>
            ))}
          </div>
        </div>

        {images.length > 1 ? (
          <>
            <Button
              type="button"
              size="icon-sm"
              variant="outline"
              onClick={() => emblaApi?.scrollPrev()}
              className="absolute end-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 shadow"
              aria-label={t('media.prev', 'السابق')}
            >
              <ChevronRight className="size-4" />
            </Button>
            <Button
              type="button"
              size="icon-sm"
              variant="outline"
              onClick={() => emblaApi?.scrollNext()}
              className="absolute start-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 shadow"
              aria-label={t('media.next', 'التالي')}
            >
              <ChevronLeft className="size-4" />
            </Button>
            <span className="bg-ink-900/70 absolute bottom-2 end-2 rounded-full px-2 py-0.5 text-[11px] font-medium text-white">
              {selected + 1} / {images.length}
            </span>
          </>
        ) : null}
      </div>

      {/* Thumb strip */}
      {images.length > 1 ? (
        <div className="flex gap-2 overflow-x-auto pb-1">
          {images.map((media, idx) => (
            <button
              key={media.id}
              type="button"
              onClick={() => scrollTo(idx)}
              className={cn(
                'relative size-16 shrink-0 overflow-hidden rounded-lg ring-2 transition-all',
                idx === selected ? 'ring-coral' : 'ring-transparent hover:ring-ink-200',
              )}
              aria-label={`${alt} — ${idx + 1}`}
            >
              <BlurHashImage
                src={media.sizes.thumbnail || media.url}
                alt=""
                blurhash={media.blurhash}
                aspect="1 / 1"
                className="size-full"
              />
            </button>
          ))}
        </div>
      ) : null}

      {/* Fullscreen overlay */}
      {overlayOpen ? (
        <div
          role="dialog"
          aria-modal="true"
          className="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/90 p-4"
          onClick={() => setOverlayOpen(false)}
        >
          <Button
            type="button"
            size="icon"
            variant="outline"
            className="absolute end-4 top-4 z-10 rounded-full bg-white/90"
            onClick={() => setOverlayOpen(false)}
            aria-label={t('media.close_fullscreen', 'إغلاق')}
          >
            <X className="size-4" />
          </Button>
          <img
            src={images[selected]?.sizes.original_webp || images[selected]?.url}
            alt={`${alt} — ${selected + 1}`}
            className="max-h-[90vh] max-w-full object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      ) : null}
    </div>
  );
}
