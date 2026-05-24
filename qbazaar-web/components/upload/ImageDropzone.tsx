'use client';

/**
 * Drag-and-drop image uploader for ad media (FE-4 / image UX wave).
 *
 * Responsibilities:
 *
 *   - File picker via click + dropzone area
 *   - Client-side validation (mime + size) BEFORE we touch the network
 *   - Per-batch compression via `compressImage` (1920 max edge, JPEG 0.85)
 *   - Per-batch progress through axios `onUploadProgress`
 *   - Grid of existing thumbnails with drag-to-reorder via dnd-kit
 *   - Per-thumb delete with optimistic UI
 *   - BlurHash placeholders while real images load
 *
 * Props:
 *
 *   - `adId`: target ad. Uploads are POSTed to /ads/{adId}/images.
 *   - `existing`: server-known media in `order` ascending (rendered first).
 *   - `max` (default 20): client-side cap mirroring the backend's hard limit.
 *   - `onChange`: fires whenever the media list changes locally so the
 *     wizard parent can keep its own state in sync.
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  DndContext,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  rectSortingStrategy,
} from '@dnd-kit/sortable';
import { Camera, Upload } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { MediaThumb } from './MediaThumb';
import { compressImage } from '@/lib/images/compressImage';
import {
  useDeleteMediaMutation,
  useReorderAdImagesMutation,
  useUploadAdImagesMutation,
} from '@/lib/queries/ad-images';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Media } from '@/lib/api/types';

interface Props {
  adId: string;
  existing: Media[];
  max?: number;
  onChange?: (images: Media[]) => void;
  className?: string;
}

const MAX_BYTES = 10 * 1024 * 1024;
const ACCEPTED = new Set(['image/jpeg', 'image/png', 'image/webp']);

export function ImageDropzone({
  adId,
  existing,
  max = 20,
  onChange,
  className,
}: Props) {
  const [images, setImages] = useState<Media[]>(existing);
  const [isDraggingFiles, setDraggingFiles] = useState(false);
  const [progress, setProgress] = useState<number>(0);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const uploadMutation = useUploadAdImagesMutation();
  const deleteMutation = useDeleteMediaMutation();
  const reorderMutation = useReorderAdImagesMutation();

  // Keep local state in sync when the server payload changes (after upload
  // invalidates the parent query).
  useEffect(() => {
    setImages(existing);
  }, [existing]);

  const commit = useCallback(
    (next: Media[]) => {
      setImages(next);
      onChange?.(next);
    },
    [onChange],
  );

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const remainingSlots = Math.max(0, max - images.length);

  // ── File handling ───────────────────────────────────────────────────────

  const handleFiles = useCallback(
    async (filesList: FileList | File[]) => {
      const incoming = Array.from(filesList);
      if (incoming.length === 0) return;

      // Validate up-front so we can surface ALL problems in one toast.
      const valid: File[] = [];
      const rejected: string[] = [];
      for (const file of incoming) {
        if (!ACCEPTED.has(file.type)) {
          rejected.push(t('media.upload.error_type', 'صيغة غير مدعومة'));
          continue;
        }
        if (file.size > MAX_BYTES) {
          rejected.push(t('media.upload.error_size', 'الملف أكبر من 10MB'));
          continue;
        }
        valid.push(file);
      }
      if (rejected.length) {
        toast.error(rejected[0]);
      }
      if (valid.length === 0) return;

      const allowed = valid.slice(0, remainingSlots);
      if (allowed.length < valid.length) {
        toast.error(
          t('media.upload.max_count', { max: String(max) }, `الحد الأقصى ${max} صورة`),
        );
      }
      if (allowed.length === 0) return;

      try {
        setProgress(1);
        const compressed = await Promise.all(allowed.map(compressImage));
        const blobs = compressed.map((c) => c.blob);
        const uploaded = await uploadMutation.mutateAsync({
          adId,
          files: blobs,
          options: { onProgress: (pct) => setProgress(pct) },
        });
        commit([...images, ...uploaded]);
        toast.success(
          t('media.upload.success', { count: String(uploaded.length) }, 'تم رفع الصور'),
        );
      } catch (err) {
        toast.error(
          (err as { message?: string })?.message ??
            t('media.upload.error', 'تعذّر رفع الصور'),
        );
      } finally {
        setProgress(0);
      }
    },
    [adId, commit, images, max, remainingSlots, uploadMutation],
  );

  // ── DOM event glue ──────────────────────────────────────────────────────

  const onDrop = useCallback(
    (e: React.DragEvent<HTMLDivElement>) => {
      e.preventDefault();
      setDraggingFiles(false);
      if (remainingSlots === 0) return;
      if (e.dataTransfer.files?.length) void handleFiles(e.dataTransfer.files);
    },
    [handleFiles, remainingSlots],
  );

  const onPick = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files?.length) void handleFiles(e.target.files);
    // Reset so picking the same file twice still triggers `change`.
    e.target.value = '';
  };

  // ── Reorder ─────────────────────────────────────────────────────────────

  const onDragEnd = useCallback(
    async (event: DragEndEvent) => {
      const { active, over } = event;
      if (!over || active.id === over.id) return;
      const oldIndex = images.findIndex((m) => m.id === active.id);
      const newIndex = images.findIndex((m) => m.id === over.id);
      if (oldIndex === -1 || newIndex === -1) return;
      const next = arrayMove(images, oldIndex, newIndex);
      commit(next);
      try {
        await reorderMutation.mutateAsync({
          adId,
          mediaIds: next.map((m) => m.id),
        });
      } catch (err) {
        // Roll back on failure so the UI doesn't drift from the server.
        commit(images);
        toast.error(
          (err as { message?: string })?.message ??
            t('media.upload.error_reorder', 'تعذّر حفظ الترتيب'),
        );
      }
    },
    [adId, commit, images, reorderMutation],
  );

  // ── Delete ──────────────────────────────────────────────────────────────

  const onDelete = useCallback(
    async (mediaId: string) => {
      const previous = images;
      const next = images.filter((m) => m.id !== mediaId);
      commit(next);
      try {
        await deleteMutation.mutateAsync({ adId, mediaId });
      } catch (err) {
        commit(previous);
        toast.error(
          (err as { message?: string })?.message ??
            t('media.upload.error_delete', 'تعذّر حذف الصورة'),
        );
      }
    },
    [adId, commit, deleteMutation, images],
  );

  const isUploading = uploadMutation.isPending || progress > 0;
  const ids = useMemo(() => images.map((m) => m.id), [images]);

  return (
    <div className={cn('space-y-4', className)}>
      {/* Dropzone */}
      <div
        onDragOver={(e) => {
          e.preventDefault();
          if (remainingSlots > 0) setDraggingFiles(true);
        }}
        onDragLeave={() => setDraggingFiles(false)}
        onDrop={onDrop}
        className={cn(
          'flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed bg-cream-50 px-6 py-10 text-center transition-colors',
          isDraggingFiles
            ? 'border-coral bg-coral/5'
            : 'border-ink-200 hover:border-coral/50',
          remainingSlots === 0 && 'opacity-50',
        )}
      >
        <span className="bg-cream-200 text-terracotta flex size-12 items-center justify-center rounded-full">
          <Camera className="size-5" />
        </span>
        <p className="text-ink-900 font-medium">
          {t('media.upload.drop', 'اسحب الصور هنا أو')}
        </p>
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => fileInputRef.current?.click()}
            disabled={remainingSlots === 0 || isUploading}
          >
            <Upload className="size-3.5" />
            {t('media.upload.browse', 'تصفّح الملفات')}
          </Button>
        </div>
        <p className="text-ink-500 text-xs">
          {t(
            'media.upload.constraints',
            { max: String(max) },
            `حتى ${max} صورة · JPG/PNG/WEBP · 10MB لكل صورة`,
          )}
        </p>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          className="sr-only"
          onChange={onPick}
        />
      </div>

      {/* Progress strip */}
      {isUploading ? (
        <div className="bg-cream-200 h-1.5 w-full overflow-hidden rounded-full">
          <div
            className="bg-coral h-full transition-all"
            style={{ width: `${Math.max(progress, 5)}%` }}
            role="progressbar"
            aria-valuenow={progress}
            aria-valuemin={0}
            aria-valuemax={100}
          />
        </div>
      ) : null}

      {/* Existing grid */}
      {images.length > 0 ? (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={ids} strategy={rectSortingStrategy}>
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
              {images.map((media, index) => (
                <MediaThumb
                  key={media.id}
                  media={media}
                  isCover={index === 0}
                  onDelete={() => void onDelete(media.id)}
                  disabled={isUploading || deleteMutation.isPending}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      ) : null}
    </div>
  );
}
