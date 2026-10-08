'use client';

import { memo, useCallback, useEffect, useMemo, useRef, useState, type DragEvent } from 'react';
import { DndContext, PointerSensor, closestCenter, useSensor, useSensors, type Announcements, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, rectSortingStrategy } from '@dnd-kit/sortable';
import { ArrowUp } from 'lucide-react';
import { toast } from 'sonner';

import { Icon } from '@/components/design-system/Icon';
import { PHOTO_ACCEPTED_TYPES } from '@/lib/images/prepare-photo';
import { t } from '@/lib/i18n/messages';
import { AD_LIMITS, isPublishable } from '@/lib/post-ad/form';
import type { RejectedFile } from '@/lib/post-ad/photo-queue';
import type { PhotoItem } from '@/lib/post-ad/photos';
import { cn } from '@/lib/utils';
import { usePostAdStore } from '@/store/post-ad';

import { FieldError, describedBy, fieldId } from './FormParts';
import { PhotoTile, moveButtonId, type PhotoStep } from './PhotoTile';
import { photoQueue } from './session';

const LABEL_ID = 'post-ad-photos-label';
const HINT_ID = 'post-ad-photos-hint';
const NOTICE_ID = 'post-ad-photos-notice';

function rejectionText(rejected: RejectedFile[]): string | null {
  if (rejected.length === 0) return null;
  if (rejected.some((file) => file.problem === 'limit')) {
    return t('post_ad.photos.rejected_limit', { max: AD_LIMITS.photosMax });
  }
  const first = rejected[0];
  return first.problem === 'size'
    ? t('post_ad.photos.rejected_size', { name: first.name })
    : t('post_ad.photos.rejected_type', { name: first.name });
}

/**
 * Spoken summary of the queue, so screen readers hear progress without every
 * percent. `uploadsNow` is false while photos wait for a save: a new ad, or a
 * live one, whose changes go up with "Save Changes".
 */
function queueSummary(photos: PhotoItem[], uploadsNow: boolean): string {
  const failed = photos.filter((photo) => photo.status === 'failed').length;
  if (failed > 0) return t('post_ad.photos.some_failed');
  const busy = photos.filter((photo) => photo.status === 'processing' || photo.status === 'uploading').length;
  const waiting = photos.filter((photo) => photo.status === 'ready').length;
  const uploaded = photos.filter((photo) => photo.status === 'uploaded').length;
  if (busy > 0 || (waiting > 0 && uploadsNow)) {
    return t('post_ad.photos.summary_uploading', { done: uploaded, total: photos.length });
  }
  if (waiting > 0) return t('post_ad.photos.summary_waiting');
  return photos.length > 0 ? t('post_ad.photos.summary_done') : '';
}

function announcementsFor(photos: PhotoItem[]): Announcements {
  const position = (id: string | number) => photos.findIndex((photo) => photo.key === id) + 1;
  return {
    onDragStart: ({ active }) => t('post_ad.photos.dnd_picked', { n: position(active.id) }),
    onDragOver: ({ over }) => (over ? t('post_ad.photos.dnd_over', { n: position(over.id) }) : undefined),
    onDragEnd: ({ over }) => (over ? t('post_ad.photos.dnd_dropped', { n: position(over.id) }) : t('post_ad.photos.dnd_cancelled')),
    onDragCancel: () => t('post_ad.photos.dnd_cancelled'),
  };
}

// Module-level, so the memoised tiles get the same handlers on every render.
function removePhoto(key: string) {
  void photoQueue.remove(key).then((removed) => {
    if (!removed) toast.error(t('post_ad.photos.remove_failed'));
  });
}

function retryPhoto(key: string) {
  photoQueue.retry(key);
}

function makeCover(key: string) {
  photoQueue.makeCover(key);
}

/**
 * Dropzone and photo grid of add-ads.html, with per-photo progress, reordering
 * and the cover. Memoised: typing in the fields around it must not redraw 20 tiles.
 */
export const PhotoUploader = memo(function PhotoUploader({ error }: { error?: string }) {
  const photos = usePostAdStore((state) => state.photos);
  const uploadsNow = usePostAdStore((state) => state.ad !== null && isPublishable(state.ad));
  const clearError = usePostAdStore((state) => state.clearError);
  const [dragging, setDragging] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [moveNotice, setMoveNotice] = useState('');
  const movedPhoto = useRef<{ key: string; step: PhotoStep } | null>(null);
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
  const announcements = announcementsFor(photos);
  const full = photos.length >= AD_LIMITS.photosMax;
  // Stable while only progress changes, so the sortable tiles don't all redraw.
  const keyList = photos.map((photo) => photo.key).join(' ');
  const keys = useMemo(() => (keyList ? keyList.split(' ') : []), [keyList]);

  const move = useCallback((key: string, step: PhotoStep) => {
    const current = usePostAdStore.getState().photos;
    const from = current.findIndex((photo) => photo.key === key);
    const to = from + step;
    if (from === -1 || to < 0 || to >= current.length) return;
    movedPhoto.current = { key, step };
    photoQueue.move(from, to);
    setMoveNotice(t('post_ad.photos.moved', { n: to + 1, total: current.length }));
  }, []);

  // Reordering can move the focused tile's node, which drops focus; put it back on its arrow.
  useEffect(() => {
    const moved = movedPhoto.current;
    if (!moved) return;
    movedPhoto.current = null;
    const button = document.getElementById(moveButtonId(moved.key, moved.step)) ?? document.getElementById(moveButtonId(moved.key, -moved.step as PhotoStep));
    button?.focus();
  }, [photos]);

  function addFiles(files: File[]) {
    if (files.length === 0) return;
    const result = photoQueue.add(files, AD_LIMITS.photosMax);
    setNotice(rejectionText(result.rejected));
    if (result.added > 0) clearError('photos');
  }

  function onDrop(event: DragEvent<HTMLLabelElement>) {
    event.preventDefault();
    setDragging(false);
    addFiles(Array.from(event.dataTransfer.files));
  }

  function onDragOver(event: DragEvent<HTMLLabelElement>) {
    if (!event.dataTransfer.types.includes('Files')) return;
    event.preventDefault();
    setDragging(true);
  }

  function onDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return;
    const from = photos.findIndex((photo) => photo.key === active.id);
    const to = photos.findIndex((photo) => photo.key === over.id);
    photoQueue.move(from, to);
  }

  return (
    <div>
      <p id={LABEL_ID} className="mb-2.5 text-qb-body-sm text-qb-ink-body">
        {t('post_ad.basic.images')}
      </p>
      <label
        onDragOver={onDragOver}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
        className={cn(
          'flex cursor-pointer flex-col items-center gap-2.5 rounded-qb-lg border-[1.5px] border-dashed px-5 py-9 text-center transition-colors',
          'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-qb-brand-active',
          dragging ? 'border-qb-brand bg-qb-brand-soft' : 'border-qb-line-strong hover:bg-qb-hover',
          error && 'border-qb-danger',
          full && 'cursor-not-allowed opacity-60',
        )}
      >
        <input
          id={fieldId('photos')}
          type="file"
          accept={PHOTO_ACCEPTED_TYPES.join(',')}
          multiple
          disabled={full}
          className="sr-only"
          aria-labelledby={LABEL_ID}
          {...describedBy('photos', error, HINT_ID, notice ? NOTICE_ID : undefined)}
          onChange={(event) => {
            addFiles(Array.from(event.target.files ?? []));
            // Lets the same file be picked again after it was removed.
            event.target.value = '';
          }}
        />
        <UploadCloud />
        <span className="font-qb-label text-qb-micro text-qb-ink-body">{t('post_ad.photos.drop')}</span>
        <span id={HINT_ID} className="text-qb-label text-qb-ink-subtle">
          {t('post_ad.photos.limits', { max: AD_LIMITS.photosMax })}
        </span>
      </label>

      {notice ? (
        <p id={NOTICE_ID} role="alert" className="mt-2 text-qb-label text-qb-danger">
          {notice}
        </p>
      ) : null}
      <FieldError name="photos" message={error} />

      {photos.length > 0 ? (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragEnd={onDragEnd}
          accessibility={{ announcements, screenReaderInstructions: { draggable: t('post_ad.photos.reorder_hint') } }}
        >
          <SortableContext items={keys} strategy={rectSortingStrategy}>
            <ul aria-label={t('post_ad.photos.list_label')} className="mt-3.5 [display:grid] grid-cols-[repeat(auto-fill,minmax(90px,1fr))] gap-2.5">
              {photos.map((photo, index) => (
                <PhotoTile
                  key={photo.key}
                  photo={photo}
                  position={index + 1}
                  total={photos.length}
                  onRemove={removePhoto}
                  onRetry={retryPhoto}
                  onMakeCover={makeCover}
                  onMove={move}
                />
              ))}
            </ul>
          </SortableContext>
          <p className="mt-2 text-qb-label text-qb-ink-subtle">{t('post_ad.photos.reorder_hint')}</p>
        </DndContext>
      ) : null}
      <p aria-live="polite" className="sr-only">
        {queueSummary(photos, uploadsNow)}
      </p>
      <p aria-live="polite" className="sr-only">
        {moveNotice}
      </p>
    </div>
  );
});

/** The orange cloud with the green arrow above the drop text. */
function UploadCloud() {
  return (
    <span aria-hidden="true" className="relative flex h-12 w-[60px] items-center justify-center">
      <svg width="58" height="44" viewBox="0 0 24 24" className="fill-qb-brand">
        <path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z" />
      </svg>
      <span className="absolute top-[60%] left-1/2 flex size-[22px] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-qb-surface bg-qb-success">
        <Icon icon={ArrowUp} size="sm" strokeWidth={3} className="size-3 text-qb-surface" />
      </span>
    </span>
  );
}
