'use client';

/**
 * AvatarUploader (FE-2.13)
 *
 * The avatar + "Change Profile photo" control of 393:8828; it owns the whole
 * upload journey:
 *
 *   pick a file  →  client-side validate  →  crop 1:1 modal  →
 *   POST /uploads/avatar  →  patch the auth store so the new photo
 *   shows up immediately on the sidebar/header.
 *
 * The crop step is mandatory (and constrained to a 1:1 aspect) so every
 * avatar lands on the backend in the same shape — the server only has to
 * resize, not re-crop.
 *
 * Validation rules MUST mirror the backend (BE-2.12):
 *   - MIME: jpeg / png / webp only
 *   - Size: ≤ 5 MB
 *
 * After a successful upload we toast + call `setAvatarUrls` on the auth
 * store. We do NOT touch React Query directly here; the consumer can pass
 * `onUploaded` if it wants to invalidate a specific query.
 */
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Camera, Loader2, ZoomIn } from 'lucide-react';
import Cropper, { type Area } from 'react-easy-crop';

import { Avatar } from '@/components/design-system/Avatar';
import { Button } from '@/components/design-system/Button';
import { Modal } from '@/components/design-system/Modal';
import { t, translateMaybeKey } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import { uploadAvatar } from '@/lib/api/uploads';
import { ApiClientError } from '@/lib/api/auth';
import { useAuthStore } from '@/store/auth';
import type { AvatarUploadResponse } from '@/lib/api/types';

import { ModalActions } from './ModalActions';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const;
const MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const OUTPUT_SIZE = 512; // post-crop square edge, matches backend medium size
const OUTPUT_MIME = 'image/jpeg';
const OUTPUT_QUALITY = 0.9;

type AcceptedMime = (typeof ACCEPTED_TYPES)[number];

function isAcceptedMime(value: string): value is AcceptedMime {
  return (ACCEPTED_TYPES as readonly string[]).includes(value);
}

export interface AvatarUploaderProps {
  /**
   * The user's full name — used for the fallback initials when no avatar
   * is set yet. The component reads the URL straight from the auth store.
   */
  fullName: string;
  /** Optional hook fired after a successful upload (e.g. to invalidate queries). */
  onUploaded?: (urls: AvatarUploadResponse) => void;
  className?: string;
}

interface ValidationError {
  /** Translated error message ready to render. */
  message: string;
}

function validateFile(file: File): ValidationError | null {
  if (!isAcceptedMime(file.type)) {
    return { message: t('account.avatar.errors.type') };
  }
  if (file.size > MAX_BYTES) {
    return { message: t('account.avatar.errors.size') };
  }
  return null;
}

function readFileAsDataUrl(file: File | Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () =>
      typeof reader.result === 'string'
        ? resolve(reader.result)
        : reject(new Error('not-a-string'));
    reader.onerror = () => reject(reader.error ?? new Error('read-error'));
    reader.readAsDataURL(file);
  });
}

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('image-decode-failed'));
    img.src = src;
  });
}

/**
 * Crops the source image to the supplied 1:1 area and returns a JPEG `Blob`
 * sized to OUTPUT_SIZE px. Done off-DOM via a canvas so we don't ship a heavy
 * image lib for one crop.
 */
async function cropToBlob(
  imageSrc: string,
  area: Area,
): Promise<Blob> {
  const image = await loadImage(imageSrc);
  const canvas = document.createElement('canvas');
  canvas.width = OUTPUT_SIZE;
  canvas.height = OUTPUT_SIZE;
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('canvas-unsupported');

  ctx.drawImage(
    image,
    area.x,
    area.y,
    area.width,
    area.height,
    0,
    0,
    OUTPUT_SIZE,
    OUTPUT_SIZE,
  );

  return new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (blob) =>
        blob ? resolve(blob) : reject(new Error('canvas-to-blob-failed')),
      OUTPUT_MIME,
      OUTPUT_QUALITY,
    );
  });
}

export function AvatarUploader({
  fullName,
  onUploaded,
  className,
}: AvatarUploaderProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);

  const user = useAuthStore((s) => s.user);
  const setAvatarUrls = useAuthStore((s) => s.setAvatarUrls);

  const currentAvatar =
    user?.avatar_medium_url ?? user?.avatar_url ?? null;

  const hintId = useId();
  const [dragOver, setDragOver] = useState(false);
  const [inlineError, setInlineError] = useState<string | null>(null);

  // Crop modal state
  const [pickedImage, setPickedImage] = useState<string | null>(null);
  const [crop, setCrop] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [croppedArea, setCroppedArea] = useState<Area | null>(null);

  // Release blob URLs the moment we don't need them.
  useEffect(() => {
    return () => {
      if (pickedImage?.startsWith('blob:')) {
        URL.revokeObjectURL(pickedImage);
      }
    };
  }, [pickedImage]);

  const mutation = useMutation({
    mutationFn: async (blob: Blob) => uploadAvatar(blob),
    onSuccess: (data) => {
      setAvatarUrls({
        avatar_url: data.avatar_url,
        avatar_thumb_url: data.avatar_thumb_url,
        avatar_medium_url: data.avatar_medium_url,
      });
      toast.success(t('account.avatar.uploaded'));
      onUploaded?.(data);
      closeCropModal();
    },
    onError: (err) => {
      if (err instanceof ApiClientError) {
        // 422 with per-field details → inline below the dropzone
        if (err.code === 'VALIDATION_FAILED' && err.details) {
          const firstField = Object.values(err.details)[0];
          const first = Array.isArray(firstField) ? firstField[0] : undefined;
          setInlineError(first ?? t('account.avatar.upload_failed'));
          return;
        }
        const translated =
          translateMaybeKey(`account.errors.${err.code}`) ||
          translateMaybeKey(`auth.errors.${err.code}`);
        setInlineError(translated || err.message);
        toast.error(translated || t('account.avatar.upload_failed'));
        return;
      }
      setInlineError(t('account.avatar.upload_failed'));
      toast.error(t('account.avatar.upload_failed'));
    },
  });

  const openCropModal = useCallback(async (file: File) => {
    setInlineError(null);
    const failed = validateFile(file);
    if (failed) {
      setInlineError(failed.message);
      return;
    }
    try {
      const dataUrl = await readFileAsDataUrl(file);
      setCrop({ x: 0, y: 0 });
      setZoom(1);
      setCroppedArea(null);
      setPickedImage(dataUrl);
    } catch {
      setInlineError(t('account.avatar.errors.read'));
    }
  }, []);

  const closeCropModal = useCallback(() => {
    setPickedImage(null);
    setCroppedArea(null);
    setCrop({ x: 0, y: 0 });
    setZoom(1);
    if (inputRef.current) inputRef.current.value = '';
  }, []);

  const onCropComplete = useCallback(
    (_area: Area, areaPixels: Area) => setCroppedArea(areaPixels),
    [],
  );

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) void openCropModal(file);
  };

  const handleDrop = (event: React.DragEvent<HTMLLabelElement>) => {
    event.preventDefault();
    setDragOver(false);
    const file = event.dataTransfer.files?.[0];
    if (file) void openCropModal(file);
  };

  const handleConfirmCrop = async () => {
    if (!pickedImage || !croppedArea) return;
    try {
      const blob = await cropToBlob(pickedImage, croppedArea);
      await mutation.mutateAsync(blob);
    } catch {
      setInlineError(t('account.avatar.upload_failed'));
      toast.error(t('account.avatar.upload_failed'));
    }
  };

  const acceptAttr = useMemo(() => ACCEPTED_TYPES.join(','), []);
  const uploading = mutation.isPending;

  return (
    <div className={cn('flex flex-col items-center font-qb', className)}>
      <label
        onDragOver={(event) => {
          event.preventDefault();
          setDragOver(true);
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={handleDrop}
        className={cn(
          'group flex cursor-pointer flex-col items-center gap-[18px] rounded-qb-lg p-2',
          'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-qb-brand-active',
          uploading && 'pointer-events-none opacity-60',
        )}
      >
        <input
          ref={inputRef}
          type="file"
          accept={acceptAttr}
          className="sr-only"
          onChange={handleFileChange}
          disabled={uploading}
          aria-label={t('account.avatar.change_photo')}
          aria-describedby={hintId}
        />
        <span className="relative">
          <Avatar
            name={fullName}
            src={currentAvatar}
            size="lg"
            className={cn(
              'size-[95px] bg-qb-fill text-[36px] font-medium text-qb-ink',
              dragOver && 'ring-2 ring-qb-brand',
            )}
          />
          <span
            aria-hidden="true"
            className="absolute inset-0 flex items-center justify-center rounded-full bg-qb-overlay text-white opacity-0 transition-opacity group-hover:opacity-100"
          >
            {uploading ? <Loader2 className="size-6 animate-spin" /> : <Camera className="size-6" />}
          </span>
        </span>
        <span className="text-qb-body font-medium text-qb-brand group-hover:underline">
          {t('account.avatar.change_photo')}
        </span>
      </label>
      <p id={hintId} className="mt-1 text-qb-label text-qb-ink-subtle">
        {t('account.avatar.supported')}
      </p>

      {inlineError ? (
        <p role="alert" className="mt-3 rounded-qb-md bg-qb-danger-soft px-3 py-2 text-qb-caption text-qb-danger">
          {inlineError}
        </p>
      ) : null}

      <Modal
        open={pickedImage !== null}
        onOpenChange={(open) => {
          if (!open && !uploading) closeCropModal();
        }}
        title={t('account.avatar.crop_title')}
        description={t('account.avatar.crop_subtitle')}
      >
        <div className="relative aspect-square w-full overflow-hidden rounded-qb-lg bg-qb-icon">
          {pickedImage ? (
            <Cropper
              image={pickedImage}
              crop={crop}
              zoom={zoom}
              aspect={1}
              cropShape="round"
              showGrid={false}
              onCropChange={setCrop}
              onZoomChange={setZoom}
              onCropComplete={onCropComplete}
            />
          ) : null}
        </div>

        <div className="mt-4 flex flex-col gap-2">
          <label htmlFor="avatar-zoom" className="flex items-center gap-2 text-qb-label font-medium text-qb-ink-secondary">
            <ZoomIn className="size-3.5" aria-hidden="true" />
            {t('account.avatar.crop_zoom_label')}
          </label>
          <input
            id="avatar-zoom"
            type="range"
            min={1}
            max={3}
            step={0.01}
            value={zoom}
            onChange={(event) => setZoom(Number(event.target.value))}
            className="w-full accent-qb-brand"
            disabled={uploading}
          />
        </div>

        <ModalActions className="mt-6">
          <Button size="sm" onClick={handleConfirmCrop} disabled={uploading || !croppedArea}>
            {uploading ? (
              <>
                <Loader2 className="animate-spin" aria-hidden="true" />
                {t('account.avatar.uploading')}
              </>
            ) : (
              t('account.avatar.crop_confirm')
            )}
          </Button>
          <Button variant="muted" size="sm" onClick={closeCropModal} disabled={uploading}>
            {t('account.avatar.crop_cancel')}
          </Button>
        </ModalActions>
      </Modal>
    </div>
  );
}
