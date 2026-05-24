'use client';

/**
 * One thumbnail in the ad-images dropzone grid.
 *
 * Renders the BlurHash-backed image, exposes the dnd-kit drag handle for
 * reordering, and shows a delete button on hover. The first item gets a
 * "Cover" badge to teach the user that order matters.
 */
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { BlurHashImage } from './BlurHashImage';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { Media } from '@/lib/api/types';

interface Props {
  media: Media;
  isCover: boolean;
  onDelete: () => void;
  disabled?: boolean;
}

export function MediaThumb({ media, isCover, onDelete, disabled }: Props) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: media.id, disabled });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'group relative overflow-hidden rounded-xl ring-1 ring-foreground/10 bg-card',
        isDragging && 'z-10 shadow-lg ring-coral',
      )}
    >
      <BlurHashImage
        src={media.sizes.thumbnail || media.url}
        alt=""
        blurhash={media.blurhash}
        aspect="1 / 1"
        className="size-full"
      />

      {isCover ? (
        <span className="bg-coral pointer-events-none absolute start-2 top-2 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white">
          {t('media.cover', 'الغلاف')}
        </span>
      ) : null}

      {/* Drag handle */}
      <button
        type="button"
        aria-label={t('media.reorder_handle', 'سحب لإعادة الترتيب')}
        className={cn(
          'absolute bottom-2 start-2 flex size-7 items-center justify-center rounded-full bg-ink-900/70 text-white opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100',
          disabled && 'pointer-events-none',
        )}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3.5" />
      </button>

      {/* Delete */}
      <Button
        type="button"
        variant="destructive"
        size="icon-xs"
        aria-label={t('media.delete_confirm', 'حذف الصورة')}
        onClick={onDelete}
        disabled={disabled}
        className="absolute end-2 top-2 size-7 rounded-full bg-ink-900/70 text-white opacity-0 transition-opacity hover:bg-ink-900 group-hover:opacity-100 focus-visible:opacity-100"
      >
        <X className="size-3.5" />
      </Button>
    </div>
  );
}
