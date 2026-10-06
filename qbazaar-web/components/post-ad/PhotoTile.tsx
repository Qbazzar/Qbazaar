'use client';

import Image from 'next/image';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { CircleAlert, GripVertical, LoaderCircle, RotateCw, Star, X } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';
import { AD_LIMITS } from '@/lib/post-ad/form';
import type { PhotoItem } from '@/lib/post-ad/photos';
import { cn } from '@/lib/utils';

export interface PhotoTileProps {
  photo: PhotoItem;
  position: number;
  total: number;
  onRemove: () => void;
  onRetry: () => void;
  onMakeCover: () => void;
}

const cornerButton = cn(
  'absolute flex size-[22px] items-center justify-center rounded-full bg-qb-ink/60 text-white transition-colors hover:bg-qb-ink/80',
  focusRing,
);

/** Failure text for a tile, by what went wrong. */
export function photoFailureText(photo: PhotoItem): string {
  if (photo.failure === 'process') return t('post_ad.photos.failed_process');
  switch (photo.errorCode) {
    case 'NETWORK_ERROR':
      return t('post_ad.photos.failed_network');
    case 'UPLOAD_003':
      return t('post_ad.photos.failed_limit', { max: AD_LIMITS.photosMax });
    case 'UPLOAD_001':
    case 'UPLOAD_002':
    case 'UPLOAD_004':
      return t('post_ad.photos.failed_rejected');
    default:
      return t('post_ad.photos.failed_upload');
  }
}

function statusText(photo: PhotoItem): string {
  switch (photo.status) {
    case 'processing':
      return t('post_ad.photos.status_processing');
    case 'ready':
      return t('post_ad.photos.status_ready');
    case 'uploading':
      return t('post_ad.photos.status_uploading', { percent: photo.progress });
    case 'uploaded':
      return t('post_ad.photos.status_uploaded');
    case 'failed':
      return photoFailureText(photo);
  }
}

/** One photo in the grid: drag handle, cover, remove, and its upload state. */
export function PhotoTile({ photo, position, total, onRemove, onRetry, onMakeCover }: PhotoTileProps) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: photo.key,
    attributes: { roleDescription: t('post_ad.photos.sortable') },
  });
  const isCover = position === 1;
  const settled = photo.status === 'uploaded';
  const label = t('post_ad.photos.photo_label', { n: position, total });

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      // Pointer drags start anywhere on the photo; the keyboard uses the handle.
      onPointerDown={(event) => listeners?.onPointerDown?.(event)}
      className={cn(
        'relative aspect-square touch-manipulation overflow-hidden rounded-qb-md border border-qb-line bg-qb-fill',
        isDragging && 'z-10 shadow-qb-raised',
      )}
    >
      {photo.previewUrl ? (
        <Image
          src={photo.previewUrl}
          alt={label}
          fill
          sizes="(min-width: 1001px) 100px, 30vw"
          draggable={false}
          className={cn('object-cover transition-opacity', !settled && 'opacity-60')}
        />
      ) : (
        <span role="img" aria-label={label} className="absolute inset-0" />
      )}
      <span className="sr-only">{statusText(photo)}</span>

      {isCover ? (
        <span className="absolute start-1 top-1 rounded-qb-xs bg-qb-brand px-1.5 py-0.5 text-qb-tiny font-medium text-white">
          {t('post_ad.photos.cover')}
        </span>
      ) : null}

      <button type="button" onClick={onRemove} aria-label={t('post_ad.photos.remove', { n: position })} className={cn(cornerButton, 'end-1 top-1')}>
        <Icon icon={X} size="sm" className="size-3.5" />
      </button>

      {!isCover && photo.status !== 'failed' ? (
        <button
          type="button"
          onClick={onMakeCover}
          aria-label={t('post_ad.photos.make_cover', { n: position })}
          className={cn(cornerButton, 'start-1 bottom-1')}
        >
          <Icon icon={Star} size="sm" className="size-3" />
        </button>
      ) : null}

      <button
        type="button"
        ref={setActivatorNodeRef}
        {...attributes}
        onKeyDown={(event) => listeners?.onKeyDown?.(event)}
        aria-label={t('post_ad.photos.move', { n: position })}
        className={cn(cornerButton, 'end-1 bottom-1 cursor-grab active:cursor-grabbing')}
      >
        <Icon icon={GripVertical} size="sm" className="size-3.5" />
      </button>

      {photo.status === 'processing' ? (
        <span aria-hidden="true" className="absolute inset-0 flex items-center justify-center">
          <Icon icon={LoaderCircle} size="lg" className="animate-spin text-qb-ink-secondary motion-reduce:animate-none" />
        </span>
      ) : null}

      {photo.status === 'uploading' ? (
        <span
          role="progressbar"
          aria-label={t('post_ad.photos.uploading_label', { n: position })}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={photo.progress}
          className="absolute inset-x-2 top-1/2 h-1.5 -translate-y-1/2 overflow-hidden rounded-qb-pill bg-qb-surface/80"
        >
          <span className="block h-full rounded-qb-pill bg-qb-brand transition-[width]" style={{ width: `${Math.max(photo.progress, 4)}%` }} />
        </span>
      ) : null}

      {photo.status === 'failed' ? (
        <span className="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-qb-danger-soft/90 px-1 text-center text-qb-danger">
          <Icon icon={CircleAlert} size="md" />
          {photo.failure === 'upload' ? (
            <button
              type="button"
              onClick={onRetry}
              aria-label={t('post_ad.photos.retry', { n: position })}
              className={cn('inline-flex items-center gap-1 rounded-qb-xs px-1.5 py-0.5 text-qb-tiny font-medium underline-offset-2 hover:underline', focusRing)}
            >
              <Icon icon={RotateCw} size="sm" className="size-3" />
              {t('post_ad.photos.retry_short')}
            </button>
          ) : null}
        </span>
      ) : null}
    </li>
  );
}
